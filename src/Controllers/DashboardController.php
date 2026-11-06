<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

final class DashboardController
{
    public function index(Request $request): void
    {
        $today = date('Y-m-d');
        $currentHour = (int)date('H');
        $defaultShift = $currentHour >= 14 ? 'evening' : 'morning';

        // 1. Fetch live operational KPIs via REST API
        $statsRes = ApiClient::get('/api/dashboard/stats');
        $stats = $statsRes['data'] ?? [];

        // 2. Fetch today's run-sheet for morning and evening shifts via REST API
        $morningRes = ApiClient::get('/api/deliveries', ['date' => $today, 'shift' => 'morning']);
        $eveningRes = ApiClient::get('/api/deliveries', ['date' => $today, 'shift' => 'evening']);

        $runSheetMorning = $morningRes['data'] ?? ['items' => [], 'stats' => ['total_liters' => 0, 'delivered' => 0, 'skipped' => 0, 'leave' => 0, 'pending' => 0]];
        $runSheetEvening = $eveningRes['data'] ?? ['items' => [], 'stats' => ['total_liters' => 0, 'delivered' => 0, 'skipped' => 0, 'leave' => 0, 'pending' => 0]];

        $todayLiters = (float)($runSheetMorning['stats']['total_liters'] ?? 0.0) + (float)($runSheetEvening['stats']['total_liters'] ?? 0.0);

        View::render('dashboard/index', [
            'tenant' => Auth::tenant(),
            'quota' => $stats['quota'] ?? [],
            'today' => $today,
            'defaultShift' => $defaultShift,
            'runSheetMorning' => $runSheetMorning,
            'runSheetEvening' => $runSheetEvening,
            'todayLiters' => $todayLiters,
            'monthlyRevenue' => (float)($stats['billing_month']['total_billed'] ?? 0.0),
            'unpaidInvoicesCount' => (int)($stats['unpaid_invoices_count'] ?? 0),
            'recentDeliveries' => $stats['recent_deliveries'] ?? [],
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }
}
