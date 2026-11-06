<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\QuotaService;

final class SuperAdminController
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
        $tenants = $this->db->fetchAll(
            "SELECT t.*, 
                    ts.purchased_blocks, ts.max_customers_allowed, ts.amount_paid, ts.payment_status,
                    (SELECT COUNT(*) FROM customers c WHERE c.tenant_id = t.id AND c.status = 'active') AS active_customers,
                    (SELECT COUNT(*) FROM customers c WHERE c.tenant_id = t.id) AS total_customers
             FROM tenants t
             LEFT JOIN tenant_subscriptions ts ON ts.tenant_id = t.id
             ORDER BY t.id DESC"
        );

        $systemSettings = $this->quotaService->getSystemSettings();

        // Platform KPIs
        $totalTenants = count($tenants);
        $totalActiveCustomers = 0;
        $platformRevenue = 0.0;
        foreach ($tenants as $t) {
            $totalActiveCustomers += (int)$t['active_customers'];
            $platformRevenue += (float)($t['amount_paid'] ?? 0.0);
        }

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
        $freeLimit = (int)$request->post('free_customer_limit', 5);
        $unitSize = (int)$request->post('paid_tier_unit_size', 100);
        $unitPrice = (float)$request->post('paid_tier_unit_price', 200.0);
        $currencySymbol = trim((string)$request->post('currency_symbol', '₹'));
        $platformName = trim((string)$request->post('platform_name', 'DoodhDesk SaaS'));

        $settings = [
            'free_customer_limit' => (string)$freeLimit,
            'paid_tier_unit_size' => (string)$unitSize,
            'paid_tier_unit_price' => (string)$unitPrice,
            'currency_symbol' => $currencySymbol,
            'platform_name' => $platformName,
        ];

        $stmt = $this->db->getPdo()->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        Auth::flash('success', 'Global SaaS tier parameters updated successfully.');
        Response::redirect(url('/admin'));
    }

    public function toggleTenantStatus(Request $request, array $params): void
    {
        $tenantId = (int)($params['id'] ?? 0);
        $tenant = $this->db->fetchOne("SELECT status FROM tenants WHERE id = ?", [$tenantId]);

        if ($tenant) {
            $newStatus = $tenant['status'] === 'active' ? 'suspended' : 'active';
            $this->db->execute("UPDATE tenants SET status = ? WHERE id = ?", [$newStatus, $tenantId]);
            Auth::flash('success', "Tenant status updated to {$newStatus}.");
        }

        Response::redirect(url('/admin'));
    }

    public function adjustQuota(Request $request, array $params): void
    {
        $tenantId = (int)($params['id'] ?? 0);
        $blocks = (int)$request->post('purchased_blocks', 0);

        $sys = $this->quotaService->getSystemSettings();
        $freeLimit = (int)$sys['free_customer_limit'];
        $unitSize = (int)$sys['paid_tier_unit_size'];
        $unitPrice = (float)$sys['paid_tier_unit_price'];

        $newMax = $freeLimit + ($blocks * $unitSize);
        $status = $blocks > 0 ? 'active' : 'free';

        $this->db->execute(
            "UPDATE tenant_subscriptions 
             SET purchased_blocks = ?, max_customers_allowed = ?, payment_status = ?
             WHERE tenant_id = ?",
            [$blocks, $newMax, $status, $tenantId]
        );

        Auth::flash('success', "Tenant quota adjusted to {$newMax} customers ({$blocks} paid blocks).");
        Response::redirect(url('/admin'));
    }
}
