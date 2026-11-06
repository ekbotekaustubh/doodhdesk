<?php
/** @var array $invoice */
/** @var array $breakdown */
/** @var array $tenant */
/** @var array $settings */
$isPdf = $isPdf ?? false;
?>
<div class="max-w-[800px] mx-auto bg-white p-8 md:p-12 shadow-sm border border-slate-200 print-shadow-none text-slate-900 font-sans text-xs">
    <!-- Header -->
    <div class="flex justify-between items-start pb-6 border-b-2 border-slate-900">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase"><?= e($tenant['business_name']) ?></h1>
            <p class="text-xs text-slate-600 mt-1">
                Owner: <strong><?= e($tenant['owner_name']) ?></strong> • Phone: <strong><?= e($tenant['phone']) ?></strong>
            </p>
            <?php if (!empty($settings['tax_number'])): ?>
                <p class="text-xs text-slate-700 font-mono mt-0.5">
                    <strong><?= e($settings['tax_label'] ?? 'GST') ?>IN:</strong> <?= e($settings['tax_number']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="text-right">
            <span class="inline-block px-3 py-1 bg-slate-900 text-white font-black uppercase text-xs tracking-wider rounded">
                TAX INVOICE
            </span>
            <div class="mt-2 text-xs font-mono">
                <div><strong>Invoice #:</strong> INV-<?= $invoice['billing_year'] ?>-<?= sprintf('%02d', $invoice['billing_month']) ?>-<?= sprintf('%04d', $invoice['id']) ?></div>
                <div><strong>Date:</strong> <?= formatDate($invoice['generated_at'], 'd M Y') ?></div>
                <div><strong>Billing Month:</strong> <?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?></div>
                <div><strong>Status:</strong> <?= strtoupper($invoice['payment_status']) ?></div>
            </div>
        </div>
    </div>

    <!-- Bill To Info & Delivery Summary -->
    <div class="grid grid-cols-2 gap-6 py-6 border-b border-slate-200">
        <div>
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bill To Customer</div>
            <div class="text-base font-black text-slate-900 mt-0.5"><?= e($invoice['customer_name']) ?></div>
            <div class="text-xs text-slate-600 mt-0.5"><?= e($invoice['customer_address']) ?></div>
            <div class="text-xs text-slate-600 mt-0.5">Phone: <strong class="font-mono"><?= e($invoice['customer_phone']) ?></strong></div>
            <div class="text-xs text-slate-600 mt-0.5">Route: <strong><?= e($invoice['route_name'] ?? 'Main Route') ?></strong></div>
            <div class="text-xs text-slate-700 font-semibold mt-1">
                Milk Subscribed: <strong><?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?></strong>
            </div>
        </div>

        <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-200 space-y-1">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Monthly Delivery Run-Sheet Verification</div>
            <div class="flex justify-between">
                <span>Month Total Days:</span>
                <strong><?= $breakdown['days_in_month'] ?? 30 ?> Days</strong>
            </div>
            <div class="flex justify-between text-emerald-700">
                <span>✓ Delivered Days:</span>
                <strong><?= $breakdown['delivered_days'] ?? 0 ?> Days (<?= number_format((float)$invoice['total_units'], 1) ?> Liters)</strong>
            </div>
            <div class="flex justify-between text-rose-700">
                <span>✗ Missed / Skipped Days:</span>
                <strong><?= $breakdown['skipped_days'] ?? 0 ?> Days (0.00 L billed)</strong>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>🏖 Vacation Leave Days:</span>
                <strong><?= $breakdown['leave_days'] ?? 0 ?> Days (0.00 L billed)</strong>
            </div>
        </div>
    </div>

    <!-- Line Item Breakdown Table -->
    <div class="py-6">
        <table class="w-full text-left text-xs border border-slate-200">
            <thead class="bg-slate-100 text-slate-900 border-b border-slate-200 uppercase text-[10px]">
                <tr>
                    <th class="p-2.5">Item Description</th>
                    <th class="p-2.5 text-center">Unit / Rate</th>
                    <th class="p-2.5 text-center">Qty / Days</th>
                    <th class="p-2.5 text-right">Amount (₹)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <!-- 1. Products Delivered Line Items -->
                <?php if (!empty($breakdown['products'])): ?>
                    <?php foreach ($breakdown['products'] as $prod): ?>
                        <tr>
                            <td class="p-2.5">
                                <div class="font-bold text-slate-900">Farm Fresh <?= e($prod['name']) ?> Delivered</div>
                                <div class="text-[10px] text-slate-500">Tracked on daily run-sheet (Zero charges on skipped days)</div>
                            </td>
                            <td class="p-2.5 text-center font-mono"><?= formatMoney($prod['unit_price']) ?> / <?= e($prod['unit']) ?></td>
                            <td class="p-2.5 text-center font-black"><?= number_format((float)$prod['total_units'], 2) ?> <?= e($prod['unit']) ?></td>
                            <td class="p-2.5 text-right font-black"><?= formatMoney($prod['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="p-2.5">
                            <div class="font-bold text-slate-900">Farm Fresh <?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?> Delivered</div>
                            <div class="text-[10px] text-slate-500">Actual deliveries tracked on daily run-sheet (Zero charges on skipped days)</div>
                        </td>
                        <td class="p-2.5 text-center font-mono"><?= formatMoney($breakdown['customer_rate'] ?? 65.00) ?> / L</td>
                        <td class="p-2.5 text-center font-black"><?= number_format((float)$invoice['total_units'], 2) ?> L</td>
                        <td class="p-2.5 text-right font-black"><?= formatMoney($invoice['milk_subtotal']) ?></td>
                    </tr>
                <?php endif; ?>

                <!-- 2. Custom Surcharges -->
                <?php if (!empty($breakdown['charges'])): ?>
                    <?php foreach ($breakdown['charges'] as $ch): ?>
                        <tr>
                            <td class="p-2.5">
                                <div class="font-bold text-slate-900"><?= e($ch['name']) ?></div>
                                <div class="text-[10px] text-slate-500"><?= e($ch['note'] ?? '') ?><?= !empty($ch['is_taxable']) ? ' (Taxable)' : '' ?></div>
                            </td>
                            <td class="p-2.5 text-center font-mono">
                                <?= formatMoney($ch['rate']) ?>
                            </td>
                            <td class="p-2.5 text-center font-mono">
                                <?= match($ch['type']) {
                                    'per_liter' => number_format((float)$invoice['total_units'], 1) . ' L',
                                    'per_delivery_day' => ($breakdown['distinct_delivered_days'] ?? $breakdown['delivered_days'] ?? 0) . ' Days',
                                    default => '1 Month',
                                } ?>
                            </td>
                            <td class="p-2.5 text-right font-black"><?= formatMoney($ch['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- 3. Tax Row -->
                <?php if (!empty($breakdown['tax_enabled']) && (float)$invoice['tax_amount'] > 0): ?>
                    <tr>
                        <td class="p-2.5 font-bold" colspan="3">
                            <?= e($breakdown['tax_label'] ?? 'GST') ?> (<?= number_format((float)($breakdown['tax_percentage'] ?? 0), 2) ?>%)
                            <span class="text-[10px] text-slate-400 font-normal">on taxable base of <?= formatMoney($breakdown['taxable_base'] ?? 0) ?></span>
                        </td>
                        <td class="p-2.5 text-right font-black"><?= formatMoney($invoice['tax_amount']) ?></td>
                    </tr>
                <?php endif; ?>

                <!-- 4. Previous Balance -->
                <?php if ((float)$invoice['previous_balance'] > 0): ?>
                    <tr class="bg-rose-50/50">
                        <td class="p-2.5 font-bold text-rose-800" colspan="3">
                            Previous Unpaid Balance Carried Forward
                        </td>
                        <td class="p-2.5 text-right font-black text-rose-800">+ <?= formatMoney($invoice['previous_balance']) ?></td>
                    </tr>
                <?php endif; ?>

                <!-- Payments Received -->
                <?php 
                $paymentsRec = (float)($breakdown['payments_received'] ?? 0.0);
                if ($paymentsRec > 0): 
                ?>
                    <tr class="bg-emerald-50/50">
                        <td class="p-2.5 font-bold text-emerald-800" colspan="3">
                            Less: Payments Received / Advance
                        </td>
                        <td class="p-2.5 text-right font-black text-emerald-800">- <?= formatMoney($paymentsRec) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-slate-50 border-t-2 border-slate-900 font-black text-sm">
                <tr>
                    <td class="p-3 uppercase" colspan="3">Total Amount Payable</td>
                    <td class="p-3 text-right text-base text-blue-700"><?= formatMoney($invoice['final_amount']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Day-by-Day Calendar Delivery Matrix -->
    <?php
    $matrix = $breakdown['calendar_matrix'] ?? [];
    $firstDayOfWeek = (int)($breakdown['first_day_of_week'] ?? 1);
    $daysInMonth = (int)($breakdown['days_in_month'] ?? count($matrix));
    ?>
    <?php if (!empty($matrix)): ?>
        <div class="py-3 border-t border-slate-200">
            <div class="flex justify-between items-center mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-700">
                    Daily Delivery Matrix & Verification (<?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?>)
                </span>
                <span class="text-[9px] text-slate-500">
                    Each date marked with delivered milk quantity (0L = Skipped / Leave)
                </span>
            </div>
            
            <table class="w-full text-center border-collapse border border-slate-300 text-[10px]">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold">
                        <th class="p-1 border border-slate-300 w-[14.28%]">Mon</th>
                        <th class="p-1 border border-slate-300 w-[14.28%]">Tue</th>
                        <th class="p-1 border border-slate-300 w-[14.28%]">Wed</th>
                        <th class="p-1 border border-slate-300 w-[14.28%]">Thu</th>
                        <th class="p-1 border border-slate-300 w-[14.28%]">Fri</th>
                        <th class="p-1 border border-slate-300 w-[14.28%] text-amber-800">Sat</th>
                        <th class="p-1 border border-slate-300 w-[14.28%] text-rose-800">Sun</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                    <?php
                    $col = 1;
                    // Pad empty cells before first day of month
                    for ($pad = 1; $pad < $firstDayOfWeek; $pad++) {
                        echo '<td class="p-1 border border-slate-200 bg-slate-50/50">&nbsp;</td>';
                        $col++;
                    }

                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $cell = $matrix[$d] ?? ['quantity' => 0.0, 'status' => 'unrecorded'];
                        $st = $cell['status'] ?? 'unrecorded';
                        $q = (float)($cell['quantity'] ?? 0);
                        
                        $bg = $st === 'delivered' ? 'background-color: #ecfdf5;' : ($st === 'skipped' ? 'background-color: #fff1f2;' : ($st === 'leave' ? 'background-color: #f1f5f9;' : ''));
                        $qtyText = $st === 'delivered' ? number_format($q, 1) . 'L' : ($st === 'skipped' ? '0L (Skip)' : ($st === 'leave' ? '0L (Lv)' : '-'));
                        $qtyColor = $st === 'delivered' ? 'color: #047857; font-weight: bold;' : ($st === 'skipped' ? 'color: #be123c; font-weight: bold;' : 'color: #64748b;');

                        echo "<td class=\"p-1 border border-slate-300 align-top\" style=\"{$bg}\">";
                        echo "<div style=\"font-size: 8px; font-weight: bold; color: #475569;\">{$d}</div>";
                        echo "<div style=\"font-size: 9px; line-height: 1.1; {$qtyColor}\">{$qtyText}</div>";
                        echo "</td>";

                        if ($col % 7 === 0 && $d < $daysInMonth) {
                            echo "</tr><tr>";
                        }
                        $col++;
                    }

                    // Pad remaining cells of last row
                    while (($col - 1) % 7 !== 0) {
                        echo '<td class="p-1 border border-slate-200 bg-slate-50/50">&nbsp;</td>';
                        $col++;
                    }
                    ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Payment UPI QR & Note Footer -->
    <div class="pt-6 border-t border-slate-200 grid grid-cols-3 gap-6 items-center">
        <div class="col-span-2">
            <div class="font-black text-xs uppercase tracking-wider text-slate-900">Payment Terms & Notes</div>
            <p class="text-xs text-slate-600 mt-1">
                <?= e($settings['invoice_footer_note'] ?? 'Please pay via UPI or cash before the 5th of this month. For any questions, contact your milk delivery partner.') ?>
            </p>
            <?php if (!empty($settings['upi_id'])): ?>
                <div class="mt-2 text-xs font-mono">
                    <strong>UPI ID:</strong> <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-300"><?= e($settings['upi_id']) ?></span>
                </div>
            <?php endif; ?>
            <div class="mt-4 pt-4 border-t border-slate-100 text-[10px] text-slate-400">
                Powered by DairyFlow SaaS • Multi-Tenant Zero-Dispute Invoicing
            </div>
        </div>

        <div class="text-center">
            <div class="text-[10px] font-black uppercase text-slate-500 mb-1">Scan to Pay UPI</div>
            <?= \App\Services\QrCodeService::renderQrTag(
                $settings['upi_qr_path'] ?? null,
                $settings['upi_id'] ?? null,
                $tenant['business_name'] ?? 'DairyFlow',
                (float)$invoice['final_amount'],
                110,
                "Milk Bill INV-{$invoice['id']}"
            ) ?>
        </div>
    </div>
</div>
