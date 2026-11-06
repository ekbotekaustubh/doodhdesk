<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$pdo = getDbConnection();

echo "Seeding DoodhDesk Database...\n";

// 1. System Settings
$settings = [
    'free_customer_limit' => '5',
    'paid_tier_unit_size' => '100',
    'paid_tier_unit_price' => '200.00',
    'currency_symbol' => '₹',
    'platform_name' => 'DoodhDesk SaaS',
    'support_email' => 'support@doodhdesk.com',
    'support_phone' => '+91 98000 11223'
];

$stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
foreach ($settings as $k => $v) {
    $stmt->execute([$k, $v]);
}

// 2. Super Admin
$adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
$pdo->prepare("INSERT IGNORE INTO super_admins (id, name, email, password_hash) VALUES (1, 'DoodhDesk Platform Admin', 'admin@doodhdesk.com', ?)")
    ->execute([$adminPassword]);

// 3. Tenant 1: Krishna Pure Milk Dairy
$tenantPassword = password_hash('password123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO tenants (id, business_name, owner_name, phone, email, password_hash, status) 
    VALUES (1, 'Krishna Pure Milk Dairy', 'Ramesh Patel', '9876543210', 'ramesh@krishnadairy.com', ?, 'active')
    ON DUPLICATE KEY UPDATE business_name = VALUES(business_name)");
$stmt->execute([$tenantPassword]);

// Tenant 1 Settings
$pdo->prepare("INSERT INTO tenant_settings (tenant_id, tax_enabled, tax_label, tax_percentage, tax_number, upi_id, invoice_footer_note)
    VALUES (1, 1, 'GST', 5.00, '24AAAAA0000A1Z5', 'krishnadairy@okaxis', 'Pure and fresh farm milk delivered daily at your doorstep. Please pay bills on or before the 5th of every month.')
    ON DUPLICATE KEY UPDATE tax_enabled = 1, tax_label = 'GST', tax_percentage = 5.00, tax_number = '24AAAAA0000A1Z5', upi_id = 'krishnadairy@okaxis'")
    ->execute();

// Tenant 1 Additional Charges
$pdo->prepare("DELETE FROM tenant_additional_charges WHERE tenant_id = 1")->execute();
$chargeStmt = $pdo->prepare("INSERT INTO tenant_additional_charges (tenant_id, charge_name, charge_type, amount, is_taxable, is_active) VALUES (?, ?, ?, ?, ?, ?)");
$charges = [
    [1, 'Monthly Bottle Maintenance Fee', 'flat_monthly', 50.00, 1, 1],
    [1, 'Morning Delivery Transport Fee', 'per_delivery_day', 2.00, 0, 1],
    [1, 'Glass Bottle Sanitization Surcharge', 'per_liter', 1.00, 1, 1],
];
foreach ($charges as $c) {
    $chargeStmt->execute($c);
}

// Tenant 1 Subscription (Active Paid Tier - 1 block = 105 max customers)
$pdo->prepare("INSERT INTO tenant_subscriptions (id, tenant_id, purchased_blocks, max_customers_allowed, amount_paid, valid_until, payment_status)
    VALUES (1, 1, 1, 105, 200.00, '2027-12-31', 'active')
    ON DUPLICATE KEY UPDATE purchased_blocks = 1, max_customers_allowed = 105, payment_status = 'active'")
    ->execute();

// 4. Tenant 2: Radha Cow Dairy (Free Tier Demo: 4 customers, near quota limit of 5)
$stmt = $pdo->prepare("INSERT INTO tenants (id, business_name, owner_name, phone, email, password_hash, status) 
    VALUES (2, 'Radha Fresh Cow Dairy', 'Suresh Sharma', '9876500000', 'suresh@radhadairy.com', ?, 'active')
    ON DUPLICATE KEY UPDATE business_name = VALUES(business_name)");
$stmt->execute([$tenantPassword]);

$pdo->prepare("INSERT INTO tenant_settings (tenant_id, tax_enabled, tax_label, tax_percentage, tax_number, upi_id, invoice_footer_note)
    VALUES (2, 0, 'GST', 0.00, NULL, 'radhadairy@upi', 'Fresh A2 Cow Milk. Please pay on time.')
    ON DUPLICATE KEY UPDATE tax_enabled = 0")
    ->execute();

$pdo->prepare("INSERT INTO tenant_subscriptions (id, tenant_id, purchased_blocks, max_customers_allowed, amount_paid, valid_until, payment_status)
    VALUES (2, 2, 0, 5, 0.00, NULL, 'free')
    ON DUPLICATE KEY UPDATE max_customers_allowed = 5, payment_status = 'free'")
    ->execute();

// 4. Products & Catalog for Tenants (Approach A: Normalized Multi-Product Architecture)
$pdo->prepare("DELETE FROM daily_delivery_items WHERE tenant_id IN (1, 2)")->execute();
$pdo->prepare("DELETE FROM customer_subscriptions WHERE tenant_id IN (1, 2)")->execute();
$pdo->prepare("DELETE FROM products WHERE tenant_id IN (1, 2)")->execute();

$prodStmt = $pdo->prepare("INSERT INTO products (id, tenant_id, name, code, unit, default_rate, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
// Tenant 1 Products
$prodStmt->execute([1, 1, 'Cow Milk', 'cow', 'L', 65.00, 1, 1]);
$prodStmt->execute([2, 1, 'Buffalo Milk', 'buffalo', 'L', 70.00, 1, 2]);
$prodStmt->execute([3, 1, 'Fresh Paneer', 'paneer', 'kg', 360.00, 1, 3]);

// Tenant 2 Products
$prodStmt->execute([101, 2, 'Cow Milk', 'cow', 'L', 60.00, 1, 1]);
$prodStmt->execute([102, 2, 'Buffalo Milk', 'buffalo', 'L', 68.00, 1, 2]);

// 5. Customers for Tenant 1
$pdo->prepare("DELETE FROM customers WHERE tenant_id = 1")->execute();
$custStmt = $pdo->prepare("INSERT INTO customers (id, tenant_id, name, phone, address, route_name, milk_types, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$customers = [
    [1, 1, 'Aarav Sharma', '9822011111', 'Flat 101, Gokul Heights, Station Road', 'Green Valley Route', 'cow', 'active'],
    [2, 1, 'Priya Verma', '9822022222', 'B-204, Sunrise Residency, Near Temple', 'Green Valley Route', 'buffalo', 'active'],
    [3, 1, 'Rajesh Gupta', '9822033333', 'House #14, Civil Lines, Main Market', 'North City Route', 'cow,buffalo', 'active'],
    [4, 1, 'Sneha Kulkarni', '9822044444', 'Flat 302, Palm Grove Society', 'Green Valley Route', 'cow', 'active'],
    [5, 1, 'Vikram Singh', '9822055555', 'Villa 5, Lakeview Society, Ring Road', 'North City Route', 'buffalo', 'active'],
    [6, 1, 'Ananya Deshmukh', '9822066666', 'Flat 405, Lotus Residency', 'North City Route', 'cow,buffalo', 'active'],
];
foreach ($customers as $c) {
    $custStmt->execute($c);
}

// Subscriptions for Tenant 1 Customers
$subStmt = $pdo->prepare("INSERT INTO customer_subscriptions (tenant_id, customer_id, product_id, default_quantity, custom_rate, shift, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
// Customer 1: Aarav (Cow 2.0L @ 65)
$subStmt->execute([1, 1, 1, 2.00, 65.00, 'both', 1]);
// Customer 2: Priya (Buffalo 1.5L @ 70)
$subStmt->execute([1, 2, 2, 1.50, 70.00, 'both', 1]);
// Customer 3: Rajesh Gupta takes BOTH Cow & Buffalo with separate quantities & rates!
$subStmt->execute([1, 3, 1, 1.00, 60.00, 'both', 1]);
$subStmt->execute([1, 3, 2, 0.50, 70.00, 'both', 1]);
// Customer 4: Sneha (Cow 2.5L @ 65)
$subStmt->execute([1, 4, 1, 2.50, 65.00, 'both', 1]);
// Customer 5: Vikram (Buffalo 3.0L @ 70)
$subStmt->execute([1, 5, 2, 3.00, 70.00, 'both', 1]);
// Customer 6: Ananya takes BOTH Cow & Buffalo with separate quantities!
$subStmt->execute([1, 6, 1, 1.00, 65.00, 'both', 1]);
$subStmt->execute([1, 6, 2, 1.00, 70.00, 'both', 1]);

// 4 Customers for Tenant 2 (Free tier)
$pdo->prepare("DELETE FROM customers WHERE tenant_id = 2")->execute();
$tenant2Cust = [
    [101, 2, 'Kavita Joshi', '9911011111', 'A-12 Shiv Krupa', 'Sector 1', 'cow', 'active'],
    [102, 2, 'Manoj Patil', '9911022222', 'B-04 Golden Nest', 'Sector 1', 'cow,buffalo', 'active'],
    [103, 2, 'Dinesh Rao', '9911033333', 'C-31 Shanti Niketan', 'Sector 2', 'buffalo', 'active'],
    [104, 2, 'Neha Agarwal', '9911044444', 'D-15 Park View', 'Sector 2', 'cow', 'active'],
];
foreach ($tenant2Cust as $c) {
    $custStmt->execute($c);
}
// Subscriptions for Tenant 2
$subStmt->execute([2, 101, 101, 1.00, 60.00, 'both', 1]);
$subStmt->execute([2, 102, 101, 1.00, 60.00, 'both', 1]);
$subStmt->execute([2, 102, 102, 1.00, 68.00, 'both', 1]);
$subStmt->execute([2, 103, 102, 1.50, 68.00, 'both', 1]);
$subStmt->execute([2, 104, 101, 1.00, 60.00, 'both', 1]);

// 6. Vacation / Customer Leaves for Priya Verma (Tenant 1)
$pdo->prepare("DELETE FROM customer_leaves WHERE tenant_id = 1")->execute();
$now = new DateTime('now');
$currentYear = (int)$now->format('Y');
$currentMonth = (int)$now->format('m');
$todayDateStr = $now->format('Y-m-d');

// Priya has leave around mid current month or past days
$leaveStart = (clone $now)->setDate($currentYear, $currentMonth, 10)->format('Y-m-d');
$leaveEnd = (clone $now)->setDate($currentYear, $currentMonth, 13)->format('Y-m-d');
$pdo->prepare("INSERT INTO customer_leaves (tenant_id, customer_id, start_date, end_date, reason) VALUES (1, 2, ?, ?, 'Family out of town')")
    ->execute([$leaveStart, $leaveEnd]);

// 7. Seed Daily Deliveries for Tenant 1 for the previous month and current month up to today!
$pdo->prepare("DELETE FROM daily_delivery_items WHERE tenant_id = 1")->execute();
$pdo->prepare("DELETE FROM daily_deliveries WHERE tenant_id = 1")->execute();

$delivStmt = $pdo->prepare("INSERT INTO daily_deliveries (tenant_id, customer_id, delivery_date, shift, quantity, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
$delivItemStmt = $pdo->prepare("INSERT INTO daily_delivery_items (tenant_id, delivery_id, product_id, quantity, unit_price, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");

// Preload customer subscriptions for fast seeding
$subRows = $pdo->query("SELECT cs.*, p.default_rate as prod_rate FROM customer_subscriptions cs JOIN products p ON p.id = cs.product_id WHERE cs.tenant_id = 1")->fetchAll(PDO::FETCH_ASSOC);
$custSubsMap = [];
foreach ($subRows as $sr) {
    $custSubsMap[$sr['customer_id']][] = $sr;
}

$recordSeedDelivery = function(int $cId, string $date, string $shift, string $status, float $quantity, ?string $notes) use ($pdo, $delivStmt, $delivItemStmt, $custSubsMap) {
    $delivStmt->execute([1, $cId, $date, $shift, $quantity, $status, $notes]);
    $delivId = (int)$pdo->lastInsertId();

    if (isset($custSubsMap[$cId])) {
        foreach ($custSubsMap[$cId] as $cSub) {
            $pId = (int)$cSub['product_id'];
            $pRate = (float)($cSub['custom_rate'] ?? $cSub['prod_rate']);
            $defaultSubQty = (float)$cSub['default_quantity'];

            // If skipped or leave, 0.00
            if ($status === 'skipped' || $status === 'leave') {
                $pQty = 0.00;
            } else {
                // If adjusted total quantity, scale proportionally
                $pQty = $defaultSubQty;
            }
            $delivItemStmt->execute([1, $delivId, $pId, $pQty, $pRate, $status, $notes]);
        }
    }
};

// Generate previous month full data (e.g., October 2026 or last month)
$prevMonthDate = (clone $now)->modify('-1 month');
$prevYear = (int)$prevMonthDate->format('Y');
$prevMonth = (int)$prevMonthDate->format('m');
$daysInPrevMonth = (int)$prevMonthDate->format('t');

for ($day = 1; $day <= $daysInPrevMonth; $day++) {
    $dStr = sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $day);
    foreach ($customers as $c) {
        $cId = $c[0];
        $defaultQty = array_sum(array_column($custSubsMap[$cId] ?? [], 'default_quantity'));

        // Shift 1: Morning
        // Let's create some missed days (e.g. day 7 and 21 for customer 1)
        if ($cId === 1 && ($day === 7 || $day === 21)) {
            $recordSeedDelivery($cId, $dStr, 'morning', 'skipped', 0.00, 'Customer called to skip morning');
        } elseif ($cId === 3 && $day >= 15 && $day <= 18) {
            $recordSeedDelivery($cId, $dStr, 'morning', 'leave', 0.00, 'Out of station');
        } elseif ($cId === 4 && $day === 12) {
            // Extra milk on Sunday!
            $recordSeedDelivery($cId, $dStr, 'morning', 'delivered', $defaultQty + 1.0, 'Requested +1L extra milk');
        } else {
            $recordSeedDelivery($cId, $dStr, 'morning', 'delivered', $defaultQty, null);
        }
    }
}

// Generate current month data up to today
$currentDay = (int)$now->format('d');
for ($day = 1; $day <= $currentDay; $day++) {
    $dStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
    foreach ($customers as $c) {
        $cId = $c[0];
        $defaultQty = array_sum(array_column($custSubsMap[$cId] ?? [], 'default_quantity'));

        // Check if on leave
        if ($cId === 2 && $dStr >= $leaveStart && $dStr <= $leaveEnd) {
            $recordSeedDelivery($cId, $dStr, 'morning', 'leave', 0.00, 'Family vacation');
            continue;
        }

        // Today's entries: leave some pending unverified so notification banner shows up!
        if ($day === $currentDay) {
            if ($cId === 5 || $cId === 6) {
                // Not yet marked today! Leave out of DB or mark unconfirmed
                continue;
            }
        }

        if ($cId === 1 && $day === 2) {
            $recordSeedDelivery($cId, $dStr, 'morning', 'skipped', 0.00, 'Door locked, skipped');
        } else {
            $recordSeedDelivery($cId, $dStr, 'morning', 'delivered', $defaultQty, null);
        }
    }
}

// 8. Generate Previous Month Invoices for Tenant 1
$pdo->prepare("DELETE FROM invoices WHERE tenant_id = 1")->execute();
$pdo->prepare("DELETE FROM payments WHERE tenant_id = 1")->execute();

$invStmt = $pdo->prepare("INSERT INTO invoices (tenant_id, customer_id, billing_month, billing_year, total_units, milk_subtotal, additional_charges_total, tax_amount, previous_balance, final_amount, payment_status, breakdown_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// Calculate for each customer in prev month
foreach ($customers as $c) {
    $cId = $c[0];
    $cRate = (float)($custSubsMap[$cId][0]['custom_rate'] ?? $custSubsMap[$cId][0]['prod_rate'] ?? 60.0);

    // Deliveries summary for prev month
    $q = $pdo->prepare("SELECT 
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_days,
        COUNT(CASE WHEN status = 'skipped' THEN 1 END) as skipped_days,
        COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days,
        COALESCE(SUM(quantity), 0) as total_liters
        FROM daily_deliveries 
        WHERE tenant_id = 1 AND customer_id = ? AND delivery_date BETWEEN ? AND ?");
    $startPrev = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
    $endPrev = sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $daysInPrevMonth);
    $q->execute([$cId, $startPrev, $endPrev]);
    $stats = $q->fetch(PDO::FETCH_ASSOC);

    $totalLiters = (float)$stats['total_liters'];
    $deliveredDays = (int)$stats['delivered_days'];
    $skippedDays = (int)$stats['skipped_days'];
    $leaveDays = (int)$stats['leave_days'];

    // 1. Base Milk Subtotal
    $milkSubtotal = round($totalLiters * $cRate, 2);

    // 2. Additional Charges
    // Flat monthly: 50.00 (taxable)
    // Per delivery day: 2.00 * deliveredDays (non-taxable)
    // Per liter: 1.00 * totalLiters (taxable)
    $flatCharge = 50.00;
    $transportCharge = round(2.00 * $deliveredDays, 2);
    $bottleSanitizeCharge = round(1.00 * $totalLiters, 2);
    $additionalChargesTotal = round($flatCharge + $transportCharge + $bottleSanitizeCharge, 2);

    // 3. Tax (5% on milk subtotal + taxable charges)
    $taxableBase = $milkSubtotal + $flatCharge + $bottleSanitizeCharge;
    $taxAmount = round($taxableBase * (5.00 / 100), 2);

    // 4. Previous Balance
    $previousBalance = ($cId === 3) ? 150.00 : 0.00;

    // Total Payable
    $finalAmount = round($milkSubtotal + $additionalChargesTotal + $taxAmount + $previousBalance, 2);

    // Itemized deliveries for invoice audit log & matrix
    $delivRows = $pdo->prepare("SELECT delivery_date as date, shift, status, quantity, notes FROM daily_deliveries WHERE tenant_id = 1 AND customer_id = ? AND delivery_date BETWEEN ? AND ? ORDER BY delivery_date ASC, shift ASC");
    $delivRows->execute([$cId, $startPrev, $endPrev]);
    $itemizedDeliveries = [];
    while ($dr = $delivRows->fetch(PDO::FETCH_ASSOC)) {
        $dr['quantity'] = (float)$dr['quantity'];
        $dr['amount'] = round($dr['quantity'] * $cRate, 2);
        $itemizedDeliveries[] = $dr;
    }

    $matrixData = buildCalendarMatrix([
        'itemized_deliveries' => $itemizedDeliveries,
        'days_in_month' => $daysInPrevMonth,
    ], $prevYear, $prevMonth);

    $breakdown = [
        'customer_name' => $c[2],
        'customer_phone' => $c[3],
        'customer_milk_types' => $c[6],
        'rate_per_unit' => $cRate,
        'billing_period' => sprintf('%02d/%04d', $prevMonth, $prevYear),
        'days_in_month' => $daysInPrevMonth,
        'delivered_days' => $deliveredDays,
        'skipped_days' => $skippedDays,
        'leave_days' => $leaveDays,
        'total_units' => $totalLiters,
        'milk_subtotal' => $milkSubtotal,
        'charges' => [
            ['name' => 'Monthly Bottle Maintenance Fee', 'type' => 'flat_monthly', 'rate' => 50.00, 'amount' => $flatCharge, 'taxable' => true],
            ['name' => 'Morning Delivery Transport Fee', 'type' => 'per_delivery_day', 'rate' => 2.00, 'amount' => $transportCharge, 'taxable' => false],
            ['name' => 'Glass Bottle Sanitization Surcharge', 'type' => 'per_liter', 'rate' => 1.00, 'amount' => $bottleSanitizeCharge, 'taxable' => true],
        ],
        'additional_charges_total' => $additionalChargesTotal,
        'tax_enabled' => true,
        'tax_label' => 'GST',
        'tax_percentage' => 5.00,
        'tax_amount' => $taxAmount,
        'previous_balance' => $previousBalance,
        'final_amount' => $finalAmount,
        'itemized_deliveries' => $itemizedDeliveries,
        'calendar_matrix' => $matrixData['matrix'],
        'first_day_of_week' => $matrixData['first_day_of_week'],
    ];

    $paymentStatus = ($cId === 1 || $cId === 2) ? 'paid' : (($cId === 4) ? 'partial' : 'unpaid');
    $invStmt->execute([
        1, $cId, $prevMonth, $prevYear, $totalLiters, $milkSubtotal,
        $additionalChargesTotal, $taxAmount, $previousBalance, $finalAmount,
        $paymentStatus, json_encode($breakdown, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ]);
    $invoiceId = (int)$pdo->lastInsertId();

    // If paid or partial, insert payments
    if ($paymentStatus === 'paid') {
        $pdo->prepare("INSERT INTO payments (tenant_id, customer_id, invoice_id, amount, payment_date, payment_method, reference_no, notes)
            VALUES (1, ?, ?, ?, ?, 'upi', 'UPI-TXN-984321', 'Paid via PhonePe QR')")
            ->execute([$cId, $invoiceId, $finalAmount, (clone $now)->setDate($currentYear, $currentMonth, 3)->format('Y-m-d')]);
    } elseif ($paymentStatus === 'partial') {
        $pdo->prepare("INSERT INTO payments (tenant_id, customer_id, invoice_id, amount, payment_date, payment_method, reference_no, notes)
            VALUES (1, ?, ?, 1000.00, ?, 'cash', NULL, 'Cash advance received')")
            ->execute([$cId, $invoiceId, (clone $now)->setDate($currentYear, $currentMonth, 2)->format('Y-m-d')]);
    }
}

echo "Database seeded successfully!\n";
echo "Super Admin: admin@doodhdesk.com / admin123\n";
echo "Tenant 1 (Krishna Pure Milk Dairy): ramesh@krishnadairy.com / password123 (Active Paid Quota: 105)\n";
echo "Tenant 2 (Radha Fresh Cow Dairy): suresh@radhadairy.com / password123 (Free Tier Quota: 5)\n";
