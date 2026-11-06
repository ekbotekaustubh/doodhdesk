<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Services\DeliveryService;
use App\Services\QuotaService;

final class DashboardController
{
    private Database $db;
    private QuotaService $quotaService;
    private DeliveryService $deliveryService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->quotaService = new QuotaService();
        $this->deliveryService = new DeliveryService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $today = date('Y-m-d');
        $currentHour = (int)date('H');
        $defaultShift = $currentHour >= 14 ? 'evening' : 'morning';

        // Quota info
        $quota = $this->quotaService->getTenantQuota($tenantId);

        // Run-sheet status for today
        $runSheetMorning = $this->deliveryService->getRunSheet($tenantId, $today, 'morning');
        $runSheetEvening = $this->deliveryService->getRunSheet($tenantId, $today, 'evening');

        // Deliveries volume today
        $todayLiters = $runSheetMorning['stats']['total_liters'] + $runSheetEvening['stats']['total_liters'];

        // Monthly stats for current month
        $year = (int)date('Y');
        $month = (int)date('m');
        $monthlyRevenue = (float)$this->db->fetchValue(
            "SELECT COALESCE(SUM(final_amount), 0) FROM invoices WHERE tenant_id = ? AND billing_year = ? AND billing_month = ?",
            [$tenantId, $year, $month]
        );
        $unpaidInvoicesCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM invoices WHERE tenant_id = ? AND payment_status IN ('unpaid', 'partial')",
            [$tenantId]
        );

        // Recent deliveries
        $recentDeliveries = $this->db->fetchAll(
            "SELECT dd.*, c.name AS customer_name, c.route_name
             FROM daily_deliveries dd
             JOIN customers c ON c.id = dd.customer_id
             WHERE dd.tenant_id = ?
             ORDER BY dd.delivery_date DESC, dd.updated_at DESC
             LIMIT 8",
            [$tenantId]
        );

        View::render('dashboard/index', [
            'tenant' => Auth::tenant(),
            'quota' => $quota,
            'today' => $today,
            'defaultShift' => $defaultShift,
            'runSheetMorning' => $runSheetMorning,
            'runSheetEvening' => $runSheetEvening,
            'todayLiters' => $todayLiters,
            'monthlyRevenue' => $monthlyRevenue,
            'unpaidInvoicesCount' => $unpaidInvoicesCount,
            'recentDeliveries' => $recentDeliveries,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }
}
