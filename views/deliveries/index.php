<div class="space-y-4">
    <!-- Header Controls: Date Picker, Shift Selector, Route Filter -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-sm space-y-4">
        <!-- Date Selector & Day Navigation -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl md:text-2xl font-black text-slate-900 flex items-center gap-2">
                    <span>📋 Daily Delivery Run-Sheet</span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Zero-Dispute Delivery Tracker • Missed & paused days are auto-excluded from invoices
                </p>
            </div>

            <!-- Date Controls -->
            <form method="GET" action="<?= url('/deliveries') ?>" class="flex items-center gap-1.5 self-start sm:self-auto">
                <input type="hidden" name="shift" value="<?= e($shift) ?>">
                <?php if (!empty($selectedRoute)): ?>
                    <input type="hidden" name="route" value="<?= e($selectedRoute) ?>">
                <?php endif; ?>

                <?php 
                $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
                $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
                $isToday = ($date === date('Y-m-d'));
                ?>
                <a href="<?= url("/deliveries?date={$prevDate}&shift={$shift}&route={$selectedRoute}") ?>" class="tap-target px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 flex items-center justify-center">
                    ◀
                </a>

                <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" class="px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black text-slate-800 focus:ring-2 focus:ring-blue-500">

                <a href="<?= url("/deliveries?date={$nextDate}&shift={$shift}&route={$selectedRoute}") ?>" class="tap-target px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 flex items-center justify-center">
                    ▶
                </a>

                <?php if (!$isToday): ?>
                    <a href="<?= url("/deliveries?date=" . date('Y-m-d') . "&shift={$shift}&route={$selectedRoute}") ?>" class="px-2.5 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold hover:bg-blue-100">
                        Today
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Shift Selector Tabs & Route Filter -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-3 border-t border-slate-100">
            <!-- Shifts Switcher (Morning vs Evening) -->
            <div class="grid grid-cols-2 gap-2 w-full md:w-auto">
                <a href="<?= url("/deliveries?date={$date}&shift=morning&route={$selectedRoute}") ?>" class="tap-target px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 border-2 <?= $shift === 'morning' ? 'bg-amber-500 border-amber-600 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' ?>">
                    <span class="text-base">🌅</span>
                    <span>Morning Shift</span>
                </a>
                <a href="<?= url("/deliveries?date={$date}&shift=evening&route={$selectedRoute}") ?>" class="tap-target px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center justify-center gap-2 border-2 <?= $shift === 'evening' ? 'bg-indigo-600 border-indigo-700 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' ?>">
                    <span class="text-base">🌆</span>
                    <span>Evening Shift</span>
                </a>
            </div>

            <!-- Route Dropdown Filter & Batch Actions -->
            <div class="flex items-center gap-2 w-full md:w-auto">
                <form method="GET" action="<?= url('/deliveries') ?>" class="flex-1 md:w-48">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="shift" value="<?= e($shift) ?>">
                    <select name="route" onchange="this.form.submit()" class="w-full text-xs font-bold py-2.5 px-3 border border-slate-300 rounded-xl bg-slate-50">
                        <option value="">🛣️ All Routes (<?= count($runSheet['items']) ?>)</option>
                        <?php foreach ($runSheet['routes'] as $rName): ?>
                            <option value="<?= e($rName) ?>" <?= $selectedRoute === $rName ? 'selected' : '' ?>>
                                <?= e($rName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Bulk Confirm Delivered for all pending -->
                <?php if ($runSheet['stats']['pending'] > 0): ?>
                    <form method="POST" action="<?= url('/deliveries/bulk-deliver') ?>" onsubmit="return confirm('Mark all <?= $runSheet['stats']['pending'] ?> pending houses as Delivered?');">
                        <?= \App\Core\Auth::csrfField() ?>
                        <input type="hidden" name="date" value="<?= e($date) ?>">
                        <input type="hidden" name="shift" value="<?= e($shift) ?>">
                        <button type="submit" class="tap-target px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow transition flex items-center gap-1.5 whitespace-nowrap">
                            <span>✓ Mark All Delivered</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Shift Verification Alert Banner (Module 1 Requirement) -->
    <?php if (!empty($runSheet['pending_alert'])): ?>
        <div class="p-4 bg-amber-50 border-2 border-amber-300 text-amber-950 rounded-2xl shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">⚠️</span>
                <span class="text-xs sm:text-sm font-black"><?= e($runSheet['pending_alert']) ?></span>
            </div>
            <span class="text-xs bg-amber-200 text-amber-900 font-bold px-2.5 py-1 rounded-lg">
                <?= $runSheet['stats']['pending'] ?> left
            </span>
        </div>
    <?php endif; ?>

    <!-- Run-Sheet Shift Metrics Summary Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm text-center">
        <div class="p-2 rounded-xl bg-blue-50/60 border border-blue-100">
            <div class="text-[10px] font-bold uppercase text-slate-500">Delivered Volume</div>
            <div class="text-xl font-black text-blue-700"><?= number_format($runSheet['stats']['total_liters'], 1) ?> <span class="text-xs">L</span></div>
        </div>
        <div class="p-2 rounded-xl bg-emerald-50/60 border border-emerald-100">
            <div class="text-[10px] font-bold uppercase text-emerald-700">Delivered</div>
            <div class="text-xl font-black text-emerald-700"><?= $runSheet['stats']['delivered'] ?></div>
        </div>
        <div class="p-2 rounded-xl bg-rose-50/60 border border-rose-100">
            <div class="text-[10px] font-bold uppercase text-rose-700">Skipped</div>
            <div class="text-xl font-black text-rose-700"><?= $runSheet['stats']['skipped'] ?></div>
        </div>
        <div class="p-2 rounded-xl bg-slate-100 border border-slate-200">
            <div class="text-[10px] font-bold uppercase text-slate-600">On Vacation</div>
            <div class="text-xl font-black text-slate-700"><?= $runSheet['stats']['leave'] ?></div>
        </div>
        <div class="p-2 rounded-xl bg-amber-50/60 border border-amber-200 col-span-2 sm:col-span-1">
            <div class="text-[10px] font-bold uppercase text-amber-700">Pending</div>
            <div class="text-xl font-black text-amber-700"><?= $runSheet['stats']['pending'] ?></div>
        </div>
    </div>

    <!-- Zero-Charge Rule Reminder Notice -->
    <div class="text-center text-xs text-slate-500 font-medium">
        🛡️ <strong>Zero-Charge Rule Active:</strong> Skipped deliveries or customers on vacation are automatically assigned 0.00L and excluded from the monthly milk invoice.
    </div>

    <!-- Delivery Rows List -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
        <?php if (empty($runSheet['items'])): ?>
            <div class="p-12 text-center">
                <span class="text-4xl">👥</span>
                <h3 class="text-base font-black text-slate-800 mt-2">No Active Customers Found</h3>
                <p class="text-xs text-slate-500 mt-1">Add customers to your route to start tracking daily deliveries.</p>
                <a href="<?= url('/customers/create') ?>" class="inline-flex mt-4 px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold">
                    + Add New Customer
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($runSheet['items'] as $item): ?>
                <?= \App\Core\View::renderPartial('deliveries/_row', [
                    'item' => $item,
                    'date' => $date,
                    'shift' => $shift,
                ]) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
