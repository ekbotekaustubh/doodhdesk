<div class="space-y-6 max-w-4xl mx-auto" x-data="{ showPaymentModal: false }">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <a href="<?= url("/invoices?year={$invoice['billing_year']}&month={$invoice['billing_month']}") ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900">
            ← Back to Invoices (<?= sprintf('%02d/%04d', $invoice['billing_month'], $invoice['billing_year']) ?>)
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Recalculate Form -->
            <form method="POST" action="<?= url('/invoices/generate-single') ?>" onsubmit="return confirm('Recalculate invoice based on latest daily delivery entries?');" class="inline">
                <?= \App\Core\Auth::csrfField() ?>
                <input type="hidden" name="customer_id" value="<?= $invoice['customer_id'] ?>">
                <input type="hidden" name="year" value="<?= $invoice['billing_year'] ?>">
                <input type="hidden" name="month" value="<?= $invoice['billing_month'] ?>">
                <button type="submit" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-300">
                    🔄 Recalculate
                </button>
            </form>

            <!-- Record Payment Button -->
            <button @click="showPaymentModal = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow-sm flex items-center gap-1.5">
                <span>💰 Record Payment</span>
            </button>

            <!-- Thermal Receipt Print -->
            <a href="<?= url("/invoices/{$invoice['id']}/thermal") ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-black shadow-sm flex items-center gap-1">
                <span>🧾 Thermal POS (80/58mm)</span>
            </a>

            <!-- Standard A4 Print -->
            <a href="<?= url("/invoices/{$invoice['id']}/a4") ?>" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-sm flex items-center gap-1">
                <span>📄 Print A4 Invoice</span>
            </a>

            <!-- Download PDF -->
            <a href="<?= url("/invoices/{$invoice['id']}/pdf?type=a4") ?>" target="_blank" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold border border-slate-300">
                📥 PDF
            </a>
        </div>
    </div>

    <!-- Invoice Header & Summary Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-slate-100">
            <!-- Business Info -->
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-2xl">🥛</span>
                    <h2 class="text-2xl font-black text-slate-900"><?= e($tenant['business_name']) ?></h2>
                </div>
                <p class="text-xs text-slate-600">
                    Owner: <?= e($tenant['owner_name']) ?> • Phone: <a href="tel:<?= e($tenant['phone']) ?>" class="text-blue-600 font-mono"><?= e($tenant['phone']) ?></a>
                </p>
                <?php if (!empty($settings['tax_number'])): ?>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">
                        <?= e($settings['tax_label'] ?? 'GST') ?>IN: <strong><?= e($settings['tax_number']) ?></strong>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Invoice Status & Number -->
            <div class="text-left md:text-right">
                <span class="text-xs px-3 py-1 rounded-full font-black uppercase tracking-wider border <?= \App\Enums\PaymentStatus::from($invoice['payment_status'])->badgeClass() ?>">
                    <?= \App\Enums\PaymentStatus::from($invoice['payment_status'])->label() ?>
                </span>
                <div class="text-xs text-slate-400 font-mono mt-2">Invoice #INV-<?= $invoice['billing_year'] ?>-<?= sprintf('%02d', $invoice['billing_month']) ?>-<?= sprintf('%04d', $invoice['id']) ?></div>
                <div class="text-xs text-slate-500 mt-0.5">Billing Month: <strong><?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?></strong></div>
                <div class="text-xs text-slate-400 mt-0.5">Generated: <?= formatDate($invoice['generated_at'], 'd M Y, h:i A') ?></div>
            </div>
        </div>

        <!-- Customer Bill To Info -->
        <div class="py-6 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <span class="text-xs font-black uppercase text-slate-400 tracking-wider">Bill To Customer</span>
                <div class="flex items-center gap-2 mt-1">
                    <h3 class="text-lg font-black text-slate-900"><?= e($invoice['customer_name']) ?></h3>
                    <?= formatMilkTypesBadge($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?>
                </div>
                <p class="text-xs text-slate-600 mt-0.5"><?= e($invoice['customer_address']) ?></p>
                <p class="text-xs text-slate-500 mt-0.5">Phone: <span class="font-mono"><?= e($invoice['customer_phone']) ?></span> • Route: <span class="font-semibold"><?= e($invoice['route_name'] ?? 'Main Route') ?></span></p>
                <p class="text-xs text-slate-700 font-semibold mt-1">
                    Subscribed: <strong class="text-slate-900"><?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?></strong>
                </p>
            </div>

            <!-- Anti-Error Delivery Stats Summary -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs space-y-1.5">
                <span class="font-black text-slate-800 uppercase tracking-wider block text-[11px] mb-2">Delivery Run-Sheet Verification</span>
                <div class="flex justify-between">
                    <span class="text-slate-600">Total Days in Month:</span>
                    <span class="font-bold text-slate-800"><?= $breakdown['days_in_month'] ?? 30 ?> Days</span>
                </div>
                <div class="flex justify-between text-emerald-700 font-bold">
                    <span>✓ Delivered Days:</span>
                    <span><?= $breakdown['delivered_days'] ?? 0 ?> Days (<?= number_format((float)$invoice['total_units'], 1) ?> Liters)</span>
                </div>
                <div class="flex justify-between text-rose-700 font-bold">
                    <span>✗ Skipped / Missed Days:</span>
                    <span><?= $breakdown['skipped_days'] ?? 0 ?> Days (0.00 L billed)</span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>🏖 Scheduled Leave Days:</span>
                    <span><?= $breakdown['leave_days'] ?? 0 ?> Days (0.00 L billed)</span>
                </div>
            </div>
        </div>

        <!-- 4-Step Formula Breakdown Section (Module 2 Requirement) -->
        <div class="py-6 space-y-4">
            <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <span>🧮 Invoice Calculation Breakdown</span>
            </h3>

            <div class="bg-slate-50/70 rounded-2xl border border-slate-200 p-5 space-y-3 text-sm">
                <!-- Step 1: Base Milk Subtotal -->
                <div class="pb-3 border-b border-slate-200 space-y-2">
                    <div class="font-bold text-slate-900 flex items-center justify-between">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span>1. Base Milk / Product Subtotal</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">
                                <?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?>
                            </span>
                        </div>
                        <div class="font-black text-slate-900 text-base">
                            <?= formatMoney($invoice['milk_subtotal']) ?>
                        </div>
                    </div>
                    <?php if (!empty($breakdown['products'])): ?>
                        <div class="space-y-1.5 pl-3 border-l-2 border-blue-400 my-1">
                            <?php foreach ($breakdown['products'] as $prod): ?>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-800"><?= e($prod['name']) ?>:</span>
                                        <span class="text-slate-500 font-mono"><?= number_format((float)$prod['total_units'], 2) ?> <?= e($prod['unit']) ?> @ <?= formatMoney($prod['unit_price']) ?>/<?= e($prod['unit']) ?></span>
                                    </div>
                                    <div class="font-bold text-slate-900"><?= formatMoney($prod['subtotal']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-slate-500">
                            <?= number_format((float)$invoice['total_units'], 2) ?> Liters delivered × <?= formatMoney($breakdown['customer_rate'] ?? 65.00) ?>/L
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Step 2: Custom Surcharges Line Items -->
                <div class="pb-3 border-b border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-slate-900">2. Additional Charges & Surcharges</div>
                        <div class="font-black text-slate-900"><?= formatMoney($invoice['additional_charges_total']) ?></div>
                    </div>

                    <?php if (!empty($breakdown['charges'])): ?>
                        <div class="pl-3 space-y-1 text-xs">
                            <?php foreach ($breakdown['charges'] as $ch): ?>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>
                                        • <?= e($ch['name']) ?> 
                                        <span class="text-slate-400 italic">(<?= e($ch['note'] ?? '') ?><?= !empty($ch['is_taxable']) ? ', Taxable' : '' ?>)</span>
                                    </span>
                                    <span class="font-bold text-slate-800"><?= formatMoney($ch['amount']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pl-3 text-xs text-slate-400 italic">No additional charges active.</div>
                    <?php endif; ?>
                </div>

                <!-- Step 3: Tax Calculation -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <div class="font-bold text-slate-900">
                            3. Tax (<?= e($breakdown['tax_label'] ?? 'GST') ?> <?= number_format((float)($breakdown['tax_percentage'] ?? 0), 2) ?>%)
                        </div>
                        <div class="text-xs text-slate-500">
                            <?php if (!empty($breakdown['tax_enabled']) && (float)($breakdown['tax_amount'] ?? 0) > 0): ?>
                                Calculated on Taxable Base: <?= formatMoney($breakdown['taxable_base'] ?? 0) ?>
                            <?php else: ?>
                                Taxes not enabled or 0%
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="font-black text-slate-900">
                        <?= formatMoney($invoice['tax_amount']) ?>
                    </div>
                </div>

                <!-- Step 4: Previous Balance & Total -->
                <?php if ((float)$invoice['previous_balance'] > 0): ?>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 text-rose-700">
                        <div>
                            <div class="font-bold">4. Previous Unpaid Dues / Balance</div>
                            <div class="text-xs text-rose-500">Carried forward from previous billing period</div>
                        </div>
                        <div class="font-black text-base">
                            + <?= formatMoney($invoice['previous_balance']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payments Deducted -->
                <?php 
                $paymentsRec = (float)($breakdown['payments_received'] ?? 0.0);
                if ($paymentsRec > 0): 
                ?>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 text-emerald-700">
                        <div>
                            <div class="font-bold">Payments Received / Advances</div>
                            <div class="text-xs text-emerald-600">Deducted from current total payable</div>
                        </div>
                        <div class="font-black text-base">
                            - <?= formatMoney($paymentsRec) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Grand Total -->
                <div class="flex items-center justify-between pt-2 text-lg">
                    <div class="font-black text-slate-900 uppercase">
                        Total Amount Due
                    </div>
                    <div class="font-black text-2xl text-blue-700">
                        <?= formatMoney($invoice['final_amount']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment & UPI QR Code Section -->
        <div class="pt-6 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h4 class="font-black text-sm text-slate-900 mb-1">Payment Instructions</h4>
                <p class="text-xs text-slate-600 max-w-md">
                    <?= e($settings['invoice_footer_note'] ?? 'Please pay via UPI using the QR code or to the milkman in cash on delivery.') ?>
                </p>
                <?php if (!empty($settings['upi_id'])): ?>
                    <p class="text-xs font-mono font-bold text-slate-800 mt-2">
                        UPI ID: <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-300"><?= e($settings['upi_id']) ?></span>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Dynamic UPI QR -->
            <div class="text-center">
                <div class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Scan & Pay via UPI</div>
                <?= \App\Services\QrCodeService::renderQrTag(
                    $settings['upi_qr_path'] ?? null,
                    $settings['upi_id'] ?? null,
                    $tenant['business_name'] ?? 'DairyFlow',
                    (float)$invoice['final_amount'],
                    130,
                    "Milk Bill INV-{$invoice['id']}"
                ) ?>
            </div>
        </div>
    </div>

    <!-- Day-by-Day Delivery Calendar Matrix -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span>📅 Delivery Calendar Matrix (<?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?>)</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Day-by-day calendar matrix with exact milk quantity marked for every date of the billing month.
                </p>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-2 text-xs flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span> Delivered
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 font-bold text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Skipped / Missed
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 font-bold text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-slate-500"></span> Leave (0L)
                </span>
            </div>
        </div>

        <?php
        $matrix = $breakdown['calendar_matrix'] ?? [];
        $firstDayOfWeek = (int)($breakdown['first_day_of_week'] ?? 1);
        $daysInMonth = (int)($breakdown['days_in_month'] ?? count($matrix));
        ?>

        <?php if (!empty($matrix)): ?>
            <!-- 7-Column Calendar Grid -->
            <div class="overflow-x-auto">
                <div class="min-w-[640px]">
                    <!-- Weekday Header -->
                    <div class="grid grid-cols-7 gap-2 text-center text-xs font-black uppercase tracking-wider text-slate-500 mb-2">
                        <div class="p-2 bg-slate-100 rounded-xl">Mon</div>
                        <div class="p-2 bg-slate-100 rounded-xl">Tue</div>
                        <div class="p-2 bg-slate-100 rounded-xl">Wed</div>
                        <div class="p-2 bg-slate-100 rounded-xl">Thu</div>
                        <div class="p-2 bg-slate-100 rounded-xl">Fri</div>
                        <div class="p-2 bg-slate-100 text-amber-700 rounded-xl">Sat</div>
                        <div class="p-2 bg-slate-100 text-rose-700 rounded-xl">Sun</div>
                    </div>

                    <!-- Day Cells -->
                    <div class="grid grid-cols-7 gap-2">
                        <?php 
                        // Empty cells before first day of month
                        for ($pad = 1; $pad < $firstDayOfWeek; $pad++): 
                        ?>
                            <div class="p-2 min-h-[72px] rounded-2xl bg-slate-50/50 border border-dashed border-slate-200/60"></div>
                        <?php endfor; ?>

                        <?php foreach ($matrix as $d => $cell): ?>
                            <?php
                            $st = $cell['status'] ?? 'unrecorded';
                            $qty = (float)($cell['quantity'] ?? 0);
                            $isWeekend = !empty($cell['is_weekend']);
                            
                            $cellBg = match($st) {
                                'delivered' => 'bg-emerald-50/70 border-emerald-300 hover:bg-emerald-100/60',
                                'skipped' => 'bg-rose-50/70 border-rose-300 hover:bg-rose-100/60',
                                'leave' => 'bg-slate-100 border-slate-300 hover:bg-slate-200/60',
                                default => 'bg-white border-slate-200',
                            };
                            ?>
                            <div class="p-2.5 min-h-[72px] rounded-2xl border transition flex flex-col justify-between <?= $cellBg ?>">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-black <?= $isWeekend ? 'text-amber-800' : 'text-slate-700' ?>">
                                        <?= $d ?>
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">
                                        <?= $cell['day_name'] ?? '' ?>
                                    </span>
                                </div>

                                <div class="my-1 text-center">
                                    <?php if ($st === 'delivered'): ?>
                                        <div class="font-black text-sm text-emerald-800">
                                            <?= number_format($qty, 1) ?><span class="text-[10px] font-bold ml-0.5">L</span>
                                        </div>
                                    <?php elseif ($st === 'skipped'): ?>
                                        <div class="text-[11px] font-black text-rose-700">
                                            0.0 L
                                        </div>
                                        <div class="text-[9px] font-bold text-rose-600 uppercase">Skipped</div>
                                    <?php elseif ($st === 'leave'): ?>
                                        <div class="text-[11px] font-black text-slate-600">
                                            0.0 L
                                        </div>
                                        <div class="text-[9px] font-bold text-slate-500 uppercase">Leave</div>
                                    <?php else: ?>
                                        <div class="text-xs text-slate-300 font-bold">-</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($cell['notes'])): ?>
                                    <div class="text-[9px] text-slate-500 truncate text-center" title="<?= e($cell['notes']) ?>">
                                        <?= e($cell['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detailed Itemized Delivery Table -->
        <div class="pt-4 border-t border-slate-100">
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600 mb-3">Itemized Audit Log (Shift Breakdown)</h4>
            <?php $itemized = $breakdown['itemized_deliveries'] ?? []; ?>
            <?php if (empty($itemized)): ?>
                <div class="p-6 text-center text-xs text-slate-500 italic bg-slate-50 rounded-2xl">No delivery records logged in this month.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border border-slate-200 rounded-xl overflow-hidden">
                        <thead class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200 uppercase text-[10px]">
                            <tr>
                                <th class="p-2.5">Date</th>
                                <th class="p-2.5">Shift</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5 text-right">Delivered Qty</th>
                                <th class="p-2.5 text-right">Rate</th>
                                <th class="p-2.5 text-right">Line Amount</th>
                                <th class="p-2.5">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($itemized as $it): ?>
                                <tr class="hover:bg-slate-50 transition <?= $it['status'] === 'skipped' ? 'bg-rose-50/40' : ($it['status'] === 'leave' ? 'bg-slate-50' : '') ?>">
                                    <td class="p-2.5 font-bold font-mono"><?= formatDate($it['date'], 'd M Y') ?></td>
                                    <td class="p-2.5 capitalize font-medium"><?= e($it['shift']) ?></td>
                                    <td class="p-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $it['status'] === 'delivered' ? 'bg-emerald-100 text-emerald-800' : ($it['status'] === 'skipped' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') ?>">
                                            <?= e($it['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-right font-black"><?= number_format((float)$it['quantity'], 1) ?> L</td>
                                    <td class="p-2.5 text-right text-slate-500"><?= formatMoney($breakdown['customer_rate'] ?? 65.00) ?></td>
                                    <td class="p-2.5 text-right font-black <?= (float)$it['amount'] > 0 ? 'text-slate-900' : 'text-slate-400' ?>">
                                        <?= formatMoney($it['amount']) ?>
                                    </td>
                                    <td class="p-2.5 italic text-slate-400"><?= e($it['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payments Ledger Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span>💰 Payments Received for this Invoice</span>
            </h3>
            <button @click="showPaymentModal = true" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow transition">
                + Record Payment
            </button>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-6 text-center text-xs text-slate-500 italic bg-slate-50 rounded-2xl">
                No payments recorded yet for this invoice.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden">
                <?php foreach ($payments as $p): ?>
                    <div class="p-3.5 bg-slate-50/50 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-black text-slate-900"><?= formatMoney($p['amount']) ?></span>
                            <span class="text-slate-500 ml-2">via <?= strtoupper($p['payment_method']) ?></span>
                            <span class="text-slate-400 ml-2 font-mono"><?= formatDate($p['payment_date'], 'd M Y') ?></span>
                            <?php if (!empty($p['reference_no'])): ?>
                                <span class="text-slate-500 ml-2 font-mono">Ref: <?= e($p['reference_no']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['notes'])): ?>
                                <span class="italic text-slate-400 ml-2">(<?= e($p['notes']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-emerald-600 font-bold">✓ Cleared</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Record Payment Modal -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="showPaymentModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 md:p-8 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-900">Record Customer Payment</h3>
                <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
            </div>

            <form method="POST" action="<?= url("/invoices/{$invoice['id']}/payment") ?>" class="space-y-4">
                <?= \App\Core\Auth::csrfField() ?>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Payment Amount (₹) *</label>
                    <input type="number" step="0.5" min="1" name="amount" required value="<?= e($invoice['final_amount']) ?>" class="w-full text-base font-black px-3.5 py-2.5 border-2 border-emerald-400 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Payment Date *</label>
                        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>" class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Method *</label>
                        <select name="payment_method" class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-slate-50">
                            <option value="upi">UPI / QR</option>
                            <option value="cash">Cash in Hand</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Reference / UTR Number</label>
                    <input type="text" name="reference_no" placeholder="e.g. UPI-984321..." class="w-full text-xs px-3.5 py-2 border border-slate-300 rounded-xl">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Notes</label>
                    <input type="text" name="notes" placeholder="e.g. Paid in cash at doorstep" class="w-full text-xs px-3.5 py-2 border border-slate-300 rounded-xl">
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-sm shadow-md transition">
                        ✓ Confirm & Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
