<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\QuotaService;

final class CustomerController
{
    private Database $db;
    private QuotaService $quotaService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->quotaService = new QuotaService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $search = trim((string)$request->get('q', ''));
        $route = trim((string)$request->get('route', ''));
        $status = trim((string)$request->get('status', ''));

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

        if (!empty($status)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.status ASC, c.route_name ASC, c.name ASC";
        $customers = $this->db->fetchAll($sql, $params);

        // Fetch distinct routes
        $routes = $this->db->fetchAll(
            "SELECT DISTINCT route_name FROM customers WHERE tenant_id = ? AND route_name != '' ORDER BY route_name ASC",
            [$tenantId]
        );

        $quota = $this->quotaService->getTenantQuota($tenantId);

        View::render('customers/index', [
            'customers' => $customers,
            'routes' => array_column($routes, 'route_name'),
            'search' => $search,
            'selectedRoute' => $route,
            'selectedStatus' => $status,
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
            'paywall' => Auth::flash('paywall'),
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

        View::render('customers/form', [
            'customer' => null,
            'quota' => $quota,
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
        $defaultQuantity = (float)$request->post('default_quantity', 1.0);
        $ratePerUnit = (float)$request->post('rate_per_unit', 60.0);
        $status = (string)$request->post('status', 'active');

        if (empty($name) || empty($phone) || $defaultQuantity <= 0 || $ratePerUnit <= 0) {
            Auth::flash('error', 'Please fill in all required fields with valid values.');
            Response::redirect(url('/customers/create'));
        }

        $this->db->execute(
            "INSERT INTO customers (tenant_id, name, phone, address, route_name, default_quantity, rate_per_unit, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$tenantId, $name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $defaultQuantity, $ratePerUnit, $status]
        );

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

        // Fetch leaves
        $leaves = $this->db->fetchAll(
            "SELECT * FROM customer_leaves WHERE customer_id = ? AND tenant_id = ? ORDER BY start_date DESC",
            [$id, $tenantId]
        );

        View::render('customers/form', [
            'customer' => $customer,
            'quota' => $quota,
            'leaves' => $leaves,
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
        $defaultQuantity = (float)$request->post('default_quantity', 1.0);
        $ratePerUnit = (float)$request->post('rate_per_unit', 60.0);
        $status = (string)$request->post('status', 'active');

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
             SET name = ?, phone = ?, address = ?, route_name = ?, default_quantity = ?, rate_per_unit = ?, status = ?
             WHERE id = ? AND tenant_id = ?",
            [$name, $phone, $address, empty($routeName) ? 'Main Route' : $routeName, $defaultQuantity, $ratePerUnit, $status, $id, $tenantId]
        );

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
}
