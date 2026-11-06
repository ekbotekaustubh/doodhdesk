<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\QuotaService;

final class AdminApiController extends ApiBaseController
{
    private QuotaService $quotaService;

    public function __construct()
    {
        parent::__construct();
        $this->quotaService = new QuotaService();
    }

    public function stats(Request $request): void
    {
        $tenantStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_tenants,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active_tenants,
                COALESCE(SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END), 0) as suspended_tenants
             FROM tenants"
        );

        $totalCustomers = (int)$this->db->fetchValue("SELECT COUNT(*) FROM customers");
        $activeCustomers = (int)$this->db->fetchValue("SELECT COUNT(*) FROM customers WHERE status = 'active'");
        $totalInvoices = (int)$this->db->fetchValue("SELECT COUNT(*) FROM invoices");
        $totalRevenueBilled = (float)$this->db->fetchValue("SELECT COALESCE(SUM(final_amount), 0) FROM invoices");

        $this->jsonSuccess([
            'tenants' => [
                'total' => (int)($tenantStats['total_tenants'] ?? 0),
                'active' => (int)($tenantStats['active_tenants'] ?? 0),
                'suspended' => (int)($tenantStats['suspended_tenants'] ?? 0),
            ],
            'customers' => [
                'total' => $totalCustomers,
                'active' => $activeCustomers,
            ],
            'platform_volume' => [
                'total_invoices' => $totalInvoices,
                'total_billed_amount' => round($totalRevenueBilled, 2),
            ]
        ]);
    }

    public function getSettings(Request $request): void
    {
        $settings = $this->quotaService->getPlatformSettings();
        $this->jsonSuccess($settings);
    }

    public function updateSettings(Request $request): void
    {
        $freeLimit = (int)$request->input('free_customer_limit', 5);
        $unitSize = (int)$request->input('paid_tier_unit_size', 100);
        $unitPrice = (float)$request->input('paid_tier_unit_price', 200.0);
        $currencySymbol = trim((string)$request->input('currency_symbol', '₹'));
        $platformName = trim((string)$request->input('platform_name', 'DoodhDesk SaaS'));
        $supportEmail = trim((string)$request->input('support_email', 'support@doodhdesk.com'));
        $supportPhone = trim((string)$request->input('support_phone', ''));

        $settings = [
            'free_customer_limit' => (string)$freeLimit,
            'paid_tier_unit_size' => (string)$unitSize,
            'paid_tier_unit_price' => (string)$unitPrice,
            'currency_symbol' => $currencySymbol,
            'platform_name' => $platformName,
            'support_email' => $supportEmail,
            'support_phone' => $supportPhone,
        ];

        $stmt = $this->db->getPdo()->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        $fresh = $this->quotaService->getPlatformSettings();
        $this->jsonSuccess($fresh, 'Platform settings updated successfully');
    }

    public function getTenants(Request $request): void
    {
        $search = trim((string)$request->get('q', ''));
        $status = trim((string)$request->get('status', ''));

        $sql = "SELECT t.id, t.business_name, t.owner_name, t.phone, t.email, t.status, t.created_at,
                       ts.purchased_blocks, ts.max_customers_allowed, ts.amount_paid, ts.payment_status,
                       (SELECT COUNT(*) FROM customers c WHERE c.tenant_id = t.id AND c.status = 'active') as active_customers,
                       (SELECT COUNT(*) FROM customers c WHERE c.tenant_id = t.id) as total_customers
                FROM tenants t
                LEFT JOIN tenant_subscriptions ts ON ts.tenant_id = t.id
                WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (t.business_name LIKE ? OR t.owner_name LIKE ? OR t.phone LIKE ? OR t.email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if (!empty($status)) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY t.created_at DESC";
        $tenants = $this->db->fetchAll($sql, $params);

        foreach ($tenants as &$t) {
            $t['id'] = (int)$t['id'];
            $t['active_customers'] = (int)$t['active_customers'];
            $t['total_customers'] = (int)$t['total_customers'];
            $t['purchased_blocks'] = (int)($t['purchased_blocks'] ?? 0);
            $t['max_customers_allowed'] = (int)($t['max_customers_allowed'] ?? 5);
            $t['amount_paid'] = (float)($t['amount_paid'] ?? 0.0);
            $t['remaining_slots'] = max(0, $t['max_customers_allowed'] - $t['active_customers']);
        }

        $this->jsonSuccess($tenants, 'Tenants retrieved', 200, ['total_count' => count($tenants)]);
    }

    public function toggleTenantStatus(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $tenant = $this->db->fetchOne("SELECT id, business_name, status FROM tenants WHERE id = ?", [$id]);

        if (!$tenant) {
            $this->jsonError('Tenant not found.', 404, 'NOT_FOUND');
        }

        $newStatus = $tenant['status'] === 'active' ? 'suspended' : 'active';
        $this->db->execute("UPDATE tenants SET status = ? WHERE id = ?", [$newStatus, $id]);

        $this->jsonSuccess([
            'id' => $id,
            'business_name' => $tenant['business_name'],
            'status' => $newStatus,
        ], "Tenant account {$newStatus}.");
    }

    public function adjustQuota(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $purchasedBlocks = (int)$request->input('purchased_blocks', 0);

        $tenant = $this->db->fetchOne("SELECT id, business_name FROM tenants WHERE id = ?", [$id]);
        if (!$tenant) {
            $this->jsonError('Tenant not found.', 404, 'NOT_FOUND');
        }

        $settings = $this->quotaService->getPlatformSettings();
        $freeLimit = (int)$settings['free_customer_limit'];
        $unitSize = (int)$settings['paid_tier_unit_size'];
        $newAllowed = $freeLimit + ($purchasedBlocks * $unitSize);

        $this->db->execute(
            "INSERT INTO tenant_subscriptions (tenant_id, purchased_blocks, max_customers_allowed, payment_status)
             VALUES (?, ?, ?, 'active')
             ON DUPLICATE KEY UPDATE 
                purchased_blocks = VALUES(purchased_blocks),
                max_customers_allowed = VALUES(max_customers_allowed)",
            [$id, $purchasedBlocks, $newAllowed]
        );

        $quota = $this->quotaService->getTenantQuota($id);

        $this->jsonSuccess($quota, "Quota updated for {$tenant['business_name']}.");
    }
}
