<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class DeliveryController
{
    public function index(Request $request): void
    {
        $date = (string)$request->get('date', date('Y-m-d'));
        $shift = (string)$request->get('shift', (int)date('H') >= 14 ? 'evening' : 'morning');
        $route = (string)$request->get('route', '');

        if (!in_array($shift, ['morning', 'evening'], true)) {
            $shift = 'morning';
        }

        // 1. Fetch daily delivery run-sheet via REST API
        $res = ApiClient::get('/api/deliveries', [
            'date' => $date,
            'shift' => $shift,
            'route' => $route,
        ]);

        $runSheet = $res['data'] ?? [
            'items' => [],
            'stats' => ['total_liters' => 0.0, 'delivered' => 0, 'skipped' => 0, 'leave' => 0, 'pending' => 0, 'product_totals' => []],
            'routes' => [],
            'pending_alert' => null,
        ];

        View::render('deliveries/index', [
            'date' => $date,
            'shift' => $shift,
            'selectedRoute' => $route,
            'runSheet' => $runSheet,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function toggle(Request $request): void
    {
        $customerId = (int)$request->post('customer_id');
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');
        $status = (string)$request->post('status', 'delivered');
        $quantity = $request->post('quantity') !== null ? (float)$request->post('quantity') : null;
        $notes = (string)$request->post('notes', '');

        // 1. Execute toggle proof-of-delivery via REST API
        $apiRes = ApiClient::post('/api/deliveries/toggle', [
            'customer_id' => $customerId,
            'delivery_date' => $date,
            'shift' => $shift,
            'status' => $status,
            'quantity' => $quantity,
            'notes' => $notes,
        ]);

        // 2. Fetch updated run-sheet item for single-row HTMX swap
        $runSheetRes = ApiClient::get('/api/deliveries', [
            'date' => $date,
            'shift' => $shift,
        ]);
        $runSheet = $runSheetRes['data'] ?? ['items' => []];

        $targetItem = null;
        foreach (($runSheet['items'] ?? []) as $it) {
            if ((int)$it['customer_id'] === $customerId) {
                $targetItem = $it;
                break;
            }
        }

        if ($request->isHtmx()) {
            echo View::renderPartial('deliveries/_row', [
                'item' => $targetItem,
                'date' => $date,
                'shift' => $shift,
            ]);
            exit;
        }

        if ($request->isAjax()) {
            Response::json([
                'success' => $apiRes['success'],
                'delivery' => $apiRes['data'],
                'stats' => $runSheet['stats'] ?? [],
                'pending_alert' => $runSheet['pending_alert'] ?? null,
            ]);
        }

        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function editQuantity(Request $request): void
    {
        $customerId = (int)$request->post('customer_id');
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');
        $notes = trim((string)$request->post('notes', ''));

        $rawProductItems = $request->post('product_items');
        $productItems = null;
        $totalQty = 0.0;

        if (is_array($rawProductItems)) {
            $productItems = [];
            foreach ($rawProductItems as $pId => $pQty) {
                $qVal = max(0.0, (float)$pQty);
                $productItems[(int)$pId] = $qVal;
                $totalQty += $qVal;
            }
        } else {
            $totalQty = (float)$request->post('quantity', 1.0);
        }

        // 1. Execute volume adjustment via REST API
        $apiRes = ApiClient::post('/api/deliveries/edit-quantity', [
            'customer_id' => $customerId,
            'delivery_date' => $date,
            'shift' => $shift,
            'quantity' => $totalQty,
            'product_items' => $productItems,
            'notes' => empty($notes) ? "Adjusted qty to {$totalQty}L" : $notes,
        ]);

        // 2. Fetch updated item for single-row HTMX swap
        $runSheetRes = ApiClient::get('/api/deliveries', [
            'date' => $date,
            'shift' => $shift,
        ]);
        $runSheet = $runSheetRes['data'] ?? ['items' => []];

        $targetItem = null;
        foreach (($runSheet['items'] ?? []) as $it) {
            if ((int)$it['customer_id'] === $customerId) {
                $targetItem = $it;
                break;
            }
        }

        if ($request->isHtmx()) {
            echo View::renderPartial('deliveries/_row', [
                'item' => $targetItem,
                'date' => $date,
                'shift' => $shift,
            ]);
            exit;
        }

        if ($request->isAjax()) {
            Response::json(['success' => $apiRes['success'], 'delivery' => $apiRes['data']]);
        }

        Auth::flash('success', "Delivery quantity updated to {$totalQty}L");
        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function markAllDelivered(Request $request): void
    {
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');
        $route = (string)$request->post('route_name', '');

        // 1. Bulk confirm deliveries via REST API
        $apiRes = ApiClient::post('/api/deliveries/bulk-deliver', [
            'date' => $date,
            'shift' => $shift,
            'route_name' => $route,
        ]);

        $count = $apiRes['data']['updated_count'] ?? 0;
        Auth::flash('success', "Quick Action: {$count} pending houses were confirmed as delivered!");
        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function getStatsJson(Request $request): void
    {
        $date = (string)$request->get('date', date('Y-m-d'));
        $shift = (string)$request->get('shift', 'morning');

        // Fetch shift metrics via REST API
        $res = ApiClient::get('/api/deliveries/summary', [
            'date' => $date,
            'shift' => $shift,
        ]);

        Response::json([
            'stats' => $res['data']['stats'] ?? [],
            'pending_alert' => $res['data']['pending_alert'] ?? null,
        ]);
    }
}
