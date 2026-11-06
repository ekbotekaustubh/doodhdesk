<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ProductService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ensure standard default products exist for a tenant (Cow Milk and Buffalo Milk)
     */
    public function ensureDefaultProducts(int $tenantId): void
    {
        $count = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM products WHERE tenant_id = ?",
            [$tenantId]
        );

        if ($count === 0) {
            $this->db->execute(
                "INSERT INTO products (tenant_id, name, code, unit, default_rate, is_active, sort_order)
                 VALUES 
                 (?, 'Cow Milk', 'cow', 'L', 60.00, 1, 1),
                 (?, 'Buffalo Milk', 'buffalo', 'L', 70.00, 1, 2)",
                [$tenantId, $tenantId]
            );
        }
    }

    /**
     * Get all products for a tenant
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTenantProducts(int $tenantId, bool $activeOnly = true): array
    {
        $this->ensureDefaultProducts($tenantId);

        $sql = "SELECT * FROM products WHERE tenant_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";

        return $this->db->fetchAll($sql, [$tenantId]);
    }

    /**
     * Add or update a product for a tenant
     */
    public function createProduct(int $tenantId, string $name, string $code, string $unit, float $defaultRate, int $sortOrder = 0): int
    {
        $cleanCode = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($code)));
        if (empty($cleanCode)) {
            $cleanCode = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($name)));
        }

        $this->db->execute(
            "INSERT INTO products (tenant_id, name, code, unit, default_rate, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), unit = VALUES(unit), default_rate = VALUES(default_rate), is_active = 1, sort_order = VALUES(sort_order)",
            [$tenantId, $name, $cleanCode, $unit, $defaultRate, $sortOrder]
        );

        $existing = $this->db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = ?", [$tenantId, $cleanCode]);
        return $existing ? (int)$existing['id'] : (int)$this->db->lastInsertId();
    }

    public function toggleProduct(int $tenantId, int $productId): bool
    {
        $product = $this->db->fetchOne("SELECT id, is_active FROM products WHERE id = ? AND tenant_id = ?", [$productId, $tenantId]);
        if (!$product) {
            return false;
        }

        $newStatus = $product['is_active'] ? 0 : 1;
        $this->db->execute("UPDATE products SET is_active = ? WHERE id = ? AND tenant_id = ?", [$newStatus, $productId, $tenantId]);
        return true;
    }

    public function deleteProduct(int $tenantId, int $productId): bool
    {
        // Check if there are active subscriptions
        $subCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customer_subscriptions WHERE tenant_id = ? AND product_id = ?",
            [$tenantId, $productId]
        );

        if ($subCount > 0) {
            // Soft delete by deactivating to preserve history
            $this->db->execute("UPDATE products SET is_active = 0 WHERE id = ? AND tenant_id = ?", [$productId, $tenantId]);
            return true;
        }

        $this->db->execute("DELETE FROM products WHERE id = ? AND tenant_id = ?", [$productId, $tenantId]);
        return true;
    }

    /**
     * Get active subscriptions for a customer
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCustomerSubscriptions(int $tenantId, int $customerId): array
    {
        $this->ensureDefaultProducts($tenantId);

        $sql = "SELECT cs.*, p.name AS product_name, p.code AS product_code, p.unit, p.default_rate AS product_default_rate
                FROM customer_subscriptions cs
                JOIN products p ON p.id = cs.product_id
                WHERE cs.tenant_id = ? AND cs.customer_id = ? AND cs.is_active = 1
                ORDER BY p.sort_order ASC, p.name ASC";

        $subs = $this->db->fetchAll($sql, [$tenantId, $customerId]);

        // If no subscriptions found in table, auto-seed/sync from customer legacy columns
        if (empty($subs)) {
            $cust = $this->db->fetchOne("SELECT * FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
            if ($cust) {
                $this->syncSubscriptionsFromLegacyCustomer($tenantId, $cust);
                $subs = $this->db->fetchAll($sql, [$tenantId, $customerId]);
            }
        }

        return $subs;
    }

    /**
     * Save customer subscriptions from an array of product inputs
     *
     * @param array<int, array{product_id: int, quantity: float, custom_rate?: ?float, shift?: string, is_active?: bool}> $subscriptions
     */
    public function saveCustomerSubscriptions(int $tenantId, int $customerId, array $subscriptions): void
    {
        $this->ensureDefaultProducts($tenantId);

        $this->db->execute(
            "DELETE FROM customer_subscriptions WHERE tenant_id = ? AND customer_id = ?",
            [$tenantId, $customerId]
        );

        $activeCodes = [];
        $totalQty = 0.0;
        $primaryRate = 60.0;

        foreach ($subscriptions as $sub) {
            $productId = (int)($sub['product_id'] ?? 0);
            $qty = (float)($sub['quantity'] ?? 0.0);
            $rate = isset($sub['custom_rate']) && $sub['custom_rate'] !== '' && (float)$sub['custom_rate'] > 0 ? (float)$sub['custom_rate'] : null;
            $shift = (string)($sub['shift'] ?? 'both');
            $isActive = !empty($sub['is_active']);

            if ($productId <= 0 || $qty <= 0 || !$isActive) {
                continue;
            }

            $this->db->execute(
                "INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                [$tenantId, $customerId, $productId, $qty, $rate, $shift]
            );

            $prod = $this->db->fetchOne("SELECT code, default_rate FROM products WHERE id = ? AND tenant_id = ?", [$productId, $tenantId]);
            if ($prod) {
                $activeCodes[] = $prod['code'];
                $totalQty += $qty;
                if ($rate !== null) {
                    $primaryRate = $rate;
                } else {
                    $primaryRate = (float)$prod['default_rate'];
                }
            }
        }

        // Keep customer milk_types updated
        $milkTypesStr = !empty($activeCodes) ? implode(',', array_unique($activeCodes)) : 'cow';
        $this->db->execute(
            "UPDATE customers 
             SET milk_types = ?
             WHERE id = ? AND tenant_id = ?",
            [$milkTypesStr, $customerId, $tenantId]
        );
    }

    /**
     * Migrate/sync legacy customer columns (milk_types, default_quantity, rate_per_unit) into customer_subscriptions
     */
    public function syncSubscriptionsFromLegacyCustomer(int $tenantId, array $customer): void
    {
        $customerId = (int)$customer['id'];
        $milkTypes = explode(',', (string)($customer['milk_types'] ?? 'cow'));
        $defaultQty = (float)($customer['default_quantity'] ?? 1.0);
        $rate = (float)($customer['rate_per_unit'] ?? 60.0);

        $hasCow = in_array('cow', $milkTypes, true);
        $hasBuffalo = in_array('buffalo', $milkTypes, true);

        $cowProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
        $bufProd = $this->db->fetchOne("SELECT id, default_rate FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

        if ($hasCow && $hasBuffalo && $cowProd && $bufProd) {
            $cowQty = round($defaultQty / 2, 2);
            $bufQty = round($defaultQty - $cowQty, 2);
            if ($cowQty <= 0) $cowQty = 1.0;
            if ($bufQty <= 0) $bufQty = 1.0;

            $this->db->execute(
                "INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active)
                 VALUES (?, ?, ?, ?, ?, 'both', 1)
                 ON DUPLICATE KEY UPDATE default_quantity = VALUES(default_quantity)",
                [$tenantId, $customerId, (int)$cowProd['id'], $cowQty, $rate]
            );
            $this->db->execute(
                "INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active)
                 VALUES (?, ?, ?, ?, ?, 'both', 1)
                 ON DUPLICATE KEY UPDATE default_quantity = VALUES(default_quantity)",
                [$tenantId, $customerId, (int)$bufProd['id'], $bufQty, (float)$bufProd['default_rate']]
            );
        } elseif ($hasBuffalo && $bufProd) {
            $this->db->execute(
                "INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active)
                 VALUES (?, ?, ?, ?, ?, 'both', 1)
                 ON DUPLICATE KEY UPDATE default_quantity = VALUES(default_quantity)",
                [$tenantId, $customerId, (int)$bufProd['id'], $defaultQty, $rate]
            );
        } elseif ($cowProd) {
            $this->db->execute(
                "INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active)
                 VALUES (?, ?, ?, ?, ?, 'both', 1)
                 ON DUPLICATE KEY UPDATE default_quantity = VALUES(default_quantity)",
                [$tenantId, $customerId, (int)$cowProd['id'], $defaultQty, $rate]
            );
        }
    }
}
