<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class QuotaService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getSystemSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
        $settings = [
            'free_customer_limit' => 5,
            'paid_tier_unit_size' => 100,
            'paid_tier_unit_price' => 200.00,
            'currency_symbol' => '₹',
            'platform_name' => 'DoodhDesk SaaS'
        ];
        foreach ($rows as $r) {
            $key = $r['setting_key'];
            $val = $r['setting_value'];
            if (in_array($key, ['free_customer_limit', 'paid_tier_unit_size'])) {
                $settings[$key] = (int)$val;
            } elseif ($key === 'paid_tier_unit_price') {
                $settings[$key] = (float)$val;
            } else {
                $settings[$key] = $val;
            }
        }
        return $settings;
    }

    public function getTenantQuota(int $tenantId): array
    {
        $sys = $this->getSystemSettings();
        $freeLimit = (int)$sys['free_customer_limit'];
        $unitSize = (int)$sys['paid_tier_unit_size'];
        $unitPrice = (float)$sys['paid_tier_unit_price'];

        // Get tenant subscription
        $sub = $this->db->fetchOne(
            "SELECT * FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1",
            [$tenantId]
        );

        $purchasedBlocks = $sub ? (int)$sub['purchased_blocks'] : 0;
        $paymentStatus = $sub ? (string)$sub['payment_status'] : 'free';
        $validUntil = $sub['valid_until'] ?? null;

        // Max allowed customers
        $maxAllowed = $freeLimit + ($purchasedBlocks * $unitSize);

        // Count current active customers
        $activeCustomers = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );

        $totalCustomers = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customers WHERE tenant_id = ?",
            [$tenantId]
        );

        $canAdd = $activeCustomers < $maxAllowed;
        $usagePercent = $maxAllowed > 0 ? min(100, round(($activeCustomers / $maxAllowed) * 100, 1)) : 100;

        return [
            'tenant_id' => $tenantId,
            'active_customers' => $activeCustomers,
            'total_customers' => $totalCustomers,
            'max_allowed' => $maxAllowed,
            'free_limit' => $freeLimit,
            'purchased_blocks' => $purchasedBlocks,
            'unit_size' => $unitSize,
            'unit_price' => $unitPrice,
            'currency_symbol' => $sys['currency_symbol'],
            'payment_status' => $paymentStatus,
            'valid_until' => $validUntil,
            'can_add' => $canAdd,
            'remaining_slots' => max(0, $maxAllowed - $activeCustomers),
            'usage_percent' => $usagePercent,
            'is_near_limit' => ($maxAllowed - $activeCustomers) <= 1,
            'is_at_limit' => $activeCustomers >= $maxAllowed,
        ];
    }

    public function canCreateCustomer(int $tenantId): bool
    {
        $quota = $this->getTenantQuota($tenantId);
        return $quota['can_add'];
    }

    public function upgradeCapacity(int $tenantId, int $additionalBlocks): array
    {
        $sys = $this->getSystemSettings();
        $unitSize = (int)$sys['paid_tier_unit_size'];
        $unitPrice = (float)$sys['paid_tier_unit_price'];

        $sub = $this->db->fetchOne("SELECT * FROM tenant_subscriptions WHERE tenant_id = ?", [$tenantId]);
        $currentBlocks = $sub ? (int)$sub['purchased_blocks'] : 0;
        $newBlocks = $currentBlocks + $additionalBlocks;
        $freeLimit = (int)$sys['free_customer_limit'];
        $newMax = $freeLimit + ($newBlocks * $unitSize);
        $totalCost = $additionalBlocks * $unitPrice;
        $validUntil = date('Y-m-d', strtotime('+1 year'));

        if ($sub) {
            $this->db->execute(
                "UPDATE tenant_subscriptions 
                 SET purchased_blocks = ?, max_customers_allowed = ?, amount_paid = amount_paid + ?, valid_until = ?, payment_status = 'active'
                 WHERE tenant_id = ?",
                [$newBlocks, $newMax, $totalCost, $validUntil, $tenantId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO tenant_subscriptions (tenant_id, purchased_blocks, max_customers_allowed, amount_paid, valid_until, payment_status)
                 VALUES (?, ?, ?, ?, ?, 'active')",
                [$tenantId, $newBlocks, $newMax, $totalCost, $validUntil]
            );
        }

        return $this->getTenantQuota($tenantId);
    }
}
