<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\DeliveryShift;
use App\Enums\DeliveryStatus;

final class DeliveryService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getRunSheet(int $tenantId, string $date, string $shift, ?string $routeFilter = null): array
    {
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

        // Process rows into normalized delivery items
        $items = [];
        $deliveredCount = 0;
        $skippedCount = 0;
        $leaveCount = 0;
        $pendingCount = 0;
        $totalLiters = 0.0;

        foreach ($rows as $row) {
            $isOnLeave = !empty($row['leave_id']);
            $hasRecord = !empty($row['delivery_id']);

            $status = null;
            $quantity = 0.0;
            $notes = $row['delivery_notes'] ?? '';

            if ($hasRecord) {
                $status = (string)$row['recorded_status'];
                $quantity = (float)$row['recorded_quantity'];
            } elseif ($isOnLeave) {
                // Auto-detected leave
                $status = DeliveryStatus::Leave->value;
                $quantity = 0.00; // Zero-charge rule
                $notes = $row['leave_reason'] ?? 'Customer on scheduled leave';
            } else {
                $status = 'pending';
                $quantity = (float)$row['default_quantity'];
            }

            // Stats
            match ($status) {
                DeliveryStatus::Delivered->value => ($deliveredCount++ && ($totalLiters += $quantity)),
                DeliveryStatus::Skipped->value => $skippedCount++,
                DeliveryStatus::Leave->value => $leaveCount++,
                default => $pendingCount++,
            };

            $items[] = [
                'customer_id' => (int)$row['id'],
                'customer_name' => (string)$row['name'],
                'phone' => (string)$row['phone'],
                'address' => (string)($row['address'] ?? ''),
                'route_name' => (string)($row['route_name'] ?? 'Main Route'),
                'default_quantity' => (float)$row['default_quantity'],
                'rate_per_unit' => (float)$row['rate_per_unit'],
                'is_on_leave' => $isOnLeave,
                'leave_reason' => $row['leave_reason'] ?? null,
                'delivery_id' => $row['delivery_id'] ? (int)$row['delivery_id'] : null,
                'status' => $status,
                'quantity' => $quantity,
                'notes' => $notes,
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
        ?string $notes = null
    ): array {
        // Enforce Zero-Charge Rule: Missed or paused days are strictly logged with 0 quantity billed
        if ($status === DeliveryStatus::Skipped->value || $status === DeliveryStatus::Leave->value) {
            $finalQty = 0.00;
        } else {
            if ($quantity !== null && $quantity >= 0) {
                $finalQty = round($quantity, 2);
            } else {
                // Fetch default quantity from customer profile
                $cust = $this->db->fetchOne("SELECT default_quantity FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
                $finalQty = $cust ? (float)$cust['default_quantity'] : 1.00;
            }
        }

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
            $finalQty,
            $status,
            $notes
        ]);

        // Return updated row state
        $delivery = $this->db->fetchOne(
            "SELECT dd.*, c.name AS customer_name, c.default_quantity, c.rate_per_unit, c.route_name
             FROM daily_deliveries dd
             JOIN customers c ON c.id = dd.customer_id
             WHERE dd.tenant_id = ? AND dd.customer_id = ? AND dd.delivery_date = ? AND dd.shift = ?",
            [$tenantId, $customerId, $date, $shift]
        );

        return $delivery ?: [];
    }

    public function markAllPendingAsDelivered(int $tenantId, string $date, string $shift): int
    {
        // Find all active customers who have no delivery record and are not on leave
        $pendingCustomers = $this->db->fetchAll(
            "SELECT c.id, c.default_quantity 
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
                (float)$pc['default_quantity'],
                'Bulk confirmed delivered'
            );
            $count++;
        }

        return $count;
    }
}
