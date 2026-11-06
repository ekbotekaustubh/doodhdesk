<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\ChargeType;

final class BillingService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function calculateCustomerBill(int $tenantId, int $customerId, int $year, int $month): array
    {
        // 1. Fetch customer details
        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ? AND tenant_id = ?",
            [$customerId, $tenantId]
        );
        if (!$customer) {
            throw new \InvalidArgumentException("Customer not found.");
        }

        $customerRate = (float)$customer['rate_per_unit'];

        // Determine date range for billing month
        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Fetch all delivery records for this customer in the month
        $deliveries = $this->db->fetchAll(
            "SELECT id, delivery_date, shift, quantity, status, notes
             FROM daily_deliveries
             WHERE tenant_id = ? AND customer_id = ? AND delivery_date BETWEEN ? AND ?
             ORDER BY delivery_date ASC, shift ASC",
            [$tenantId, $customerId, $startDate, $endDate]
        );

        // Fetch detailed product delivery items if available (Approach A)
        $deliveryItems = $this->db->fetchAll(
            "SELECT ddi.*, dd.delivery_date, dd.shift, dd.status as delivery_status, 
                    p.name as product_name, p.code as product_code, p.unit
             FROM daily_delivery_items ddi
             JOIN daily_deliveries dd ON dd.id = ddi.delivery_id
             JOIN products p ON p.id = ddi.product_id
             WHERE ddi.tenant_id = ? AND dd.customer_id = ? AND dd.delivery_date BETWEEN ? AND ?
             ORDER BY dd.delivery_date ASC, dd.shift ASC, p.sort_order ASC",
            [$tenantId, $customerId, $startDate, $endDate]
        );

        // Analyze deliveries
        $totalUnits = 0.0;
        $deliveredDaysSet = [];
        $deliveredCount = 0;
        $skippedCount = 0;
        $leaveCount = 0;
        $itemizedDeliveries = [];

        foreach ($deliveries as $del) {
            $status = $del['status'];
            $qty = (float)$del['quantity'];
            $date = $del['delivery_date'];

            if ($status === 'delivered') {
                $deliveredCount++;
                $totalUnits += $qty;
                $deliveredDaysSet[$date] = true;
            } elseif ($status === 'skipped') {
                $skippedCount++;
            } elseif ($status === 'leave') {
                $leaveCount++;
            }

            $itemizedDeliveries[] = [
                'date' => $date,
                'shift' => $del['shift'],
                'status' => $status,
                'quantity' => $qty,
                'amount' => round($qty * $customerRate, 2),
                'notes' => $del['notes'] ?? '',
            ];
        }

        $distinctDeliveredDays = count($deliveredDaysSet);

        // 1. Base Product / Milk Subtotal
        $productSummaries = [];
        if (!empty($deliveryItems)) {
            $calculatedSubtotal = 0.0;
            foreach ($deliveryItems as $di) {
                $pId = (int)$di['product_id'];
                $pQty = (float)$di['quantity'];
                $pRate = (float)$di['unit_price'];
                $pStatus = $di['status'];

                if (!isset($productSummaries[$pId])) {
                    $productSummaries[$pId] = [
                        'product_id' => $pId,
                        'name' => (string)$di['product_name'],
                        'code' => (string)$di['product_code'],
                        'unit' => (string)$di['unit'],
                        'unit_price' => $pRate,
                        'total_units' => 0.0,
                        'subtotal' => 0.0,
                    ];
                }

                if ($pStatus === 'delivered') {
                    $productSummaries[$pId]['total_units'] += $pQty;
                    $lineAmt = round($pQty * $pRate, 2);
                    $productSummaries[$pId]['subtotal'] += $lineAmt;
                    $calculatedSubtotal += $lineAmt;
                }
            }
            $milkSubtotal = round($calculatedSubtotal, 2);
        } else {
            // Fallback for single-rate legacy deliveries
            $milkSubtotal = round($totalUnits * $customerRate, 2);
            $productSummaries[] = [
                'product_id' => 1,
                'name' => 'Farm Fresh Milk',
                'code' => 'milk',
                'unit' => 'L',
                'unit_price' => $customerRate,
                'total_units' => $totalUnits,
                'subtotal' => $milkSubtotal,
            ];
        }

        // 2. Additional Charges from tenant_additional_charges (Active custom charges)
        $activeCharges = $this->db->fetchAll(
            "SELECT * FROM tenant_additional_charges WHERE tenant_id = ? AND is_active = 1",
            [$tenantId]
        );

        $chargeLineItems = [];
        $additionalChargesTotal = 0.0;
        $taxableChargesTotal = 0.0;

        foreach ($activeCharges as $charge) {
            $cType = $charge['charge_type'];
            $cAmount = (float)$charge['amount'];
            $isTaxable = (bool)$charge['is_taxable'];
            $lineAmount = 0.0;
            $calculationNote = '';

            match ($cType) {
                ChargeType::FlatMonthly->value => (function() use (&$lineAmount, &$calculationNote, $cAmount) {
                    $lineAmount = $cAmount;
                    $calculationNote = 'Flat monthly fee';
                })(),
                ChargeType::PerLiter->value => (function() use (&$lineAmount, &$calculationNote, $cAmount, $totalUnits) {
                    $lineAmount = round($cAmount * $totalUnits, 2);
                    $calculationNote = "₹{$cAmount}/L × {$totalUnits} Liters";
                })(),
                ChargeType::PerDeliveryDay->value => (function() use (&$lineAmount, &$calculationNote, $cAmount, $distinctDeliveredDays) {
                    $lineAmount = round($cAmount * $distinctDeliveredDays, 2);
                    $calculationNote = "₹{$cAmount}/Day × {$distinctDeliveredDays} Days";
                })(),
                default => null,
            };

            $additionalChargesTotal += $lineAmount;
            if ($isTaxable) {
                $taxableChargesTotal += $lineAmount;
            }

            $chargeLineItems[] = [
                'charge_id' => (int)$charge['id'],
                'name' => (string)$charge['charge_name'],
                'type' => $cType,
                'rate' => $cAmount,
                'is_taxable' => $isTaxable,
                'amount' => $lineAmount,
                'note' => $calculationNote,
            ];
        }

        $additionalChargesTotal = round($additionalChargesTotal, 2);

        // 3. Tax Amount = (Base Subtotal + Taxable Charges) * (Tax% / 100)
        $tenantSettings = $this->db->fetchOne(
            "SELECT * FROM tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $taxEnabled = $tenantSettings ? (bool)$tenantSettings['tax_enabled'] : false;
        $taxLabel = $tenantSettings['tax_label'] ?? 'GST';
        $taxRate = $tenantSettings ? (float)$tenantSettings['tax_percentage'] : 0.00;
        $taxNumber = $tenantSettings['tax_number'] ?? null;
        $taxAmount = 0.00;
        $taxableBase = 0.00;

        if ($taxEnabled && $taxRate > 0) {
            $taxableBase = round($milkSubtotal + $taxableChargesTotal, 2);
            $taxAmount = round($taxableBase * ($taxRate / 100), 2);
        }

        // 4. Previous Balance & Payments Received
        // Previous balance from last month unpaid invoice
        $prevMonthDate = (new \DateTime("{$year}-{$month}-01"))->modify('-1 month');
        $prevYear = (int)$prevMonthDate->format('Y');
        $prevMonth = (int)$prevMonthDate->format('m');

        $prevInvoice = $this->db->fetchOne(
            "SELECT final_amount, payment_status, id FROM invoices 
             WHERE tenant_id = ? AND customer_id = ? AND billing_year = ? AND billing_month = ?",
            [$tenantId, $customerId, $prevYear, $prevMonth]
        );

        $previousBalance = 0.0;
        if ($prevInvoice && in_array($prevInvoice['payment_status'], ['unpaid', 'partial'])) {
            // Find payments made towards prev invoice
            $prevPaid = (float)$this->db->fetchValue(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND invoice_id = ?",
                [$tenantId, $prevInvoice['id']]
            );
            $previousBalance = max(0.0, round((float)$prevInvoice['final_amount'] - $prevPaid, 2));
        }

        // Payments received for current month/cycle
        $paymentsReceived = (float)$this->db->fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM payments 
             WHERE tenant_id = ? AND customer_id = ? AND payment_date BETWEEN ? AND ?",
            [$tenantId, $customerId, $startDate, $endDate]
        );

        // Total Payable = Base Subtotal + Additional Charges + Tax Amount + Previous Balance - Payments Received
        $totalPayable = round($milkSubtotal + $additionalChargesTotal + $taxAmount + $previousBalance - $paymentsReceived, 2);
        if ($totalPayable < 0) {
            $totalPayable = 0.00;
        }

        // Determine payment status
        $paymentStatus = 'unpaid';
        if ($paymentsReceived >= ($milkSubtotal + $additionalChargesTotal + $taxAmount + $previousBalance)) {
            $paymentStatus = 'paid';
        } elseif ($paymentsReceived > 0) {
            $paymentStatus = 'partial';
        }

        $matrixData = buildCalendarMatrix([
            'itemized_deliveries' => $itemizedDeliveries,
            'days_in_month' => $daysInMonth,
        ], $year, $month);

        return [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'customer' => $customer,
            'year' => $year,
            'month' => $month,
            'billing_period' => sprintf('%02d/%04d', $month, $year),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_in_month' => $daysInMonth,
            'delivered_days' => $deliveredCount,
            'distinct_delivered_days' => $distinctDeliveredDays,
            'skipped_days' => $skippedCount,
            'leave_days' => $leaveCount,
            'total_units' => round($totalUnits, 2),
            'customer_rate' => $customerRate,
            'milk_subtotal' => $milkSubtotal,
            'products' => array_values($productSummaries),
            'charges' => $chargeLineItems,
            'additional_charges_total' => $additionalChargesTotal,
            'taxable_charges_total' => $taxableChargesTotal,
            'tax_enabled' => $taxEnabled,
            'tax_label' => $taxLabel,
            'tax_percentage' => $taxRate,
            'tax_number' => $taxNumber,
            'taxable_base' => $taxableBase,
            'tax_amount' => $taxAmount,
            'previous_balance' => $previousBalance,
            'payments_received' => $paymentsReceived,
            'final_amount' => $totalPayable,
            'payment_status' => $paymentStatus,
            'itemized_deliveries' => $itemizedDeliveries,
            'calendar_matrix' => $matrixData['matrix'],
            'first_day_of_week' => $matrixData['first_day_of_week'],
            'tenant_settings' => $tenantSettings,
        ];
    }

    public function generateOrUpdateInvoice(int $tenantId, int $customerId, int $year, int $month): array
    {
        $bill = $this->calculateCustomerBill($tenantId, $customerId, $year, $month);

        $breakdownJson = json_encode($bill, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $sql = "INSERT INTO invoices (
                    tenant_id, customer_id, billing_month, billing_year,
                    total_units, milk_subtotal, additional_charges_total, tax_amount,
                    previous_balance, final_amount, payment_status, breakdown_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_units = VALUES(total_units),
                    milk_subtotal = VALUES(milk_subtotal),
                    additional_charges_total = VALUES(additional_charges_total),
                    tax_amount = VALUES(tax_amount),
                    previous_balance = VALUES(previous_balance),
                    final_amount = VALUES(final_amount),
                    payment_status = VALUES(payment_status),
                    breakdown_json = VALUES(breakdown_json),
                    generated_at = CURRENT_TIMESTAMP";

        $this->db->execute($sql, [
            $tenantId,
            $customerId,
            $month,
            $year,
            $bill['total_units'],
            $bill['milk_subtotal'],
            $bill['additional_charges_total'],
            $bill['tax_amount'],
            $bill['previous_balance'],
            $bill['final_amount'],
            $bill['payment_status'],
            $breakdownJson
        ]);

        $invoiceId = (int)$this->db->fetchValue(
            "SELECT id FROM invoices WHERE tenant_id = ? AND customer_id = ? AND billing_year = ? AND billing_month = ?",
            [$tenantId, $customerId, $year, $month]
        );

        $bill['invoice_id'] = $invoiceId;
        return $bill;
    }

    public function generateBatchInvoices(int $tenantId, int $year, int $month): array
    {
        $customers = $this->db->fetchAll(
            "SELECT id FROM customers WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );

        $results = [];
        foreach ($customers as $c) {
            $results[] = $this->generateOrUpdateInvoice($tenantId, (int)$c['id'], $year, $month);
        }

        return $results;
    }
}
