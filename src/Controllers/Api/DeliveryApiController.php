<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Enums\DeliveryShift;
use App\Enums\DeliveryStatus;
use App\Services\DeliveryService;

final class DeliveryApiController extends ApiBaseController
{
    private DeliveryService $deliveryService;

    public function __construct()
    {
        parent::__construct();
        $this->deliveryService = new DeliveryService();
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $date = (string)$request->get('date', date('Y-m-d'));
        $shift = (string)$request->get('shift', (int)date('H') >= 14 ? 'evening' : 'morning');
        $route = (string)$request->get('route', '');

        if (!in_array($shift, ['morning', 'evening'], true)) {
            $shift = 'morning';
        }

        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift, empty($route) ? null : $route);

        $this->jsonSuccess($runSheet);
    }

    public function toggle(Request $request): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)$request->input('customer_id', 0);
        $date = (string)$request->input('date', date('Y-m-d'));
        $shift = (string)$request->input('shift', 'morning');
        $status = (string)$request->input('status', 'delivered');
        $notes = (string)$request->input('notes', '');

        $errors = $this->validate([
            'customer_id' => $customerId,
            'date' => $date,
            'shift' => $shift,
            'status' => $status,
        ], [
            'customer_id' => 'required|numeric',
            'date' => 'required',
            'shift' => 'required',
            'status' => 'required',
        ]);

        if (!in_array($shift, ['morning', 'evening'], true)) {
            $errors['shift'] = 'Shift must be either morning or evening.';
        }

        if (!in_array($status, ['delivered', 'skipped', 'leave'], true)) {
            $errors['status'] = 'Status must be delivered, skipped, or leave.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        // Enforce Zero-charge rule: Missed or paused days strictly logged with 0 quantity billed
        $quantity = null;
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

        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);

        $this->jsonSuccess([
            'delivery' => $delivery,
            'stats' => $runSheet['stats'],
            'pending_alert' => $runSheet['pending_alert'],
        ], "Delivery status updated to {$status}");
    }

    public function editQuantity(Request $request): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)$request->input('customer_id', 0);
        $date = (string)$request->input('date', date('Y-m-d'));
        $shift = (string)$request->input('shift', 'morning');
        $status = (string)$request->input('status', 'delivered');
        $notes = (string)$request->input('notes', '');

        // Support either scalar total quantity or itemized productItems array
        $productItemsInput = $request->input('items', null);
        $quantity = $request->input('quantity') !== null ? (float)$request->input('quantity') : null;

        $errors = $this->validate([
            'customer_id' => $customerId,
            'date' => $date,
            'shift' => $shift,
        ], [
            'customer_id' => 'required|numeric',
            'date' => 'required',
            'shift' => 'required',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $productItems = null;
        if (is_array($productItemsInput) && !empty($productItemsInput)) {
            $productItems = [];
            foreach ($productItemsInput as $pi) {
                $pid = (int)($pi['product_id'] ?? 0);
                $pQty = (float)($pi['quantity'] ?? 0.0);
                $pPrice = isset($pi['unit_price']) ? (float)$pi['unit_price'] : 0.0;
                $pStatus = (string)($pi['status'] ?? $status);
                $pNotes = (string)($pi['notes'] ?? $notes);

                if ($pid > 0) {
                    $productItems[] = [
                        'product_id' => $pid,
                        'quantity' => $pStatus === 'delivered' ? max(0.0, $pQty) : 0.0,
                        'unit_price' => $pPrice,
                        'status' => $pStatus,
                        'notes' => $pNotes,
                    ];
                }
            }
        }

        $delivery = $this->deliveryService->recordDelivery(
            $tenantId,
            $customerId,
            $date,
            $shift,
            $status,
            $quantity,
            $notes,
            $productItems
        );

        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);

        $this->jsonSuccess([
            'delivery' => $delivery,
            'stats' => $runSheet['stats'],
        ], 'Delivery quantity updated successfully');
    }

    public function bulkDeliver(Request $request): void
    {
        $tenantId = $this->tenantId();
        $date = (string)$request->input('date', date('Y-m-d'));
        $shift = (string)$request->input('shift', 'morning');

        if (!in_array($shift, ['morning', 'evening'], true)) {
            $this->jsonError('Invalid shift parameter', 422, 'VALIDATION_ERROR');
        }

        $count = $this->deliveryService->markAllPendingAsDelivered($tenantId, $date, $shift);
        $runSheet = $this->deliveryService->getRunSheet($tenantId, $date, $shift);

        $this->jsonSuccess([
            'updated_count' => $count,
            'stats' => $runSheet['stats'],
        ], "Marked {$count} pending delivery(ies) as delivered");
    }

    public function summary(Request $request): void
    {
        $tenantId = $this->tenantId();
        $date = (string)$request->get('date', date('Y-m-d'));

        $morningStats = $this->deliveryService->getShiftStats($tenantId, $date, 'morning');
        $eveningStats = $this->deliveryService->getShiftStats($tenantId, $date, 'evening');

        $this->jsonSuccess([
            'date' => $date,
            'morning' => $morningStats,
            'evening' => $eveningStats,
            'total_liters_today' => round(((float)$morningStats['total_liters']) + ((float)$eveningStats['total_liters']), 2),
        ]);
    }
}
