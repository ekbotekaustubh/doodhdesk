<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class SuperAdminController
{
    public function index(Request $request): void
    {
        // 1. Fetch tenants roster via REST API
        $tenantsRes = ApiClient::get('/api/admin/tenants');
        $tenants = $tenantsRes['data'] ?? [];

        // 2. Fetch global system settings via REST API
        $settingsRes = ApiClient::get('/api/admin/settings');
        $systemSettings = $settingsRes['data'] ?? [];

        // 3. Fetch aggregated stats via REST API
        $statsRes = ApiClient::get('/api/admin/stats');
        $stats = $statsRes['data'] ?? [];

        $totalTenants = $stats['total_tenants'] ?? count($tenants);
        $totalActiveCustomers = $stats['total_customers'] ?? 0;
        $platformRevenue = (float)($stats['total_platform_revenue'] ?? 0.0);

        View::render('superadmin/index', [
            'admin' => Auth::admin(),
            'tenants' => $tenants,
            'systemSettings' => $systemSettings,
            'totalTenants' => $totalTenants,
            'totalActiveCustomers' => $totalActiveCustomers,
            'platformRevenue' => $platformRevenue,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'admin');
    }

    public function updateSettings(Request $request): void
    {
        $payload = [
            'free_customer_limit' => (int)$request->post('free_customer_limit', 5),
            'paid_tier_unit_size' => (int)$request->post('paid_tier_unit_size', 100),
            'paid_tier_unit_price' => (float)$request->post('paid_tier_unit_price', 200.0),
            'currency_symbol' => trim((string)$request->post('currency_symbol', '₹')),
            'platform_name' => trim((string)$request->post('platform_name', 'DoodhDesk SaaS')),
        ];

        // Update settings via REST API
        $res = ApiClient::put('/api/admin/settings', $payload);

        if ($res['success']) {
            Auth::flash('success', 'Global SaaS tier parameters updated successfully.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to update system settings.');
        }

        Response::redirect(url('/admin'));
    }

    public function toggleTenantStatus(Request $request, array $params): void
    {
        $tenantId = (int)($params['id'] ?? 0);

        // Toggle tenant status via REST API
        $res = ApiClient::post("/api/admin/tenants/{$tenantId}/toggle-status");

        if ($res['success']) {
            $newStatus = $res['data']['status'] ?? 'updated';
            Auth::flash('success', "Tenant status updated to {$newStatus}.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to update tenant status.');
        }

        Response::redirect(url('/admin'));
    }

    public function adjustQuota(Request $request, array $params): void
    {
        $tenantId = (int)($params['id'] ?? 0);
        $blocks = (int)$request->post('purchased_blocks', 0);

        // Adjust quota via REST API
        $res = ApiClient::post("/api/admin/tenants/{$tenantId}/quota", [
            'purchased_blocks' => $blocks,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Customer capacity quota override applied for tenant #{$tenantId}.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to adjust customer quota.');
        }

        Response::redirect(url('/admin'));
    }
}
