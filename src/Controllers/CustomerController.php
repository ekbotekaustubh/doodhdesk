<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ProductService;
use App\Services\QuotaService;

final class CustomerController
{
    private Database $db;
    private QuotaService $quotaService;
    private ProductService $productService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->quotaService = new QuotaService();
        $this->productService = new ProductService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $this->productService->ensureDefaultProducts($tenantId);

        $search = trim((string)$request->get('q', ''));
        $route = trim((string)$request->get('route', ''));
        $status = trim((string)$request->get('status', ''));
        $milkType = trim((string)$request->get('milk_type', ''));

        $sql = "SELECT c.*,
                    (SELECT COUNT(*) FROM customer_leaves cl WHERE cl.customer_id = c.id AND CURRENT_DATE BETWEEN cl.start_date AND cl.end_date) AS is_currently_on_leave
                FROM customers c
                WHERE c.tenant_id = ?";
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

        if (!empty($milkType)) {
            if ($milkType === 'both') {
                $sql .= " AND (c.milk_types LIKE '%cow%' AND c.milk_types LIKE '%buffalo%')";
            } elseif ($milkType === 'cow') {
                $sql .= " AND c.milk_types LIKE '%cow%'";
            } elseif ($milkType === 'buffalo') {
                $sql .= " AND c.milk_types LIKE '%buffalo%'";
            }
        }

        if (!empty($status)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.status ASC, c.route_name ASC, c.name ASC";
        $customers = $this->db->fetchAll($sql, $params);

        // Attach active subscriptions for each customer
        $allSubs = $this->db->fetchAll(
            "SELECT cs.*, p.name AS product_name, p.code AS product_code, p.unit, p.default_rate AS product_default_rate
             FROM customer_subscriptions cs
             JOIN products p ON p.id = cs.product_id
             WHERE cs.tenant_id = ? AND cs.is_active = 1
             ORDER BY p.sort_order ASC, p.name ASC",
            [$tenantId]
        );
        $subsByCust = [];
        foreach ($allSubs as $s) {
            $subsByCust[(int)$s['customer_id']][] = $s;
        }

        foreach ($customers as &$c) {
            $c['subscriptions'] = $subsByCust[(int)$c['id']] ?? [];
        }
        unset($c);

        // Fetch distinct routes
        $routes = $this->db->fetchAll(
            "SELECT DISTINCT route_name FROM customers WHERE tenant_id = ? AND route_name != '' ORDER BY route_name ASC",
            [$tenantId]
        );

        $quota = $this->quotaService->getTenantQuota($tenantId);
        $products = $this->productService->getTenantProducts($tenantId, true);

        View::render('customers/index', [
            'customers' => $customers,
            'routes' => array_column($routes, 'route_name'),
            'products' => $products,
            'search' => $search,
            'selectedRoute' => $route,
            'selectedStatus' => $status,
            'selectedMilkType' => $milkType,
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
            'paywall' => Auth::flash('paywall'),
            'import_skipped' => Auth::flash('import_skipped'),
        ], 'main');
    }

    public function createView(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $quota = $this->quotaService->getTenantQuota($tenantId);

        // Guard: check quota before showing create form
        if (!$quota['can_add']) {
            Auth::flash('paywall', 'limit_reached');
            Response::redirect(url('/customers'));
        }

        $products = $this->productService->getTenantProducts($tenantId, true);

        View::render('customers/form', [
            'customer' => null,
            'quota' => $quota,
            'products' => $products,
            'subscriptions' => [],
            'error' => Auth::flash('error')
        ], 'main');
    }

    public function store(Request $request): void
    {
        $tenantId = Auth::tenantId();

        // Check Quota Guard
        if (!$this->quotaService->canCreateCustomer($tenantId)) {
            Auth::flash('error', 'Customer limit reached! Please upgrade your subscription tier to add more customers.');
            Auth::flash('paywall', 'limit_reached');
            Response::redirect(url('/customers'));
        }

        $name = trim((string)$request->post('name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $address = trim((string)$request->post('address', ''));
        $routeName = trim((string)$request->post('route_name', 'Main Route'));
        $status = (string)$request->post('status', 'active');

        // Extract and process product subscriptions (Approach A)
        $rawSubs = $request->post('subscriptions');
        $subsList = [];
        $totalSubQty = 0.0;
        $primaryRate = 60.0;

        if (is_array($rawSubs)) {
            foreach ($rawSubs as $pId => $subData) {
                if (!empty($subData['is_active']) && (float)($subData['quantity'] ?? 0) > 0) {
                    $q = (float)$subData['quantity'];
                    $r = isset($subData['custom_rate']) && $subData['custom_rate'] !== '' && (float)$subData['custom_rate'] > 0 ? (float)$subData['custom_rate'] : null;
                    $sh = (string)($subData['shift'] ?? 'both');
                    $subsList[] = [
                        'product_id' => (int)$pId,
                        'quantity' => $q,
                        'custom_rate' => $r,
                        'shift' => $sh,
                        'is_active' => true,
                    ];
                    $totalSubQty += $q;
                    if ($r !== null) {
                        $primaryRate = $r;
                    }
                }
            }
        }

        // If no explicit subscriptions array, build from multiselect milk types (backward-compatible)
        if (empty($subsList)) {
            $rawMilkTypes = $request->post('milk_types', []);
            $selectedTypes = [];
            if (is_array($rawMilkTypes)) {
                foreach ($rawMilkTypes as $t) {
                    $t = trim(strtolower((string)$t));
                    if (in_array($t, ['cow', 'buffalo'], true) && !in_array($t, $selectedTypes, true)) {
                        $selectedTypes[] = $t;
                    }
                }
            } elseif (is_string($rawMilkTypes) && trim($rawMilkTypes) !== '') {
                $parts = explode(',', $rawMilkTypes);
                foreach ($parts as $t) {
                    $t = trim(strtolower($t));
                    if (in_array($t, ['cow', 'buffalo'], true) && !in_array($t, $selectedTypes, true)) {
                        $selectedTypes[] = $t;
                    }
                }
            }

            if (empty($selectedTypes)) {
                Auth::flash('error', 'Please select at least one product / milk subscription with quantity.');
                Response::redirect(url('/customers/create'));
            }

            $cowProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
            $bufProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

            if (in_array('cow', $selectedTypes, true) && in_array('buffalo', $selectedTypes, true) && $cowProd && $bufProd) {
                $half = round($defaultQuantity / 2, 2);
                if ($half <= 0) $half = 1.0;
                $subsList[] = ['product_id' => (int)$cowProd['id'], 'quantity' => $half, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $subsList[] = ['product_id' => (int)$bufProd['id'], 'quantity' => $half, 'custom_rate' => (float)$bufProd['default_rate'], 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'cow,buffalo';
            } elseif (in_array('buffalo', $selectedTypes, true) && $bufProd) {
                $subsList[] = ['product_id' => (int)$bufProd['id'], 'quantity' => $defaultQuantity, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'buffalo';
            } else {
                $subsList[] = ['product_id' => (int)($cowProd['id'] ?? 1), 'quantity' => $defaultQuantity, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'cow';
            }
            $finalQty = $defaultQuantity;
        } else {
            $finalQty = $totalSubQty;
            $allActiveProds = $this->db->fetchAll("SELECT id, code FROM products WHERE tenant_id = ?", [$tenantId]);
            $prodCodeMap = array_column($allActiveProds, 'code', 'id');
            $activeCodes = [];
            foreach ($subsList as $sl) {
                if (isset($prodCodeMap[$sl['product_id']])) {
                    $activeCodes[] = $prodCodeMap[$sl['product_id']];
                }
            }
            $milkTypes = implode(',', array_unique($activeCodes));
        }

        if (empty($name) || empty($phone) || $finalQty <= 0) {
            Auth::flash('error', 'Please fill in all required fields with valid values.');
            Response::redirect(url('/customers/create'));
        }

        $this->db->execute(
            "INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$tenantId, $name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $milkTypes, $status]
        );

        $customerId = (int)$this->db->lastInsertId();
        $this->productService->saveCustomerSubscriptions($tenantId, $customerId, $subsList);

        Auth::flash('success', "Customer '{$name}' added successfully.");
        Response::redirect(url('/customers'));
    }

    public function editView(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne("SELECT * FROM customers WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$customer) {
            Auth::flash('error', 'Customer not found.');
            Response::redirect(url('/customers'));
        }

        $quota = $this->quotaService->getTenantQuota($tenantId);
        $products = $this->productService->getTenantProducts($tenantId, true);
        $subscriptions = $this->productService->getCustomerSubscriptions($tenantId, $id);

        // Fetch leaves
        $leaves = $this->db->fetchAll(
            "SELECT * FROM customer_leaves WHERE customer_id = ? AND tenant_id = ? ORDER BY start_date DESC",
            [$id, $tenantId]
        );

        View::render('customers/form', [
            'customer' => $customer,
            'quota' => $quota,
            'leaves' => $leaves,
            'products' => $products,
            'subscriptions' => $subscriptions,
            'error' => Auth::flash('error'),
            'success' => Auth::flash('success'),
        ], 'main');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne("SELECT * FROM customers WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$customer) {
            Auth::flash('error', 'Customer not found.');
            Response::redirect(url('/customers'));
        }

        $name = trim((string)$request->post('name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $address = trim((string)$request->post('address', ''));
        $routeName = trim((string)$request->post('route_name', 'Main Route'));
        $status = (string)$request->post('status', 'active');

        // Extract and process product subscriptions (Approach A)
        $rawSubs = $request->post('subscriptions');
        $subsList = [];
        $totalSubQty = 0.0;
        $primaryRate = 60.0;

        if (is_array($rawSubs)) {
            foreach ($rawSubs as $pId => $subData) {
                if (!empty($subData['is_active']) && (float)($subData['quantity'] ?? 0) > 0) {
                    $q = (float)$subData['quantity'];
                    $r = isset($subData['custom_rate']) && $subData['custom_rate'] !== '' && (float)$subData['custom_rate'] > 0 ? (float)$subData['custom_rate'] : null;
                    $sh = (string)($subData['shift'] ?? 'both');
                    $subsList[] = [
                        'product_id' => (int)$pId,
                        'quantity' => $q,
                        'custom_rate' => $r,
                        'shift' => $sh,
                        'is_active' => true,
                    ];
                    $totalSubQty += $q;
                    if ($r !== null) {
                        $primaryRate = $r;
                    }
                }
            }
        }

        // If no explicit subscriptions array, build from multiselect milk types (backward-compatible)
        if (empty($subsList)) {
            $rawMilkTypes = $request->post('milk_types', []);
            $selectedTypes = [];
            if (is_array($rawMilkTypes)) {
                foreach ($rawMilkTypes as $t) {
                    $t = trim(strtolower((string)$t));
                    if (in_array($t, ['cow', 'buffalo'], true) && !in_array($t, $selectedTypes, true)) {
                        $selectedTypes[] = $t;
                    }
                }
            } elseif (is_string($rawMilkTypes) && trim($rawMilkTypes) !== '') {
                $parts = explode(',', $rawMilkTypes);
                foreach ($parts as $t) {
                    $t = trim(strtolower($t));
                    if (in_array($t, ['cow', 'buffalo'], true) && !in_array($t, $selectedTypes, true)) {
                        $selectedTypes[] = $t;
                    }
                }
            }

            if (empty($selectedTypes)) {
                Auth::flash('error', 'Please select at least one product subscription.');
                Response::redirect(url("/customers/{$id}/edit"));
            }

            $cowProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
            $bufProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

            if (in_array('cow', $selectedTypes, true) && in_array('buffalo', $selectedTypes, true) && $cowProd && $bufProd) {
                $half = round($defaultQuantity / 2, 2);
                if ($half <= 0) $half = 1.0;
                $subsList[] = ['product_id' => (int)$cowProd['id'], 'quantity' => $half, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $subsList[] = ['product_id' => (int)$bufProd['id'], 'quantity' => $half, 'custom_rate' => (float)$bufProd['default_rate'], 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'cow,buffalo';
            } elseif (in_array('buffalo', $selectedTypes, true) && $bufProd) {
                $subsList[] = ['product_id' => (int)$bufProd['id'], 'quantity' => $defaultQuantity, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'buffalo';
            } else {
                $subsList[] = ['product_id' => (int)($cowProd['id'] ?? 1), 'quantity' => $defaultQuantity, 'custom_rate' => $ratePerUnit, 'shift' => 'both', 'is_active' => true];
                $milkTypes = 'cow';
            }
            $finalQty = $defaultQuantity;
        } else {
            $finalQty = $totalSubQty;
            $allActiveProds = $this->db->fetchAll("SELECT id, code FROM products WHERE tenant_id = ?", [$tenantId]);
            $prodCodeMap = array_column($allActiveProds, 'code', 'id');
            $activeCodes = [];
            foreach ($subsList as $sl) {
                if (isset($prodCodeMap[$sl['product_id']])) {
                    $activeCodes[] = $prodCodeMap[$sl['product_id']];
                }
            }
            $milkTypes = implode(',', array_unique($activeCodes));
        }

        // If changing status from inactive to active, check quota!
        if ($customer['status'] === 'inactive' && $status === 'active') {
            if (!$this->quotaService->canCreateCustomer($tenantId)) {
                Auth::flash('error', 'Cannot activate customer: Quota limit reached for your current subscription plan.');
                Auth::flash('paywall', 'limit_reached');
                Response::redirect(url("/customers/{$id}/edit"));
            }
        }

        $this->db->execute(
            "UPDATE customers 
             SET name = ?, phone = ?, address = ?, route_name = ?, milk_types = ?, status = ?
             WHERE id = ? AND tenant_id = ?",
            [$name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $milkTypes, $status, $id, $tenantId]
        );

        $this->productService->saveCustomerSubscriptions($tenantId, $id, $subsList);

        Auth::flash('success', "Customer '{$name}' updated.");
        Response::redirect(url('/customers'));
    }

    public function addLeave(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $customerId = (int)($params['id'] ?? 0);
        $startDate = (string)$request->post('start_date');
        $endDate = (string)$request->post('end_date');
        $reason = trim((string)$request->post('reason', 'Vacation / Pause'));

        if (empty($startDate) || empty($endDate) || $startDate > $endDate) {
            Auth::flash('error', 'Please provide a valid leave date range.');
            Response::redirect(url("/customers/{$customerId}/edit"));
        }

        $this->db->execute(
            "INSERT INTO customer_leaves (tenant_id, customer_id, start_date, end_date, reason)
             VALUES (?, ?, ?, ?, ?)",
            [$tenantId, $customerId, $startDate, $endDate, $reason]
        );

        Auth::flash('success', "Vacation schedule recorded! Deliveries between {$startDate} and {$endDate} will automatically pause.");
        Response::redirect(url("/customers/{$customerId}/edit"));
    }

    public function deleteLeave(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $customerId = (int)($params['id'] ?? 0);
        $leaveId = (int)($params['leave_id'] ?? 0);

        $this->db->execute(
            "DELETE FROM customer_leaves WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            [$leaveId, $customerId, $tenantId]
        );

        Auth::flash('success', "Leave schedule removed.");
        Response::redirect(url("/customers/{$customerId}/edit"));
    }

    public function sampleCsv(Request $request): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = "sample_customers.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Headers
        fputcsv($out, [
            'Name',
            'Phone',
            'Address',
            'Route',
            'Milk Type',
            'Default Quantity',
            'Rate Per Unit',
            'Status'
        ], ',', '"', "\\");

        // Sample rows demonstrating different options
        fputcsv($out, [
            'Aarav Sharma',
            '9822011111',
            'Flat 101, Gokul Heights, Station Road',
            'Green Valley Route',
            'cow',
            '2.00',
            '65.00',
            'active'
        ], ',', '"', "\\");

        fputcsv($out, [
            'Priya Verma',
            '9822022222',
            'B-204, Sunrise Residency, Near Temple',
            'Green Valley Route',
            'buffalo',
            '1.50',
            '75.00',
            'active'
        ], ',', '"', "\\");

        fputcsv($out, [
            'Rajesh Gupta',
            '9822033333',
            'House #14, Civil Lines, Main Market',
            'North City Route',
            'cow,buffalo',
            '2.50',
            '70.00',
            'active'
        ], ',', '"', "\\");

        fputcsv($out, [
            'Sneha Kulkarni',
            '9822044444',
            'Flat 302, Palm Grove Society',
            'Green Valley Route',
            'cow',
            '1.00',
            '60.00',
            'inactive'
        ], ',', '"', "\\");

        fclose($out);
        exit;
    }

    public function exportCsv(Request $request): void
    {
        $tenantId = Auth::tenantId();
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

        if (!empty($milkType)) {
            if ($milkType === 'both') {
                $sql .= " AND (c.milk_types LIKE '%cow%' AND c.milk_types LIKE '%buffalo%')";
            } elseif ($milkType === 'cow') {
                $sql .= " AND c.milk_types LIKE '%cow%'";
            } elseif ($milkType === 'buffalo') {
                $sql .= " AND c.milk_types LIKE '%buffalo%'";
            }
        }

        if (!empty($status)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.route_name ASC, c.name ASC";
        $customers = $this->db->fetchAll($sql, $params);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $dateStr = date('Y-m-d');
        $filename = "customers_export_{$dateStr}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'ID',
            'Name',
            'Phone',
            'Address',
            'Route',
            'Milk Types',
            'Default Quantity (L)',
            'Rate Per Unit',
            'Status',
            'Created Date'
        ], ',', '"', "\\");

        $subsSummary = $this->db->fetchAll(
            "SELECT cs.customer_id, 
                    SUM(cs.default_quantity) as total_qty,
                    COALESCE(cs.custom_rate, p.default_rate) as primary_rate
             FROM customer_subscriptions cs
             JOIN products p ON p.id = cs.product_id
             WHERE cs.tenant_id = ? AND cs.is_active = 1
             GROUP BY cs.customer_id",
            [$tenantId]
        );
        $qtyMap = array_column($subsSummary, 'total_qty', 'customer_id');
        $rateMap = array_column($subsSummary, 'primary_rate', 'customer_id');

        foreach ($customers as $c) {
            $cQty = (float)($qtyMap[$c['id']] ?? 1.0);
            $cRate = (float)($rateMap[$c['id']] ?? 60.0);
            fputcsv($out, [
                $c['id'],
                $c['name'],
                $c['phone'],
                $c['address'] ?? '',
                $c['route_name'] ?? 'Main Route',
                $c['milk_types'] ?? 'cow',
                number_format($cQty, 2, '.', ''),
                number_format($cRate, 2, '.', ''),
                $c['status'],
                substr((string)($c['created_at'] ?? ''), 0, 10)
            ], ',', '"', "\\");
        }

        fclose($out);
        exit;
    }

    public function importCsv(Request $request): void
    {
        $tenantId = Auth::tenantId();

        // 1. Verify file upload
        $file = $request->file('csv_file');
        if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'Please select a valid CSV file to upload.');
            Response::redirect(url('/customers'));
        }

        $filename = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'csv' && !in_array($file['type'] ?? '', ['text/csv', 'text/plain', 'application/vnd.ms-excel'])) {
            Auth::flash('error', 'Invalid file format. Please upload a standard .csv file.');
            Response::redirect(url('/customers'));
        }

        $updateExisting = (bool)$request->post('update_existing', false);

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Auth::flash('error', 'Unable to read the uploaded CSV file.');
            Response::redirect(url('/customers'));
        }

        // 2. Read and strip UTF-8 BOM if present
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // 3. Read Header Row
        $header = fgetcsv($handle, null, ',', '"', "\\");
        if (!$header || empty($header)) {
            fclose($handle);
            Auth::flash('error', 'Uploaded CSV file is empty.');
            Response::redirect(url('/customers'));
        }

        // Normalize header columns
        $colMap = [];
        foreach ($header as $idx => $rawCol) {
            $col = strtolower(trim((string)$rawCol));
            $clean = preg_replace('/[^a-z0-9]/', '', $col);

            if (in_array($clean, ['name', 'customername', 'fullname', 'custname'], true)) {
                $colMap['name'] = $idx;
            } elseif (in_array($clean, ['phone', 'mobile', 'phonenumber', 'mobilenumber', 'contact', 'cell'], true)) {
                $colMap['phone'] = $idx;
            } elseif (in_array($clean, ['address', 'deliveryaddress', 'street', 'location'], true)) {
                $colMap['address'] = $idx;
            } elseif (in_array($clean, ['route', 'routename', 'area', 'sector'], true)) {
                $colMap['route_name'] = $idx;
            } elseif (in_array($clean, ['milktype', 'milktypes', 'milk', 'type', 'types'], true)) {
                $colMap['milk_types'] = $idx;
            } elseif (in_array($clean, ['defaultquantity', 'quantity', 'qty', 'dailyquantity', 'liters', 'defaultquantityl'], true)) {
                $colMap['default_quantity'] = $idx;
            } elseif (in_array($clean, ['rateperunit', 'rate', 'price', 'rateperliter', 'unitrate'], true)) {
                $colMap['rate_per_unit'] = $idx;
            } elseif (in_array($clean, ['status', 'accountstatus', 'state'], true)) {
                $colMap['status'] = $idx;
            }
        }

        if (!isset($colMap['name']) || !isset($colMap['phone'])) {
            fclose($handle);
            Auth::flash('error', 'Invalid CSV headers. The CSV file must contain at least "Name" and "Phone" columns.');
            Response::redirect(url('/customers'));
        }

        // Quota check baseline
        $quota = $this->quotaService->getTenantQuota($tenantId);
        $maxAllowed = (int)$quota['max_allowed'];
        $currentActiveCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );

        $rowNum = 1;
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $skippedReasons = [];

        while (($row = fgetcsv($handle, null, ',', '"', "\\")) !== false) {
            $rowNum++;

            // Skip completely empty lines
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $name = trim((string)($row[$colMap['name']] ?? ''));
            $rawPhone = trim((string)($row[$colMap['phone']] ?? ''));
            $phone = preg_replace('/[^\d+]/', '', $rawPhone);

            if (empty($name)) {
                $skippedCount++;
                $skippedReasons[] = "Row {$rowNum}: Customer name is empty.";
                continue;
            }

            if (empty($phone) || strlen($phone) < 7) {
                $skippedCount++;
                $skippedReasons[] = "Row {$rowNum} ('{$name}'): Invalid phone number '{$rawPhone}'.";
                continue;
            }

            $address = isset($colMap['address']) ? trim((string)($row[$colMap['address']] ?? '')) : '';
            $routeName = isset($colMap['route_name']) ? trim((string)($row[$colMap['route_name']] ?? '')) : '';
            if (empty($routeName)) {
                $routeName = 'Main Route';
            }

            // Parse Milk Type
            $rawMilk = isset($colMap['milk_types']) ? strtolower(trim((string)($row[$colMap['milk_types']] ?? ''))) : 'cow';
            $hasCow = str_contains($rawMilk, 'cow');
            $hasBuffalo = str_contains($rawMilk, 'buffalo');
            $isBoth = str_contains($rawMilk, 'both') || ($hasCow && $hasBuffalo);

            if ($isBoth) {
                $milkTypes = 'cow,buffalo';
            } elseif ($hasBuffalo) {
                $milkTypes = 'buffalo';
            } else {
                $milkTypes = 'cow';
            }

            // Parse Quantity
            $rawQty = isset($colMap['default_quantity']) ? (float)str_replace(',', '.', trim((string)($row[$colMap['default_quantity']] ?? '1.0'))) : 1.0;
            $defaultQty = $rawQty > 0 ? round($rawQty, 2) : 1.00;

            // Parse Rate
            $rawRate = isset($colMap['rate_per_unit']) ? (float)str_replace(',', '.', trim((string)($row[$colMap['rate_per_unit']] ?? '60.0'))) : 60.0;
            $ratePerUnit = $rawRate > 0 ? round($rawRate, 2) : 60.00;

            // Parse Status
            $rawStatus = isset($colMap['status']) ? strtolower(trim((string)($row[$colMap['status']] ?? 'active'))) : 'active';
            $status = in_array($rawStatus, ['inactive', 'deactive', 'disabled', '0'], true) ? 'inactive' : 'active';

            // Check existing customer by phone for this tenant
            $existing = $this->db->fetchOne(
                "SELECT id, name, status FROM customers WHERE tenant_id = ? AND phone = ?",
                [$tenantId, $phone]
            );

            if ($existing) {
                if ($updateExisting) {
                    if ($existing['status'] === 'inactive' && $status === 'active') {
                        if ($currentActiveCount >= $maxAllowed) {
                            $skippedCount++;
                            $skippedReasons[] = "Row {$rowNum} ('{$name}'): Cannot activate customer, active quota limit ({$maxAllowed}) reached.";
                            continue;
                        }
                        $currentActiveCount++;
                    } elseif ($existing['status'] === 'active' && $status === 'inactive') {
                        $currentActiveCount = max(0, $currentActiveCount - 1);
                    }

                    $this->db->execute(
                        "UPDATE customers 
                         SET name = ?, address = ?, route_name = ?, milk_types = ?, status = ?
                         WHERE id = ? AND tenant_id = ?",
                        [$name, $address, $routeName, $milkTypes, $status, (int)$existing['id'], $tenantId]
                    );
                    $this->productService->syncSubscriptionsFromLegacyCustomer($tenantId, [
                        'id' => (int)$existing['id'],
                        'milk_types' => $milkTypes,
                        'default_quantity' => $defaultQty,
                        'rate_per_unit' => $ratePerUnit
                    ]);
                    $updatedCount++;
                } else {
                    $skippedCount++;
                    $skippedReasons[] = "Row {$rowNum} ('{$name}'): Phone {$phone} already exists for customer '{$existing['name']}'.";
                }
            } else {
                if ($status === 'active') {
                    if ($currentActiveCount >= $maxAllowed) {
                        $skippedCount++;
                        $skippedReasons[] = "Row {$rowNum} ('{$name}'): Skipped because active customer limit ({$maxAllowed}) reached.";
                        continue;
                    }
                    $currentActiveCount++;
                }

                $this->db->execute(
                    "INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$tenantId, $name, $phone, $address, $routeName, $milkTypes, $status]
                );
                $newCustId = (int)$this->db->lastInsertId();
                $this->productService->syncSubscriptionsFromLegacyCustomer($tenantId, [
                    'id' => $newCustId,
                    'milk_types' => $milkTypes,
                    'default_quantity' => $defaultQty,
                    'rate_per_unit' => $ratePerUnit
                ]);
                $importedCount++;
            }
        }

        fclose($handle);

        if ($importedCount > 0 || $updatedCount > 0) {
            $msg = "Import completed: {$importedCount} new customer(s) added";
            if ($updatedCount > 0) {
                $msg .= ", {$updatedCount} updated";
            }
            if ($skippedCount > 0) {
                $msg .= " ({$skippedCount} skipped)";
            }
            $msg .= ".";
            Auth::flash('success', $msg);
        } else {
            Auth::flash('error', "No customers imported. {$skippedCount} row(s) skipped.");
        }

        if (!empty($skippedReasons) && $skippedCount > 0) {
            $preview = array_slice($skippedReasons, 0, 3);
            if (count($skippedReasons) > 3) {
                $preview[] = "...and " . (count($skippedReasons) - 3) . " more.";
            }
            Auth::flash('import_skipped', implode(' | ', $preview));
        }

        Response::redirect(url('/customers'));
    }
}
