<div class="space-y-6">
    <!-- Welcome & Shift Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 rounded-2xl p-5 md:p-6 text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-white/20 text-white backdrop-blur">
                    <?= date('l, d F Y') ?>
                </span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-900/60 text-blue-100">
                    <?= $defaultShift === 'morning' ? '🌅 Morning Shift' : '🌆 Evening Shift' ?>
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black tracking-tight">
                <?= e($tenant['business_name']) ?>
            </h1>
            <p class="text-blue-100 text-sm mt-1">
                Welcome back, <strong><?= e($tenant['owner_name']) ?></strong>. Ready for today's deliveries?
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('/deliveries?shift=' . $defaultShift) ?>" class="tap-target px-5 py-3 rounded-xl bg-white text-blue-700 font-black text-sm shadow-md hover:bg-blue-50 transition flex items-center justify-center gap-2">
                <span>📋 Open Run-Sheet</span>
                <span class="text-base">→</span>
            </a>
        </div>
    </div>

    <!-- Pending Verification Alert Banner (Module 1 Requirement) -->
    <?php 
    $activeShiftRunSheet = $defaultShift === 'morning' ? $runSheetMorning : $runSheetEvening;
    if (!empty($activeShiftRunSheet['pending_alert'])): 
    ?>
        <div class="p-4 bg-amber-50 border-2 border-amber-300 text-amber-900 rounded-2xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-pulse">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-xl shrink-0">
                    ⚠️
                </span>
                <div>
                    <h4 class="font-black text-sm text-amber-950">Shift Verification Pending!</h4>
                    <p class="text-xs font-medium text-amber-800">
                        <?= e($activeShiftRunSheet['pending_alert']) ?> Confirm them before the shift closes.
                    </p>
                </div>
            </div>
            <a href="<?= url('/deliveries?shift=' . $defaultShift) ?>" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black shadow text-center shrink-0">
                Verify Now
            </a>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <!-- Card 1: Today Delivered Liters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Today's Milk</span>
                <span class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">🥛</span>
            </div>
            <div>
                <div class="text-2xl md:text-3xl font-black text-slate-900">
                    <?= number_format($todayLiters, 1) ?> <span class="text-sm font-semibold text-slate-500">Liters</span>
                </div>
                <div class="text-[11px] font-semibold text-slate-500 mt-1 flex items-center gap-2">
                    <span>🌅 M: <?= number_format($runSheetMorning['stats']['total_liters'], 1) ?>L</span>
                    <span>•</span>
                    <span>🌆 E: <?= number_format($runSheetEvening['stats']['total_liters'], 1) ?>L</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Customers & Quota Limit -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Active Customers</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-sm">👥</span>
            </div>
            <div>
                <div class="text-2xl md:text-3xl font-black text-slate-900">
                    <?= $quota['active_customers'] ?> <span class="text-sm font-semibold text-slate-500">/ <?= $quota['max_allowed'] ?></span>
                </div>
                <!-- Quota Bar -->
                <div class="w-full bg-slate-100 rounded-full h-2 mt-2 overflow-hidden">
                    <div class="h-full rounded-full <?= $quota['usage_percent'] >= 90 ? 'bg-rose-500' : 'bg-emerald-500' ?>" style="width: <?= $quota['usage_percent'] ?>%"></div>
                </div>
                <div class="text-[11px] font-semibold text-slate-500 mt-1 flex justify-between">
                    <span><?= $quota['usage_percent'] ?>% capacity</span>
                    <a href="<?= url('/subscription') ?>" class="text-blue-600 hover:underline">Upgrade</a>
                </div>
            </div>
        </div>

        <!-- Card 3: Morning Run-Sheet Houses -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Morning Shift</span>
                <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold text-sm">🌅</span>
            </div>
            <div>
                <div class="text-2xl md:text-3xl font-black text-slate-900">
                    <?= $runSheetMorning['stats']['delivered'] ?> <span class="text-sm font-semibold text-slate-500">/ <?= $runSheetMorning['stats']['total_houses'] ?></span>
                </div>
                <div class="text-[11px] font-semibold text-slate-500 mt-1">
                    <span class="text-rose-600 font-bold"><?= $runSheetMorning['stats']['skipped'] ?> Skipped</span> • 
                    <span class="text-slate-600"><?= $runSheetMorning['stats']['leave'] ?> Leave</span> • 
                    <span class="text-amber-600 font-bold"><?= $runSheetMorning['stats']['pending'] ?> Pending</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Monthly Billed & Pending Invoices -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Invoices & Dues</span>
                <span class="w-8 h-8 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-sm">🧾</span>
            </div>
            <div>
                <div class="text-2xl md:text-3xl font-black text-slate-900">
                    <?= formatMoney($monthlyRevenue) ?>
                </div>
                <div class="text-[11px] font-semibold mt-1 <?= $unpaidInvoicesCount > 0 ? 'text-amber-600 font-bold' : 'text-emerald-600' ?>">
                    <?= $unpaidInvoicesCount ?> bills pending collection
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shift Run-Sheet Actions (Thumb-Friendly Tap Area) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <h3 class="text-base font-black text-slate-900 mb-3 flex items-center gap-2">
            <span>⚡ Quick Run-Sheet Navigation</span>
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="<?= url('/deliveries?date=' . $today . '&shift=morning') ?>" class="p-4 rounded-xl border-2 border-amber-200 bg-amber-50/50 hover:bg-amber-100/70 transition flex items-center justify-between group">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🌅</span>
                    <div>
                        <div class="font-black text-slate-900">Morning Shift Run-Sheet</div>
                        <div class="text-xs text-slate-600">
                            <?= $runSheetMorning['stats']['delivered'] ?> delivered, <?= $runSheetMorning['stats']['pending'] ?> pending verification
                        </div>
                    </div>
                </div>
                <span class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-black shadow group-hover:bg-amber-600">Open</span>
            </a>

            <a href="<?= url('/deliveries?date=' . $today . '&shift=evening') ?>" class="p-4 rounded-xl border-2 border-indigo-200 bg-indigo-50/50 hover:bg-indigo-100/70 transition flex items-center justify-between group">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🌆</span>
                    <div>
                        <div class="font-black text-slate-900">Evening Shift Run-Sheet</div>
                        <div class="text-xs text-slate-600">
                            <?= $runSheetEvening['stats']['delivered'] ?> delivered, <?= $runSheetEvening['stats']['pending'] ?> pending verification
                        </div>
                    </div>
                </div>
                <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-black shadow group-hover:bg-indigo-700">Open</span>
            </a>
        </div>
    </div>

    <!-- Recent Deliveries Feed -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span>🕒 Recent Delivery Activity</span>
            </h3>
            <a href="<?= url('/deliveries') ?>" class="text-xs font-bold text-blue-600 hover:underline">View All</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($recentDeliveries)): ?>
                <div class="p-6 text-center text-sm text-slate-500">No deliveries logged yet today.</div>
            <?php else: ?>
                <?php foreach ($recentDeliveries as $rd): ?>
                    <div class="px-5 py-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black <?= $rd['status'] === 'delivered' ? 'bg-emerald-100 text-emerald-700' : ($rd['status'] === 'skipped' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-700') ?>">
                                <?= $rd['status'] === 'delivered' ? '✓' : ($rd['status'] === 'skipped' ? '✗' : '🏖') ?>
                            </span>
                            <div>
                                <div class="font-bold text-sm text-slate-900"><?= e($rd['customer_name']) ?></div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                                    <span><?= e($rd['route_name']) ?></span>
                                    <span>•</span>
                                    <span><?= formatDate($rd['delivery_date'], 'd M') ?> (<?= ucfirst($rd['shift']) ?>)</span>
                                    <?php if (!empty($rd['notes'])): ?>
                                        <span>•</span>
                                        <span class="italic text-slate-400"><?= e($rd['notes']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-black <?= $rd['status'] === 'delivered' ? 'text-emerald-700' : 'text-slate-400' ?>">
                                <?= (float)$rd['quantity'] > 0 ? number_format((float)$rd['quantity'], 1) . ' L' : '0 L' ?>
                            </span>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                <?= e($rd['status']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
