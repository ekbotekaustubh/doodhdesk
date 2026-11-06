<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\DeliveryService;
use App\Services\QuotaService;

final class DashboardApiController extends ApiBaseController
{
    private DeliveryService $deliveryService;
    private QuotaService $quotaService;

    public function __construct()
    {
        parent::__construct();
        $this->deliveryService = new DeliveryService();
        $this->quotaService = new QuotaService();
    }

    public function stats(Request $request): void
    {
        $tenantId = $this->tenantId();
        $today = date('Y-m-d');
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');

        $morningStats = $this->deliveryService->getShiftStats($tenantId, $today, 'morning');
        $eveningStats = $this->deliveryService->getShiftStats($tenantId, $today, 'evening');
        $quota = $this->quotaService->getTenantQuota($tenantId);

        // Month-to-date invoicing & collection
        $invoiceStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(final_amount), 0) as total_billed,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN final_amount ELSE 0 END), 0) as total_paid
             FROM invoices
             WHERE tenant_id = ? AND billing_month = ? AND billing_year = ?",
            [$tenantId, $currentMonth, $currentYear]
        );

        $paymentsThisMonth = (float)$this->db->fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE tenant_id = ? AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?",
            [$tenantId, $currentMonth, $currentYear]
        );

        $unpaidInvoicesCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM invoices WHERE tenant_id = ? AND payment_status IN ('unpaid', 'partial')",
            [$tenantId]
        );

        $recentDeliveries = $this->db->fetchAll(
            "SELECT dd.*, c.name AS customer_name, c.route_name
             FROM daily_deliveries dd
             JOIN customers c ON c.id = dd.customer_id
             WHERE dd.tenant_id = ?
             ORDER BY dd.delivery_date DESC, dd.updated_at DESC
             LIMIT 8",
            [$tenantId]
        );

        $this->jsonSuccess([
            'date' => $today,
            'shifts' => [
                'morning' => $morningStats,
                'evening' => $eveningStats,
            ],
            'quota' => $quota,
            'billing_month' => [
                'month' => $currentMonth,
                'year' => $currentYear,
                'invoices_generated' => (int)($invoiceStats['total_invoices'] ?? 0),
                'total_billed' => (float)($invoiceStats['total_billed'] ?? 0.0),
                'total_collected' => round($paymentsThisMonth, 2),
                'pending_balance' => max(0.0, round(((float)($invoiceStats['total_billed'] ?? 0.0)) - $paymentsThisMonth, 2)),
                'unpaid_invoices_count' => $unpaidInvoicesCount,
            ],
            'unpaid_invoices_count' => $unpaidInvoicesCount,
            'recent_deliveries' => $recentDeliveries,
        ]);
    }
}
