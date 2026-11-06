<div class="space-y-4">
    <!-- Header with Batch Generate Action -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 flex items-center gap-2">
                <span>🧾 Invoices & Billing Engine</span>
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Automated monthly calculation excluding missed days • Custom surcharges & GST included
            </p>
        </div>

        <form method="POST" action="<?= url('/invoices/generate-batch') ?>" onsubmit="return confirm('Generate or recalculate invoices for all customers for <?= sprintf('%02d/%04d', $month, $year) ?>?');" class="flex items-center gap-2">
            <?= \App\Core\Auth::csrfField() ?>
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <button type="submit" class="tap-target px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition flex items-center gap-1.5 whitespace-nowrap">
                <span>⚡ Generate All <?= sprintf('%02d/%04d', $month, $year) ?> Bills</span>
            </button>
        </form>
    </div>

    <!-- Month / Year Selector & Filter Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-3 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <form method="GET" action="<?= url('/invoices') ?>" class="w-full flex flex-wrap items-center gap-2">
            <!-- Month Selector -->
            <select name="month" onchange="this.form.submit()" class="text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <!-- Year Selector -->
            <select name="year" onchange="this.form.submit()" class="text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <!-- Status Filter -->
            <select name="status" onchange="this.form.submit()" class="text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <option value="">All Statuses</option>
                <option value="unpaid" <?= $selectedStatus === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="paid" <?= $selectedStatus === 'paid' ? 'selected' : '' ?>>Paid in Full</option>
                <option value="partial" <?= $selectedStatus === 'partial' ? 'selected' : '' ?>>Partially Paid</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold">
                Apply
            </button>
        </form>
    </div>

    <!-- Summary KPI Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] font-bold uppercase text-slate-500">Total Billed</div>
            <div class="text-xl font-black text-slate-900"><?= formatMoney($totalBilled) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] font-bold uppercase text-emerald-700">Collected</div>
            <div class="text-xl font-black text-emerald-700"><?= formatMoney($totalPaid) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] font-bold uppercase text-amber-700">Outstanding Dues</div>
            <div class="text-xl font-black text-amber-700"><?= formatMoney(max(0, $totalBilled - $totalPaid)) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] font-bold uppercase text-purple-700">Bills Generated</div>
            <div class="text-xl font-black text-purple-700"><?= count($invoices) ?> / <?= $activeCustomersCount ?></div>
        </div>
    </div>

    <!-- Invoices List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
        <?php if (empty($invoices)): ?>
            <div class="p-12 text-center">
                <span class="text-4xl">📄</span>
                <h3 class="text-base font-black text-slate-800 mt-2">No Invoices for <?= sprintf('%02d/%04d', $month, $year) ?></h3>
                <p class="text-xs text-slate-500 mt-1">Click "Generate All Bills" above to calculate and issue invoices for this month.</p>
            </div>
        <?php else: ?>
            <?php foreach ($invoices as $inv): ?>
                <div class="p-4 hover:bg-slate-50 transition flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-full flex items-center justify-center font-black text-base shrink-0 <?= $inv['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($inv['payment_status'] === 'partial' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= $inv['payment_status'] === 'paid' ? '✓' : '₹' ?>
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-base text-slate-900"><?= e($inv['customer_name']) ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                                    <?= e($inv['route_name'] ?? 'Main Route') ?>
                                </span>
                                <?= formatMilkTypesBadge($inv['milk_types'] ?? 'cow') ?>
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold border <?= \App\Enums\PaymentStatus::from($inv['payment_status'])->badgeClass() ?>">
                                    <?= \App\Enums\PaymentStatus::from($inv['payment_status'])->label() ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Phone: <?= e($inv['customer_phone']) ?> • Billed Liters: <strong><?= number_format((float)$inv['total_units'], 1) ?> L</strong>
                            </p>
                            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                                <span>Milk Subtotal: <strong><?= formatMoney($inv['milk_subtotal']) ?></strong></span>
                                <?php if ((float)$inv['additional_charges_total'] > 0): ?>
                                    <span>•</span>
                                    <span>Charges: <strong><?= formatMoney($inv['additional_charges_total']) ?></strong></span>
                                <?php endif; ?>
                                <?php if ((float)$inv['tax_amount'] > 0): ?>
                                    <span>•</span>
                                    <span>Tax: <strong><?= formatMoney($inv['tax_amount']) ?></strong></span>
                                <?php endif; ?>
                                <?php if ((float)$inv['previous_balance'] > 0): ?>
                                    <span>•</span>
                                    <span class="text-rose-600 font-bold">Prev Bal: <?= formatMoney($inv['previous_balance']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Final Amount & Actions -->
                    <div class="flex items-center gap-3 self-end md:self-center">
                        <div class="text-right mr-2">
                            <div class="text-xs text-slate-400 font-semibold uppercase">Total Payable</div>
                            <div class="text-xl font-black text-slate-900"><?= formatMoney($inv['final_amount']) ?></div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <a href="<?= url("/invoices/{$inv['id']}") ?>" class="tap-target px-3 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold border border-blue-200 transition">
                                View Bill
                            </a>
                            <a href="<?= url("/invoices/{$inv['id']}/thermal") ?>" class="tap-target px-2.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition" title="Print Thermal Receipt (80mm/58mm)">
                                🧾
                            </a>
                            <a href="<?= url("/invoices/{$inv['id']}/a4") ?>" class="tap-target px-2.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition" title="Print Standard A4 Tax Invoice">
                                📄
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
