<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Enums\DeliveryShift;
use App\Enums\DeliveryStatus;
use App\Services\BillingService;
use App\Services\DeliveryService;
use App\Services\QuotaService;

echo "========================================================\n";
echo "   DAIRYFLOW MULTI-TENANT SAAS AUTOMATED TEST SUITE    \n";
echo "========================================================\n\n";

$db = Database::getInstance();
$deliveryService = new DeliveryService();
$billingService = new BillingService();
$quotaService = new QuotaService();

$passed = 0;
$failed = 0;

function runTest(string $name, callable $testFn, int &$passed, int &$failed): void
{
    echo "• Running: {$name}... ";
    try {
        $testFn();
        echo "[\033[32mPASS\033[0m]\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "[\033[31mFAIL\033[0m]\n";
        echo "  Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

// TEST 1: Tenant Data Isolation
runTest("Tenancy Data Isolation (Tenant 1 vs Tenant 2)", function() use ($db) {
    // Tenant 1 has 6 customers, Tenant 2 has 4 customers
    $t1Customers = $db->fetchAll("SELECT id FROM customers WHERE tenant_id = 1");
    $t2Customers = $db->fetchAll("SELECT id FROM customers WHERE tenant_id = 2");

    if (count($t1Customers) === 0 || count($t2Customers) === 0) {
        throw new \Exception("Customers missing for tenants");
    }

    $t1Ids = array_column($t1Customers, 'id');
    $t2Ids = array_column($t2Customers, 'id');

    // No overlap in customer IDs
    $overlap = array_intersect($t1Ids, $t2Ids);
    if (!empty($overlap)) {
        throw new \Exception("Data isolation breach: Customer IDs overlap between tenants!");
    }

    // Invoices isolation
    $t1Invoices = $db->fetchAll("SELECT id FROM invoices WHERE tenant_id = 1");
    $t2Invoices = $db->fetchAll("SELECT id FROM invoices WHERE tenant_id = 2");
    $invOverlap = array_intersect(array_column($t1Invoices, 'id'), array_column($t2Invoices, 'id'));
    if (!empty($invOverlap)) {
        throw new \Exception("Data isolation breach: Invoices overlap between tenants!");
    }
}, $passed, $failed);

// TEST 2: Core Anti-Error Engine & Zero-Charge Rule
runTest("Zero-Charge Rule: Missed & Leave Days strictly record 0.00L", function() use ($deliveryService) {
    $today = date('Y-m-d');
    
    // Mark delivered with 3.5L
    $del = $deliveryService->recordDelivery(1, 1, $today, 'morning', DeliveryStatus::Delivered->value, 3.5);
    if ((float)$del['quantity'] !== 3.5) {
        throw new \Exception("Expected delivered qty 3.5, got " . $del['quantity']);
    }

    // Mark skipped (even if quantity is provided, must be forced to 0.00)
    $skip = $deliveryService->recordDelivery(1, 1, $today, 'morning', DeliveryStatus::Skipped->value, 3.5);
    if ((float)$skip['quantity'] !== 0.0) {
        throw new \Exception("Zero-charge rule violated on Skipped! Expected 0.00, got " . $skip['quantity']);
    }

    // Mark leave (must be forced to 0.00)
    $leave = $deliveryService->recordDelivery(1, 1, $today, 'morning', DeliveryStatus::Leave->value, 2.0);
    if ((float)$leave['quantity'] !== 0.0) {
        throw new \Exception("Zero-charge rule violated on Leave! Expected 0.00, got " . $leave['quantity']);
    }

    // Restore to default delivered
    $deliveryService->recordDelivery(1, 1, $today, 'morning', DeliveryStatus::Delivered->value, 2.0);
}, $passed, $failed);

// TEST 3: Scheduled Leave Auto-Detection
runTest("Vacation / Leave Auto-Detection in Daily Run-Sheet", function() use ($deliveryService, $db) {
    // Customer 2 (Priya Verma) has a scheduled leave in customer_leaves
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $db->execute("INSERT INTO customer_leaves (tenant_id, customer_id, start_date, end_date, reason) VALUES (1, 2, ?, ?, 'Out of Town') ON DUPLICATE KEY UPDATE reason = VALUES(reason)", [$today, $tomorrow]);

    $runSheet = $deliveryService->getRunSheet(1, $today, 'morning');
    $found = false;
    foreach ($runSheet['items'] as $item) {
        if ($item['customer_id'] === 2) {
            $found = true;
            if (!$item['is_on_leave']) {
                throw new \Exception("Customer 2 should be detected as is_on_leave = true!");
            }
            break;
        }
    }
    if (!$found) {
        throw new \Exception("Customer 2 not found in run sheet");
    }
}, $passed, $failed);

// TEST 4: Customer Quota Guard & Paywall
runTest("Customer Quota Guard: Free Tier 5-Limit Enforcement", function() use ($quotaService, $db) {
    // Reset Tenant 2 to free tier
    $db->execute("UPDATE tenant_subscriptions SET purchased_blocks = 0, max_customers_allowed = 5, payment_status = 'free' WHERE tenant_id = 2");

    $quota = $quotaService->getTenantQuota(2);
    if ($quota['max_allowed'] !== 5) {
        throw new \Exception("Expected max_allowed = 5 on free tier, got " . $quota['max_allowed']);
    }
    if ($quota['active_customers'] !== 4) {
        throw new \Exception("Expected 4 active customers, got " . $quota['active_customers']);
    }
    if (!$quota['can_add']) {
        throw new \Exception("Tenant 2 should be able to add 1 more customer (4/5)");
    }

    // Add 5th customer to fill quota
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, default_quantity, rate_per_unit, status) VALUES (2, 'Fifth Test Customer', '9999900005', 'Test Address', 1.0, 60.0, 'active')");

    $quotaFull = $quotaService->getTenantQuota(2);
    if ($quotaFull['active_customers'] !== 5 || $quotaFull['can_add'] !== false) {
        throw new \Exception("Quota guard failed! Customer slots should be full (5/5), can_add should be false");
    }

    // Test Upgrade Simulation: purchase 1 block (+100 slots)
    $upgraded = $quotaService->upgradeCapacity(2, 1);
    if ($upgraded['max_allowed'] !== 105 || $upgraded['can_add'] !== true || $upgraded['payment_status'] !== 'active') {
        throw new \Exception("Upgrade capacity failed! Expected 105 max, active payment status.");
    }

    // Clean up test customer and reset
    $db->execute("DELETE FROM customers WHERE tenant_id = 2 AND phone = '9999900005'");
    $db->execute("UPDATE tenant_subscriptions SET purchased_blocks = 0, max_customers_allowed = 5, payment_status = 'free' WHERE tenant_id = 2");
}, $passed, $failed);

// TEST 5: Exact 4-Step Invoice Billing Engine Formula
runTest("Billing Engine 4-Step Invoice Calculation Formula", function() use ($billingService) {
    $now = new DateTime('now');
    $prevMonthDate = (clone $now)->modify('-1 month');
    $py = (int)$prevMonthDate->format('Y');
    $pm = (int)$prevMonthDate->format('m');

    $bill = $billingService->calculateCustomerBill(1, 1, $py, $pm);

    // Formula Step 1: Base Milk Subtotal = sum(Delivered Liters * Customer Rate)
    $expectedSubtotal = round($bill['total_units'] * $bill['customer_rate'], 2);
    if (abs($bill['milk_subtotal'] - $expectedSubtotal) > 0.01) {
        throw new \Exception("Step 1 failed! Milk subtotal mismatch");
    }

    // Formula Step 2: Custom Surcharges sum
    $chargesSum = 0.0;
    foreach ($bill['charges'] as $c) {
        $chargesSum += $c['amount'];
    }
    if (abs($bill['additional_charges_total'] - round($chargesSum, 2)) > 0.01) {
        throw new \Exception("Step 2 failed! Additional charges sum mismatch");
    }

    // Formula Step 3: Tax Amount = (Base Subtotal + Taxable Charges) * (Tax% / 100)
    $taxableBase = $bill['milk_subtotal'];
    foreach ($bill['charges'] as $c) {
        if ($c['is_taxable']) {
            $taxableBase += $c['amount'];
        }
    }
    $expectedTax = round($taxableBase * ($bill['tax_percentage'] / 100), 2);
    if (abs($bill['tax_amount'] - $expectedTax) > 0.01) {
        throw new \Exception("Step 3 failed! Tax amount mismatch");
    }

    // Formula Step 4: Total Payable = Base + Charges + Tax + Prev Balance - Payments
    $expectedFinal = round($bill['milk_subtotal'] + $bill['additional_charges_total'] + $bill['tax_amount'] + $bill['previous_balance'] - $bill['payments_received'], 2);
    if (abs($bill['final_amount'] - $expectedFinal) > 0.01) {
        throw new \Exception("Step 4 failed! Final payable amount mismatch");
    }
}, $passed, $failed);

// TEST 6: Super Admin Global SaaS Configuration
runTest("Super Admin Global Platform Settings (system_settings)", function() use ($quotaService, $db) {
    $sys = $quotaService->getSystemSettings();
    if (!isset($sys['free_customer_limit'], $sys['paid_tier_unit_size'], $sys['paid_tier_unit_price'])) {
        throw new \Exception("System settings missing core tier parameters!");
    }
}, $passed, $failed);

echo "\n========================================================\n";
echo "   TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
