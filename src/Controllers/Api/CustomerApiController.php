<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\ProductService;
use App\Services\QuotaService;

final class CustomerApiController extends ApiBaseController
{
    private QuotaService $quotaService;
    private ProductService $productService;

    public function __construct()
    {
        parent::__construct();
        $this->quotaService = new QuotaService();
        $this->productService = new ProductService();
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $search = trim((string)$request->get('q', ''));
        $route = trim((string)$request->get('route', ''));
        $status = trim((string)$request->get('status', ''));
        $milkType = trim((string)$request->get('milk_type', ''));

        $sql = "SELECT c.* FROM customers c WHERE c.tenant_id = ?";
        $params = [$tenantId];

        if (!empty($search)) {
            $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.address LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if (!empty($route)) {
            $sql .= " AND c.route_name = ?";
            $params[] = $route;
        }

        if (!empty($status)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        if (!empty($milkType)) {
            if ($milkType === 'both') {
                $sql .= " AND (c.milk_types LIKE '%cow%' AND c.milk_types LIKE '%buffalo%')";
            } elseif ($milkType === 'cow') {
                $sql .= " AND c.milk_types LIKE '%cow%'";
            } elseif ($milkType === 'buffalo') {
                $sql .= " AND c.milk_types LIKE '%buffalo%'";
            }
        }

        $sql .= " ORDER BY c.route_name ASC, c.name ASC";
        $customers = $this->db->fetchAll($sql, $params);

        // Fetch subscriptions for all customers
        $subscriptions = $this->db->fetchAll(
            "SELECT cs.*, p.name as product_name, p.code as product_code, p.unit, p.default_rate as product_default_rate,
                    COALESCE(cs.custom_rate, p.default_rate) as effective_rate
             FROM customer_subscriptions cs
             JOIN products p ON p.id = cs.product_id
             WHERE cs.tenant_id = ? AND cs.is_active = 1",
            [$tenantId]
        );

        $subsMap = [];
        foreach ($subscriptions as $s) {
            $subsMap[(int)$s['customer_id']][] = [
                'id' => (int)$s['id'],
                'product_id' => (int)$s['product_id'],
                'product_name' => $s['product_name'],
                'product_code' => $s['product_code'],
                'unit' => $s['unit'],
                'default_quantity' => (float)$s['default_quantity'],
                'custom_rate' => $s['custom_rate'] !== null ? (float)$s['custom_rate'] : null,
                'effective_rate' => (float)$s['effective_rate'],
                'shift' => $s['shift'],
            ];
        }

        foreach ($customers as &$c) {
            $cid = (int)$c['id'];
            $c['subscriptions'] = $subsMap[$cid] ?? [];
            $c['total_daily_quantity'] = array_sum(array_column($c['subscriptions'], 'default_quantity'));
        }

        $quota = $this->quotaService->getTenantQuota($tenantId);

        $this->jsonSuccess($customers, 'Customers retrieved', 200, [
            'total_count' => count($customers),
            'quota' => $quota,
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $subscriptions = $this->db->fetchAll(
            "SELECT cs.*, p.name as product_name, p.code as product_code, p.unit, p.default_rate as product_default_rate,
                    COALESCE(cs.custom_rate, p.default_rate) as effective_rate
             FROM customer_subscriptions cs
             JOIN products p ON p.id = cs.product_id
             WHERE cs.tenant_id = ? AND cs.customer_id = ?",
            [$tenantId, $id]
        );

        $leaves = $this->db->fetchAll(
            "SELECT * FROM customer_leaves WHERE tenant_id = ? AND customer_id = ? ORDER BY start_date DESC",
            [$tenantId, $id]
        );

        $recentInvoices = $this->db->fetchAll(
            "SELECT id, billing_month, billing_year, total_units, final_amount, payment_status, generated_at
             FROM invoices WHERE tenant_id = ? AND customer_id = ? ORDER BY billing_year DESC, billing_month DESC LIMIT 6",
            [$tenantId, $id]
        );

        $customer['subscriptions'] = $subscriptions;
        $customer['leaves'] = $leaves;
        $customer['recent_invoices'] = $recentInvoices;

        $this->jsonSuccess($customer);
    }

    public function store(Request $request): void
    {
        $tenantId = $this->tenantId();
        $name = trim((string)$request->input('name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $address = trim((string)$request->input('address', ''));
        $routeName = trim((string)$request->input('route_name', 'Main Route'));
        $status = strtolower(trim((string)$request->input('status', 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $errors = $this->validate([
            'name' => $name,
            'phone' => $phone,
        ], [
            'name' => 'required',
            'phone' => 'required',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        // Quota Guard Check
        if ($status === 'active' && !$this->quotaService->canCreateCustomer($tenantId)) {
            $quota = $this->quotaService->getTenantQuota($tenantId);
            $this->jsonError(
                'Customer limit reached for your current subscription plan. Please upgrade your quota capacity.',
                403,
                'QUOTA_EXCEEDED',
                ['quota' => $quota, 'upgrade_url' => '/api/subscription/upgrade']
            );
        }

        // Subscriptions payload parsing
        $subsInput = $request->input('subscriptions', []);
        $subsList = [];

        if (is_array($subsInput) && !empty($subsInput)) {
            foreach ($subsInput as $sub) {
                $pid = (int)($sub['product_id'] ?? 0);
                $qty = (float)($sub['quantity'] ?? 0.0);
                $rate = isset($sub['custom_rate']) && $sub['custom_rate'] !== '' && (float)$sub['custom_rate'] > 0 ? (float)$sub['custom_rate'] : null;
                $shift = (string)($sub['shift'] ?? 'both');
                $isActive = !isset($sub['is_active']) || !empty($sub['is_active']);

                if ($pid > 0 && $qty > 0 && $isActive) {
                    $subsList[] = [
                        'product_id' => $pid,
                        'quantity' => round($qty, 2),
                        'custom_rate' => $rate,
                        'shift' => in_array($shift, ['morning', 'evening', 'both'], true) ? $shift : 'both',
                        'is_active' => true,
                    ];
                }
            }
        }

        // Fallback default cow subscription if none provided
        if (empty($subsList)) {
            $this->productService->ensureDefaultProducts($tenantId);
            $cowProd = $this->db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
            if ($cowProd) {
                $subsList[] = [
                    'product_id' => (int)$cowProd['id'],
                    'quantity' => 1.0,
                    'custom_rate' => null,
                    'shift' => 'both',
                    'is_active' => true,
                ];
            }
        }

        $newCustomerId = 0;
        $this->db->transaction(function () use ($tenantId, $name, $phone, $address, $routeName, $status, $subsList, &$newCustomerId) {
            $this->db->execute(
                "INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, status)
                 VALUES (?, ?, ?, ?, ?, 'cow', ?)",
                [$tenantId, $name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $status]
            );
            $newCustomerId = (int)$this->db->lastInsertId();
            $this->productService->saveCustomerSubscriptions($tenantId, $newCustomerId, $subsList);
        });

        $created = $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$newCustomerId]);
        $created['subscriptions'] = $subsList;

        $this->jsonSuccess($created, 'Customer created successfully', 201);
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $name = trim((string)$request->input('name', $customer['name']));
        $phone = trim((string)$request->input('phone', $customer['phone']));
        $address = trim((string)$request->input('address', $customer['address']));
        $routeName = trim((string)$request->input('route_name', $customer['route_name']));
        $status = strtolower(trim((string)$request->input('status', $customer['status'])));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $errors = $this->validate([
            'name' => $name,
            'phone' => $phone,
        ], [
            'name' => 'required',
            'phone' => 'required',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        // Quota Guard Check on activation
        if ($customer['status'] === 'inactive' && $status === 'active' && !$this->quotaService->canCreateCustomer($tenantId)) {
            $quota = $this->quotaService->getTenantQuota($tenantId);
            $this->jsonError(
                'Cannot activate customer: Active customer limit reached for your plan.',
                403,
                'QUOTA_EXCEEDED',
                ['quota' => $quota]
            );
        }

        $subsInput = $request->input('subscriptions', null);

        $this->db->transaction(function () use ($tenantId, $id, $name, $phone, $address, $routeName, $status, $subsInput) {
            $this->db->execute(
                "UPDATE customers 
                 SET name = ?, phone = ?, address = ?, route_name = ?, status = ?
                 WHERE id = ? AND tenant_id = ?",
                [$name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $status, $id, $tenantId]
            );

            if ($subsInput !== null && is_array($subsInput)) {
                $subsList = [];
                foreach ($subsInput as $sub) {
                    $pid = (int)($sub['product_id'] ?? 0);
                    $qty = (float)($sub['quantity'] ?? 0.0);
                    $rate = isset($sub['custom_rate']) && $sub['custom_rate'] !== '' && (float)$sub['custom_rate'] > 0 ? (float)$sub['custom_rate'] : null;
                    $shift = (string)($sub['shift'] ?? 'both');
                    $isActive = !isset($sub['is_active']) || !empty($sub['is_active']);

                    if ($pid > 0 && $qty > 0 && $isActive) {
                        $subsList[] = [
                            'product_id' => $pid,
                            'quantity' => round($qty, 2),
                            'custom_rate' => $rate,
                            'shift' => in_array($shift, ['morning', 'evening', 'both'], true) ? $shift : 'both',
                            'is_active' => true,
                        ];
                    }
                }
                if (!empty($subsList)) {
                    $this->productService->saveCustomerSubscriptions($tenantId, $id, $subsList);
                }
            }
        });

        $updated = $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
        $updated['subscriptions'] = $this->db->fetchAll("SELECT * FROM customer_subscriptions WHERE customer_id = ?", [$id]);

        $this->jsonSuccess($updated, 'Customer updated successfully');
    }

    public function toggleStatus(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $newStatus = $customer['status'] === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active' && !$this->quotaService->canCreateCustomer($tenantId)) {
            $quota = $this->quotaService->getTenantQuota($tenantId);
            $this->jsonError('Cannot activate customer: quota limit reached.', 403, 'QUOTA_EXCEEDED', ['quota' => $quota]);
        }

        $this->db->execute(
            "UPDATE customers SET status = ? WHERE id = ? AND tenant_id = ?",
            [$newStatus, $id, $tenantId]
        );

        $customer['status'] = $newStatus;
        $this->jsonSuccess($customer, "Customer status updated to {$newStatus}");
    }

    public function routes(Request $request): void
    {
        $tenantId = $this->tenantId();
        $routes = $this->db->fetchAll(
            "SELECT DISTINCT route_name FROM customers WHERE tenant_id = ? AND route_name IS NOT NULL AND route_name != '' ORDER BY route_name ASC",
            [$tenantId]
        );

        $list = array_column($routes, 'route_name');
        $this->jsonSuccess($list);
    }
}
