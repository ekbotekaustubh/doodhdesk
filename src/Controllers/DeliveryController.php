<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Enums\DeliveryShift;
use App\Enums\DeliveryStatus;
use App\Services\DeliveryService;

final class DeliveryController
{
    private DeliveryService $deliveryService;

    public function __construct()
    {
        $this->deliveryService = new DeliveryService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $date = (string)$request->get('date', date('Y-m-d'));
        $shift = (string)$request->get('shift', (int)date('H') >= 14 ? 'evening' : 'morning');
        $route = (string)$request->get('route', '');

        // Validate shift
        if (!in_array($shift, ['morning', 'evening'])) {
            $shift = 'morning';
        }

        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift, empty($route) ? null : $route);

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
        $tenantId = Auth::tenantId();
        $customerId = (int)$request->post('customer_id');
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');
        $status = (string)$request->post('status', 'delivered');
        $quantity = $request->post('quantity') !== null ? (float)$request->post('quantity') : null;
        $notes = (string)$request->post('notes', '');

        // Enforce Zero-charge rule: Missed or paused days strictly logged with 0 quantity billed
        if ($status === DeliveryStatus::Skipped->value || $status === DeliveryStatus::Leave->value) {
            $quantity = 0.00;
        }

        $delivery = $this->deliveryService->recordDelivery(
            $tenantId,
            $customerId,
            $date,
            $shift,
            $status,
            $quantity,
            $notes
        );

        // Fetch refreshed single customer run sheet item for HTMX row replacement
        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);
        $item = null;
        foreach ($runSheet['items'] as $it) {
            if ($it['customer_id'] === $customerId) {
                $item = $it;
                break;
            }
        }

        if ($request->isHtmx()) {
            // Render row partial
            echo View::renderPartial('deliveries/_row', [
                'item' => $item,
                'date' => $date,
                'shift' => $shift,
            ]);
            exit;
        }

        if ($request->isAjax()) {
            Response::json([
                'success' => true,
                'delivery' => $delivery,
                'stats' => $runSheet['stats'],
                'pending_alert' => $runSheet['pending_alert'],
            ]);
        }

        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function editQuantity(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $customerId = (int)$request->post('customer_id');
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');
        $notes = trim((string)$request->post('notes', ''));

        // Handle multi-product quantities if passed
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

        // If total quantity is 0, mark as skipped
        $status = $totalQty > 0 ? DeliveryStatus::Delivered->value : DeliveryStatus::Skipped->value;

        $delivery = $this->deliveryService->recordDelivery(
            $tenantId,
            $customerId,
            $date,
            $shift,
            $status,
            $totalQty,
            empty($notes) ? "Adjusted qty to {$totalQty}L" : $notes,
            $productItems
        );

        if ($request->isHtmx()) {
            $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);
            $item = null;
            foreach ($runSheet['items'] as $it) {
                if ($it['customer_id'] === $customerId) {
                    $item = $it;
                    break;
                }
            }
            echo View::renderPartial('deliveries/_row', [
                'item' => $item,
                'date' => $date,
                'shift' => $shift,
            ]);
            exit;
        }

        if ($request->isAjax()) {
            Response::json(['success' => true, 'delivery' => $delivery]);
        }

        Auth::flash('success', "Delivery quantity updated to {$quantity}L");
        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function markAllDelivered(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $date = (string)$request->post('date', date('Y-m-d'));
        $shift = (string)$request->post('shift', 'morning');

        $count = $this->deliveryService->markAllPendingAsDelivered($tenantId, $date, $shift);

        Auth::flash('success', "Quick Action: {$count} pending houses were confirmed as delivered!");
        Response::redirect(url("/deliveries?date={$date}&shift={$shift}"));
    }

    public function getStatsJson(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $date = (string)$request->get('date', date('Y-m-d'));
        $shift = (string)$request->get('shift', 'morning');

        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);
        Response::json([
            'stats' => $runSheet['stats'],
            'pending_alert' => $runSheet['pending_alert']
        ]);
    }
}
