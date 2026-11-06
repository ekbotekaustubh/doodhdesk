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

// TEST 7: Customer Milk Type Multiselect (Cow, Buffalo, and Both)
runTest("Customer Milk Type Multiselect: Cow Milk, Buffalo Milk, and Both", function() use ($db) {
    // 1. Verify Enum
    if (\App\Enums\MilkType::Cow->value !== 'cow' || \App\Enums\MilkType::Buffalo->value !== 'buffalo') {
        throw new \Exception("MilkType enum values mismatch");
    }

    // 2. Insert customer with Cow milk only
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (1, 'Test Cow Only', '9999911111', 'Block A', 'Test Route', 'cow', 1.0, 60.0, 'active')");
    $c1 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9999911111'");
    if (!$c1 || $c1['milk_types'] !== 'cow') {
        throw new \Exception("Expected milk_types 'cow', got: " . ($c1['milk_types'] ?? 'null'));
    }
    $types1 = parseMilkTypes($c1['milk_types']);
    if ($types1 !== ['cow']) {
        throw new \Exception("parseMilkTypes mismatch for cow: " . json_encode($types1));
    }

    // 3. Insert customer with Buffalo milk only
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (1, 'Test Buffalo Only', '9999922222', 'Block B', 'Test Route', 'buffalo', 1.5, 75.0, 'active')");
    $c2 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9999922222'");
    if (!$c2 || $c2['milk_types'] !== 'buffalo') {
        throw new \Exception("Expected milk_types 'buffalo', got: " . ($c2['milk_types'] ?? 'null'));
    }
    $types2 = parseMilkTypes($c2['milk_types']);
    if ($types2 !== ['buffalo']) {
        throw new \Exception("parseMilkTypes mismatch for buffalo: " . json_encode($types2));
    }

    // 4. Insert customer with BOTH Cow and Buffalo (Multiselect)
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (1, 'Test Both Milks', '9999933333', 'Block C', 'Test Route', 'cow,buffalo', 2.0, 70.0, 'active')");
    $c3 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9999933333'");
    if (!$c3 || $c3['milk_types'] !== 'cow,buffalo') {
        throw new \Exception("Expected milk_types 'cow,buffalo', got: " . ($c3['milk_types'] ?? 'null'));
    }
    $types3 = parseMilkTypes($c3['milk_types']);
    if (count($types3) !== 2 || !in_array('cow', $types3) || !in_array('buffalo', $types3)) {
        throw new \Exception("parseMilkTypes mismatch for both: " . json_encode($types3));
    }

    // 5. Update customer from single milk to both milks
    $db->execute("UPDATE customers SET milk_types = ? WHERE id = ?", ['cow,buffalo', $c1['id']]);
    $c1Updated = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$c1['id']]);
    if ($c1Updated['milk_types'] !== 'cow,buffalo') {
        throw new \Exception("Updating customer milk types failed!");
    }

    // Clean up test rows
    $db->execute("DELETE FROM customers WHERE phone IN ('9999911111', '9999922222', '9999933333')");
}, $passed, $failed);

// TEST 8: Delivery Calendar Matrix & Milk Type on Invoices
runTest("Delivery Calendar Matrix & Milk Type on Invoices", function() use ($db, $billingService) {
    // 1. Check helper milk text formatting
    if (formatMilkTypeText('cow') !== 'Cow Milk') {
        throw new \Exception("formatMilkTypeText('cow') failed");
    }
    if (formatMilkTypeText('buffalo') !== 'Buffalo Milk') {
        throw new \Exception("formatMilkTypeText('buffalo') failed");
    }
    if (formatMilkTypeText('cow,buffalo') !== 'Cow Milk & Buffalo Milk') {
        throw new \Exception("formatMilkTypeText('cow,buffalo') failed: " . formatMilkTypeText('cow,buffalo'));
    }

    // 2. Test buildCalendarMatrix for October (31 days)
    $sampleItemized = [
        ['date' => '2026-10-01', 'shift' => 'morning', 'status' => 'delivered', 'quantity' => 2.0, 'notes' => ''],
        ['date' => '2026-10-02', 'shift' => 'morning', 'status' => 'skipped', 'quantity' => 0.0, 'notes' => 'Door locked'],
        ['date' => '2026-10-03', 'shift' => 'morning', 'status' => 'leave', 'quantity' => 0.0, 'notes' => 'Out of town'],
        ['date' => '2026-10-04', 'shift' => 'morning', 'status' => 'delivered', 'quantity' => 1.5, 'notes' => ''],
        ['date' => '2026-10-04', 'shift' => 'evening', 'status' => 'delivered', 'quantity' => 1.0, 'notes' => ''],
    ];

    $matrixData = buildCalendarMatrix([
        'itemized_deliveries' => $sampleItemized,
        'days_in_month' => 31,
    ], 2026, 10);

    if ($matrixData['days_in_month'] !== 31) {
        throw new \Exception("Expected 31 days in October, got " . $matrixData['days_in_month']);
    }
    if (count($matrixData['matrix']) !== 31) {
        throw new \Exception("Expected 31 matrix cells, got " . count($matrixData['matrix']));
    }

    // Oct 1 was Thursday (day_of_week = 4)
    if ($matrixData['first_day_of_week'] !== 4) {
        throw new \Exception("Expected first day of week 4 (Thu), got " . $matrixData['first_day_of_week']);
    }

    // Day 1: Delivered 2.0L
    $day1 = $matrixData['matrix'][1];
    if ($day1['status'] !== 'delivered' || (float)$day1['quantity'] !== 2.0) {
        throw new \Exception("Day 1 mismatch: status={$day1['status']}, qty={$day1['quantity']}");
    }

    // Day 2: Skipped 0.0L
    $day2 = $matrixData['matrix'][2];
    if ($day2['status'] !== 'skipped' || (float)$day2['quantity'] !== 0.0) {
        throw new \Exception("Day 2 mismatch: status={$day2['status']}, qty={$day2['quantity']}");
    }

    // Day 3: Leave 0.0L
    $day3 = $matrixData['matrix'][3];
    if ($day3['status'] !== 'leave' || (float)$day3['quantity'] !== 0.0) {
        throw new \Exception("Day 3 mismatch: status={$day3['status']}, qty={$day3['quantity']}");
    }

    // Day 4: Morning 1.5L + Evening 1.0L = 2.5L delivered
    $day4 = $matrixData['matrix'][4];
    if ($day4['status'] !== 'delivered' || (float)$day4['quantity'] !== 2.5) {
        throw new \Exception("Day 4 multi-shift mismatch: status={$day4['status']}, qty={$day4['quantity']}");
    }

    // 3. Test invoice query joins customer milk_types
    $inv = $db->fetchOne(
        "SELECT inv.*, c.name AS customer_name, c.phone AS customer_phone, c.route_name, c.milk_types
         FROM invoices inv
         JOIN customers c ON c.id = inv.customer_id
         WHERE inv.tenant_id = 1
         LIMIT 1"
    );
    if (!$inv) {
        throw new \Exception("No invoices found for tenant 1");
    }
    if (!isset($inv['milk_types'])) {
        throw new \Exception("Invoice query does not contain milk_types field!");
    }

    // 4. Test BillingService calculation contains calendar_matrix
    $bill = $billingService->calculateCustomerBill(1, 1, 2026, 10);
    if (empty($bill['calendar_matrix']) || count($bill['calendar_matrix']) !== 31) {
        throw new \Exception("BillingService bill missing 31-day calendar_matrix");
    }
}, $passed, $failed);

// TEST 9: Customer CSV Import, Export, and Sample Template
runTest("Customer CSV Import, Export, and Sample Template", function() use ($db) {
    // 1. Verify Sample CSV generation
    $sampleFile = tempnam(sys_get_temp_dir(), "sample_csv_");
    $fp = fopen($sampleFile, 'w');
    fputcsv($fp, ['Name', 'Phone', 'Address', 'Route', 'Milk Type', 'Default Quantity', 'Rate Per Unit', 'Status'], ',', '"', "\\");
    fputcsv($fp, ['Import Test 1', '9666611111', 'Flat 1', 'Route A', 'cow', '1.5', '60.0', 'active'], ',', '"', "\\");
    fputcsv($fp, ['Import Test 2', '9666622222', 'Flat 2', 'Route B', 'buffalo', '2.0', '70.0', 'active'], ',', '"', "\\");
    fputcsv($fp, ['Import Test 3', '9666633333', 'Flat 3', 'Route A', 'cow,buffalo', '2.5', '65.0', 'active'], ',', '"', "\\");
    fclose($fp);

    // 2. Test manual CSV parsing & ingestion logic
    $handle = fopen($sampleFile, 'r');
    $header = fgetcsv($handle, null, ',', '"', "\\");
    if (!in_array('Name', $header) || !in_array('Phone', $header) || !in_array('Milk Type', $header)) {
        throw new \Exception("Sample CSV missing critical headers");
    }

    $imported = 0;
    while (($row = fgetcsv($handle, null, ',', '"', "\\")) !== false) {
        $name = $row[0];
        $phone = $row[1];
        $addr = $row[2];
        $route = $row[3];
        $milk = $row[4];
        $qty = (float)$row[5];
        $rate = (float)$row[6];
        $status = $row[7];

        $db->execute(
            "INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$name, $phone, $addr, $route, $milk, $qty, $rate, $status]
        );
        $imported++;
    }
    fclose($handle);
    @unlink($sampleFile);

    if ($imported !== 3) {
        throw new \Exception("Expected 3 imported customers, got {$imported}");
    }

    // Verify imported data in DB
    $c1 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9666611111'");
    if (!$c1 || $c1['milk_types'] !== 'cow' || (float)$c1['default_quantity'] !== 1.5) {
        throw new \Exception("Imported customer 1 mismatch");
    }

    $c2 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9666622222'");
    if (!$c2 || $c2['milk_types'] !== 'buffalo' || (float)$c2['default_quantity'] !== 2.0) {
        throw new \Exception("Imported customer 2 mismatch");
    }

    $c3 = $db->fetchOne("SELECT * FROM customers WHERE phone = '9666633333'");
    if (!$c3 || $c3['milk_types'] !== 'cow,buffalo' || (float)$c3['default_quantity'] !== 2.5) {
        throw new \Exception("Imported customer 3 mismatch");
    }

    // 3. Test Export query
    $exportedRows = $db->fetchAll("SELECT * FROM customers WHERE tenant_id = 1 AND phone LIKE '96666%'");
    if (count($exportedRows) !== 3) {
        throw new \Exception("Export rows count mismatch: expected 3, got " . count($exportedRows));
    }

    // Cleanup
    $db->execute("DELETE FROM customers WHERE phone LIKE '96666%'");
}, $passed, $failed);

// TEST 10: Multi-Product Catalog Management (Approach A)
runTest("Product Catalog: Dynamic Tenant Products with Units & Rates", function() use ($db) {
    $productService = new \App\Services\ProductService();
    $tenantId = 1;

    // 1. Ensure default products exist (Cow Milk & Buffalo Milk)
    $productService->ensureDefaultProducts($tenantId);
    $products = $productService->getTenantProducts($tenantId);
    if (count($products) < 2) {
        throw new \Exception("Default products missing");
    }

    // 2. Create custom product: Fresh Paneer in kg
    $pId = $productService->createProduct($tenantId, 'Fresh Paneer', 'paneer', 'kg', 360.00);
    if ($pId <= 0) {
        throw new \Exception("Failed to create product");
    }

    $paneer = $db->fetchOne("SELECT * FROM products WHERE id = ? AND tenant_id = ?", [$pId, $tenantId]);
    if (!$paneer || $paneer['unit'] !== 'kg' || (float)$paneer['default_rate'] !== 360.00) {
        throw new \Exception("Product data mismatch for Paneer");
    }
}, $passed, $failed);

// TEST 11: Customer Multi-Product Subscriptions (Both Milks & New Products)
runTest("Customer Subscriptions: Independent Quantities & Rates for Both Milks", function() use ($db) {
    $productService = new \App\Services\ProductService();
    $tenantId = 1;

    // Create a customer
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (?, 'Dual Milk Test Cust', '9888800001', 'Flat 100', 'Test Route', 'cow,buffalo', 2.5, 60.0, 'active')",
                  [$tenantId]);
    $custId = (int)$db->lastInsertId();

    $cowProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
    $bufProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

    // Save independent subscriptions: Cow 1.5L @ 60, Buffalo 1.0L @ 75
    $subscriptions = [
        ['product_id' => (int)$cowProd['id'], 'quantity' => 1.5, 'custom_rate' => 60.0, 'shift' => 'both', 'is_active' => true],
        ['product_id' => (int)$bufProd['id'], 'quantity' => 1.0, 'custom_rate' => 75.0, 'shift' => 'both', 'is_active' => true],
    ];
    $productService->saveCustomerSubscriptions($tenantId, $custId, $subscriptions);

    // Verify stored subscriptions
    $stored = $productService->getCustomerSubscriptions($tenantId, $custId);
    if (count($stored) !== 2) {
        throw new \Exception("Expected 2 subscriptions, got " . count($stored));
    }

    $cowSub = null;
    $bufSub = null;
    foreach ($stored as $s) {
        if ($s['product_code'] === 'cow') $cowSub = $s;
        if ($s['product_code'] === 'buffalo') $bufSub = $s;
    }

    if (!$cowSub || (float)$cowSub['default_quantity'] !== 1.5 || (float)$cowSub['custom_rate'] !== 60.0) {
        throw new \Exception("Cow subscription mismatch");
    }
    if (!$bufSub || (float)$bufSub['default_quantity'] !== 1.0 || (float)$bufSub['custom_rate'] !== 75.0) {
        throw new \Exception("Buffalo subscription mismatch");
    }

    // Verify total default quantity in customer row updated to 2.5L
    $cust = $db->fetchOne("SELECT default_quantity FROM customers WHERE id = ?", [$custId]);
    if ((float)$cust['default_quantity'] !== 2.5) {
        throw new \Exception("Customer default_quantity was not synced to sum (2.5)");
    }

    // Clean up
    $db->execute("DELETE FROM customers WHERE id = ?", [$custId]);
}, $passed, $failed);

// TEST 12: Daily Delivery Itemized Tracking & Zero-Charge Rule
runTest("Daily Delivery Run-Sheet: Itemized Product Tracking & Zero-Charge Rule", function() use ($db, $deliveryService) {
    $tenantId = 1;
    $productService = new \App\Services\ProductService();

    // Create a customer
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (?, 'RunSheet Item Test Cust', '9888800002', 'Flat 200', 'Test Route', 'cow,buffalo', 2.0, 60.0, 'active')",
                  [$tenantId]);
    $custId = (int)$db->lastInsertId();

    $cowProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
    $bufProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

    $subscriptions = [
        ['product_id' => (int)$cowProd['id'], 'quantity' => 1.5, 'custom_rate' => 60.0, 'shift' => 'both', 'is_active' => true],
        ['product_id' => (int)$bufProd['id'], 'quantity' => 0.5, 'custom_rate' => 70.0, 'shift' => 'both', 'is_active' => true],
    ];
    $productService->saveCustomerSubscriptions($tenantId, $custId, $subscriptions);

    $date = date('Y-m-d');

    // 1. Deliver default: should log 1.5L cow and 0.5L buffalo
    $deliveryService->recordDelivery($tenantId, $custId, $date, 'morning', 'delivered');
    $del = $db->fetchOne("SELECT id, quantity FROM daily_deliveries WHERE tenant_id = ? AND customer_id = ? AND delivery_date = ? AND shift = 'morning'", [$tenantId, $custId, $date]);
    if (!$del || (float)$del['quantity'] !== 2.0) {
        throw new \Exception("Expected total delivery qty 2.0, got " . ($del['quantity'] ?? 'null'));
    }

    $items = $db->fetchAll("SELECT * FROM daily_delivery_items WHERE delivery_id = ? ORDER BY product_id ASC", [$del['id']]);
    if (count($items) !== 2) {
        throw new \Exception("Expected 2 daily_delivery_items, got " . count($items));
    }

    // 2. Custom adjustment: Customer asks for extra 0.5L Buffalo milk today (1.5L Cow, 1.0L Buffalo = 2.5L total)
    $deliveryService->recordDelivery($tenantId, $custId, $date, 'morning', 'delivered', null, 'Extra buffalo milk', [
        (int)$cowProd['id'] => 1.5,
        (int)$bufProd['id'] => 1.0,
    ]);

    $delUpdated = $db->fetchOne("SELECT id, quantity FROM daily_deliveries WHERE id = ?", [$del['id']]);
    if ((float)$delUpdated['quantity'] !== 2.5) {
        throw new \Exception("Adjusted delivery total mismatch: expected 2.5, got " . $delUpdated['quantity']);
    }

    // 3. Zero-Charge Rule on skip: both items must become 0.00
    $deliveryService->recordDelivery($tenantId, $custId, $date, 'morning', 'skipped');
    $skipItems = $db->fetchAll("SELECT * FROM daily_delivery_items WHERE delivery_id = ?", [$del['id']]);
    foreach ($skipItems as $si) {
        if ((float)$si['quantity'] !== 0.0) {
            throw new \Exception("Zero-charge rule failed for product " . $si['product_id'] . " on skip!");
        }
    }

    // Clean up
    $db->execute("DELETE FROM customers WHERE id = ?", [$custId]);
}, $passed, $failed);

// TEST 13: Multi-Product Itemized Billing
runTest("Multi-Product Invoicing: Itemized Product Subtotals", function() use ($db, $billingService, $deliveryService) {
    $tenantId = 1;
    $productService = new \App\Services\ProductService();

    // Create a customer
    $db->execute("INSERT INTO customers (tenant_id, name, phone, address, route_name, milk_types, default_quantity, rate_per_unit, status) 
                  VALUES (?, 'Billing Item Test Cust', '9888800003', 'Flat 300', 'Test Route', 'cow,buffalo', 2.0, 60.0, 'active')",
                  [$tenantId]);
    $custId = (int)$db->lastInsertId();

    $cowProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'cow'", [$tenantId]);
    $bufProd = $db->fetchOne("SELECT id FROM products WHERE tenant_id = ? AND code = 'buffalo'", [$tenantId]);

    $subscriptions = [
        ['product_id' => (int)$cowProd['id'], 'quantity' => 2.0, 'custom_rate' => 60.0, 'shift' => 'both', 'is_active' => true],
        ['product_id' => (int)$bufProd['id'], 'quantity' => 1.0, 'custom_rate' => 80.0, 'shift' => 'both', 'is_active' => true],
    ];
    $productService->saveCustomerSubscriptions($tenantId, $custId, $subscriptions);

    // Record deliveries for test month
    $year = 2026;
    $month = 9;
    // Deliver for 10 days
    for ($d = 1; $d <= 10; $d++) {
        $dStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $deliveryService->recordDelivery($tenantId, $custId, $dStr, 'morning', 'delivered');
    }

    $bill = $billingService->calculateCustomerBill($tenantId, $custId, $year, $month);

    // 10 days * 2L Cow @ 60 = 20L * 60 = 1200
    // 10 days * 1L Buffalo @ 80 = 10L * 80 = 800
    // Milk subtotal should be 2000.00
    if ((float)$bill['milk_subtotal'] !== 2000.00) {
        throw new \Exception("Expected milk subtotal 2000.00, got " . $bill['milk_subtotal']);
    }

    if (empty($bill['products']) || count($bill['products']) !== 2) {
        throw new \Exception("Expected 2 itemized products in bill");
    }

    // Clean up
    $db->execute("DELETE FROM customers WHERE id = ?", [$custId]);
}, $passed, $failed);

echo "\n========================================================\n";
echo "   TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
