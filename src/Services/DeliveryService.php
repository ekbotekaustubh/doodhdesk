<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\DeliveryShift;
use App\Enums\DeliveryStatus;

final class DeliveryService
{
    private Database $db;
    private ProductService $productService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->productService = new ProductService();
    }

    public function getRunSheet(int $tenantId, string $date, string $shift, ?string $routeFilter = null): array
    {
        // Ensure default products exist for this tenant
        $this->productService->ensureDefaultProducts($tenantId);

        // 1. Fetch all active customers for this tenant
        $sql = "SELECT c.*, 
                    cl.id AS leave_id, cl.reason AS leave_reason,
                    dd.id AS delivery_id, dd.quantity AS recorded_quantity, 
                    dd.status AS recorded_status, dd.notes AS delivery_notes,
                    dd.updated_at AS delivery_updated_at
                FROM customers c
                LEFT JOIN customer_leaves cl ON cl.customer_id = c.id 
                    AND cl.tenant_id = c.tenant_id 
                    AND ? BETWEEN cl.start_date AND cl.end_date
                LEFT JOIN daily_deliveries dd ON dd.customer_id = c.id 
                    AND dd.tenant_id = c.tenant_id 
                    AND dd.delivery_date = ? 
                    AND dd.shift = ?
                WHERE c.tenant_id = ? AND c.status = 'active'";
        
        $params = [$date, $date, $shift, $tenantId];

        if (!empty($routeFilter)) {
            $sql .= " AND c.route_name = ?";
            $params[] = $routeFilter;
        }

        $sql .= " ORDER BY c.route_name ASC, c.name ASC";

        $rows = $this->db->fetchAll($sql, $params);

        // Fetch all active subscriptions for this tenant
        $allSubs = $this->db->fetchAll(
            "SELECT cs.*, p.name AS product_name, p.code AS product_code, p.unit, p.default_rate AS product_default_rate
             FROM customer_subscriptions cs
             JOIN products p ON p.id = cs.product_id
             WHERE cs.tenant_id = ? AND cs.is_active = 1
             ORDER BY p.sort_order ASC, p.name ASC",
            [$tenantId]
        );
        $subsByCust = [];
        foreach ($allSubs as $s) {
            $subsByCust[(int)$s['customer_id']][] = $s;
        }

        // Fetch existing daily_delivery_items for this date and shift
        $allDelivItems = $this->db->fetchAll(
            "SELECT ddi.*, p.name as product_name, p.code as product_code, p.unit
             FROM daily_delivery_items ddi
             JOIN daily_deliveries dd ON dd.id = ddi.delivery_id
             JOIN products p ON p.id = ddi.product_id
             WHERE dd.tenant_id = ? AND dd.delivery_date = ? AND dd.shift = ?",
            [$tenantId, $date, $shift]
        );
        $itemsByDeliv = [];
        foreach ($allDelivItems as $di) {
            $itemsByDeliv[(int)$di['delivery_id']][] = $di;
        }

        // Process rows into normalized delivery items
        $items = [];
        $deliveredCount = 0;
        $skippedCount = 0;
        $leaveCount = 0;
        $pendingCount = 0;
        $totalLiters = 0.0;
        $productTotals = [];

        foreach ($rows as $row) {
            $cId = (int)$row['id'];
            $isOnLeave = !empty($row['leave_id']);
            $hasRecord = !empty($row['delivery_id']);

            $status = null;
            $quantity = 0.0;
            $notes = $row['delivery_notes'] ?? '';

            // Get customer subscriptions for this shift
            $custSubs = $subsByCust[$cId] ?? [];
            if (empty($custSubs)) {
                // Auto-sync if not present yet
                $this->productService->syncSubscriptionsFromLegacyCustomer($tenantId, $row);
                $custSubs = $this->productService->getCustomerSubscriptions($tenantId, $cId);
            }

            // Filter subscriptions valid for this shift
            $shiftSubs = array_filter($custSubs, function($s) use ($shift) {
                return in_array($s['shift'] ?? 'both', ['both', $shift], true);
            });
            if (empty($shiftSubs)) {
                $shiftSubs = $custSubs;
            }

            // Prepare products list for this row
            $rowProducts = [];
            if ($hasRecord) {
                $status = (string)$row['recorded_status'];
                $quantity = (float)$row['recorded_quantity'];

                $delivItems = $itemsByDeliv[(int)$row['delivery_id']] ?? [];
                if (!empty($delivItems)) {
                    foreach ($delivItems as $di) {
                        $pQty = (float)$di['quantity'];
                        $rowProducts[] = [
                            'product_id' => (int)$di['product_id'],
                            'name' => (string)$di['product_name'],
                            'code' => (string)$di['product_code'],
                            'unit' => (string)$di['unit'],
                            'quantity' => $pQty,
                            'unit_price' => (float)$di['unit_price'],
                            'status' => (string)$di['status'],
                        ];
                    }
                } else {
                    // Fallback from subscriptions
                    foreach ($shiftSubs as $sub) {
                        $pQty = ($status === DeliveryStatus::Delivered->value) ? (float)$sub['default_quantity'] : 0.0;
                        $rowProducts[] = [
                            'product_id' => (int)$sub['product_id'],
                            'name' => (string)$sub['product_name'],
                            'code' => (string)$sub['product_code'],
                            'unit' => (string)$sub['unit'],
                            'quantity' => $pQty,
                            'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                            'status' => $status,
                        ];
                    }
                }
            } elseif ($isOnLeave) {
                $status = DeliveryStatus::Leave->value;
                $quantity = 0.00;
                $notes = $row['leave_reason'] ?? 'Customer on scheduled leave';
                foreach ($shiftSubs as $sub) {
                    $rowProducts[] = [
                        'product_id' => (int)$sub['product_id'],
                        'name' => (string)$sub['product_name'],
                        'code' => (string)$sub['product_code'],
                        'unit' => (string)$sub['unit'],
                        'quantity' => 0.00,
                        'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                        'status' => 'leave',
                    ];
                }
            } else {
                $status = 'pending';
                // Sum default quantity from shift subscriptions
                $defaultSum = 0.0;
                foreach ($shiftSubs as $sub) {
                    $pQty = (float)$sub['default_quantity'];
                    $defaultSum += $pQty;
                    $rowProducts[] = [
                        'product_id' => (int)$sub['product_id'],
                        'name' => (string)$sub['product_name'],
                        'code' => (string)$sub['product_code'],
                        'unit' => (string)$sub['unit'],
                        'quantity' => $pQty,
                        'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                        'status' => 'pending',
                    ];
                }
                $quantity = $defaultSum;
            }

            // Stats
            match ($status) {
                DeliveryStatus::Delivered->value => ($deliveredCount++ && ($totalLiters += $quantity)),
                DeliveryStatus::Skipped->value => $skippedCount++,
                DeliveryStatus::Leave->value => $leaveCount++,
                default => $pendingCount++,
            };

            // Aggregate product-level delivered metrics for packing/procurement
            if ($status === DeliveryStatus::Delivered->value) {
                foreach ($rowProducts as $rp) {
                    $pName = $rp['name'];
                    if (!isset($productTotals[$pName])) {
                        $productTotals[$pName] = ['quantity' => 0.0, 'unit' => $rp['unit']];
                    }
                    $productTotals[$pName]['quantity'] += (float)$rp['quantity'];
                }
            }

            $items[] = [
                'customer_id' => $cId,
                'customer_name' => (string)$row['name'],
                'phone' => (string)$row['phone'],
                'address' => (string)($row['address'] ?? ''),
                'route_name' => (string)($row['route_name'] ?? 'Main Route'),
                'milk_types' => (string)($row['milk_types'] ?? 'cow'),
                'is_on_leave' => $isOnLeave,
                'leave_reason' => $row['leave_reason'] ?? null,
                'delivery_id' => $row['delivery_id'] ? (int)$row['delivery_id'] : null,
                'status' => $status,
                'quantity' => $quantity,
                'notes' => $notes,
                'products' => $rowProducts,
                'updated_at' => $row['delivery_updated_at'] ?? null,
            ];
        }

        // Available routes for filter dropdown
        $routes = $this->db->fetchAll(
            "SELECT DISTINCT route_name FROM customers WHERE tenant_id = ? AND route_name IS NOT NULL AND route_name != '' ORDER BY route_name ASC",
            [$tenantId]
        );
        $routeList = array_column($routes, 'route_name');

        return [
            'date' => $date,
            'shift' => $shift,
            'items' => $items,
            'routes' => $routeList,
            'stats' => [
                'total_houses' => count($items),
                'delivered' => $deliveredCount,
                'skipped' => $skippedCount,
                'leave' => $leaveCount,
                'pending' => $pendingCount,
                'total_liters' => round($totalLiters, 2),
                'product_totals' => $productTotals,
            ],
            'pending_alert' => $pendingCount > 0 ? "{$pendingCount} houses are still pending verification for this " . ($shift === 'morning' ? 'Morning' : 'Evening') . " shift!" : null,
        ];
    }

    public function recordDelivery(
        int $tenantId,
        int $customerId,
        string $date,
        string $shift,
        string $status,
        ?float $quantity = null,
        ?string $notes = null,
        ?array $productItems = null
    ): array {
        $this->productService->ensureDefaultProducts($tenantId);

        // Fetch customer subscriptions
        $subs = $this->productService->getCustomerSubscriptions($tenantId, $customerId);

        // Enforce Zero-Charge Rule: Missed or paused days are strictly logged with 0 quantity billed
        $isZeroCharge = ($status === DeliveryStatus::Skipped->value || $status === DeliveryStatus::Leave->value);

        $lineItemsToSave = [];
        $finalTotalQty = 0.0;

        if ($isZeroCharge) {
            $finalTotalQty = 0.00;
            foreach ($subs as $sub) {
                $lineItemsToSave[] = [
                    'product_id' => (int)$sub['product_id'],
                    'quantity' => 0.00,
                    'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                    'status' => $status,
                    'notes' => $notes,
                ];
            }
        } elseif (!empty($productItems)) {
            // Explicit product items passed (e.g. from multi-product adjustment)
            foreach ($subs as $sub) {
                $pId = (int)$sub['product_id'];
                $pRate = (float)($sub['custom_rate'] ?? $sub['product_default_rate']);

                $pQty = isset($productItems[$pId]) ? (float)$productItems[$pId] : (float)$sub['default_quantity'];
                $pQty = max(0.0, round($pQty, 2));
                $pStatus = $pQty > 0 ? DeliveryStatus::Delivered->value : DeliveryStatus::Skipped->value;

                $finalTotalQty += $pQty;
                $lineItemsToSave[] = [
                    'product_id' => $pId,
                    'quantity' => $pQty,
                    'unit_price' => $pRate,
                    'status' => $pStatus,
                    'notes' => $notes,
                ];
            }
        } else {
            // Delivered with either explicit total quantity or default subscriptions
            if ($quantity !== null && $quantity >= 0) {
                $finalTotalQty = round($quantity, 2);
                // Distribute or assign to subscriptions
                $defaultTotal = array_sum(array_column($subs, 'default_quantity'));
                foreach ($subs as $sub) {
                    $defaultSub = (float)$sub['default_quantity'];
                    $ratio = $defaultTotal > 0 ? ($defaultSub / $defaultTotal) : (1.0 / max(1, count($subs)));
                    $pQty = round($finalTotalQty * $ratio, 2);
                    $lineItemsToSave[] = [
                        'product_id' => (int)$sub['product_id'],
                        'quantity' => $pQty,
                        'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                        'status' => $pQty > 0 ? DeliveryStatus::Delivered->value : DeliveryStatus::Skipped->value,
                        'notes' => $notes,
                    ];
                }
            } else {
                foreach ($subs as $sub) {
                    $pQty = (float)$sub['default_quantity'];
                    $finalTotalQty += $pQty;
                    $lineItemsToSave[] = [
                        'product_id' => (int)$sub['product_id'],
                        'quantity' => $pQty,
                        'unit_price' => (float)($sub['custom_rate'] ?? $sub['product_default_rate']),
                        'status' => DeliveryStatus::Delivered->value,
                        'notes' => $notes,
                    ];
                }
            }
        }

        // 1. Insert or update daily_deliveries header
        $sql = "INSERT INTO daily_deliveries (tenant_id, customer_id, delivery_date, shift, quantity, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    quantity = VALUES(quantity),
                    status = VALUES(status),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP";

        $this->db->execute($sql, [
            $tenantId,
            $customerId,
            $date,
            $shift,
            $finalTotalQty,
            $status,
            $notes
        ]);

        $deliveryId = (int)$this->db->fetchValue(
            "SELECT id FROM daily_deliveries WHERE tenant_id = ? AND customer_id = ? AND delivery_date = ? AND shift = ?",
            [$tenantId, $customerId, $date, $shift]
        );

        // 2. Insert or update daily_delivery_items
        if ($deliveryId > 0 && !empty($lineItemsToSave)) {
            foreach ($lineItemsToSave as $item) {
                $this->db->execute(
                    "INSERT INTO daily_delivery_items (tenant_id, delivery_id, product_id, quantity, unit_price, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                        quantity = VALUES(quantity),
                        unit_price = VALUES(unit_price),
                        status = VALUES(status),
                        notes = VALUES(notes)",
                    [
                        $tenantId,
                        $deliveryId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['status'],
                        $item['notes']
                    ]
                );
            }
        }

        // Return updated row state
        $delivery = $this->db->fetchOne(
            "SELECT dd.*, c.name AS customer_name, c.route_name, c.milk_types
             FROM daily_deliveries dd
             JOIN customers c ON c.id = dd.customer_id
             WHERE dd.tenant_id = ? AND dd.customer_id = ? AND dd.delivery_date = ? AND dd.shift = ?",
            [$tenantId, $customerId, $date, $shift]
        );

        return $delivery ?: [];
    }

    public function markAllPendingAsDelivered(int $tenantId, string $date, string $shift): int
    {
        $pendingCustomers = $this->db->fetchAll(
            "SELECT c.id 
             FROM customers c
             LEFT JOIN customer_leaves cl ON cl.customer_id = c.id 
                AND cl.tenant_id = c.tenant_id 
                AND ? BETWEEN cl.start_date AND cl.end_date
             LEFT JOIN daily_deliveries dd ON dd.customer_id = c.id 
                AND dd.tenant_id = c.tenant_id 
                AND dd.delivery_date = ? 
                AND dd.shift = ?
             WHERE c.tenant_id = ? AND c.status = 'active' AND cl.id IS NULL AND dd.id IS NULL",
            [$date, $date, $shift, $tenantId]
        );

        $count = 0;
        foreach ($pendingCustomers as $pc) {
            $this->recordDelivery(
                $tenantId,
                (int)$pc['id'],
                $date,
                $shift,
                DeliveryStatus::Delivered->value,
                null,
                'Bulk confirmed delivered'
            );
            $count++;
        }

        return $count;
    }
}
