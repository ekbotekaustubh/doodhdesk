<div class="space-y-4">
    <!-- Header with Quota Status and Add Customer Button -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl md:text-2xl font-black text-slate-900">👥 Customer Directory</h1>
                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold <?= $quota['is_at_limit'] ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' ?>">
                    <?= $quota['active_customers'] ?> / <?= $quota['max_allowed'] ?> Slots Used
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
                Manage your delivery customer accounts, routes, milk rates, and vacation schedules.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <?php if ($quota['can_add']): ?>
                <a href="<?= url('/customers/create') ?>" class="tap-target px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition flex items-center gap-1.5 whitespace-nowrap">
                    <span>+ Add Customer</span>
                </a>
            <?php else: ?>
                <!-- Quota Paywall Trigger Button -->
                <button onclick="document.getElementById('paywall-modal').classList.remove('hidden')" class="tap-target px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl text-xs font-black shadow-md transition flex items-center gap-1.5 whitespace-nowrap">
                    <span>⚡ Quota Full - Upgrade</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quota Warning Banner if Near or At Limit -->
    <?php if ($quota['is_at_limit']): ?>
        <div class="p-4 bg-rose-50 border-2 border-rose-300 text-rose-900 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🛑</span>
                <div>
                    <h4 class="font-black text-sm text-rose-950">Subscription Limit Reached!</h4>
                    <p class="text-xs text-rose-800">
                        You have reached your current maximum of <strong><?= $quota['max_allowed'] ?> active customers</strong> on your <?= ucfirst($quota['payment_status']) ?> tier. Upgrade now to add more delivery stops.
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('paywall-modal').classList.remove('hidden')" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-black shadow text-center shrink-0">
                Upgrade Capacity
            </button>
        </div>
    <?php elseif ($quota['is_near_limit']): ?>
        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl flex items-center justify-between text-xs font-bold">
            <span>⚡ You only have <?= $quota['remaining_slots'] ?> customer slot remaining in your plan.</span>
            <a href="<?= url('/subscription') ?>" class="text-blue-700 hover:underline">Add More Slots →</a>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-3 shadow-sm flex flex-col sm:flex-row items-center gap-2">
        <form method="GET" action="<?= url('/customers') ?>" class="w-full flex flex-col sm:flex-row items-center gap-2">
            <div class="relative w-full sm:flex-1">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, phone, address..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>

            <select name="route" onchange="this.form.submit()" class="w-full sm:w-44 text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <option value="">All Routes</option>
                <?php foreach ($routes as $r): ?>
                    <option value="<?= e($r) ?>" <?= $selectedRoute === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status" onchange="this.form.submit()" class="w-full sm:w-36 text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <option value="">All Statuses</option>
                <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
            </select>

            <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold">
                Filter
            </button>
        </form>
    </div>

    <!-- Customer Cards / Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
        <?php if (empty($customers)): ?>
            <div class="p-12 text-center">
                <span class="text-4xl">🔍</span>
                <h3 class="text-base font-black text-slate-800 mt-2">No Customers Found</h3>
                <p class="text-xs text-slate-500 mt-1">Try resetting your filters or add a new customer.</p>
            </div>
        <?php else: ?>
            <?php foreach ($customers as $c): ?>
                <div class="p-4 hover:bg-slate-50 transition flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm shrink-0 <?= $c['status'] === 'active' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600' ?>">
                            <?= strtoupper(substr($c['name'], 0, 1)) ?>
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-base text-slate-900"><?= e($c['name']) ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                                    <?= e($c['route_name'] ?? 'Main Route') ?>
                                </span>
                                <?php if ($c['status'] === 'inactive'): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-bold">Inactive</span>
                                <?php endif; ?>
                                <?php if (!empty($c['is_currently_on_leave'])): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold border border-amber-300">
                                        🏖 On Vacation
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <?= e($c['address']) ?> • <a href="tel:<?= e($c['phone']) ?>" class="text-blue-600 font-mono hover:underline"><?= e($c['phone']) ?></a>
                            </p>
                            <div class="text-xs text-slate-600 mt-1 flex items-center gap-2">
                                <span>Default: <strong class="text-slate-900"><?= number_format((float)$c['default_quantity'], 1) ?> L/day</strong></span>
                                <span>•</span>
                                <span>Rate: <strong class="text-slate-900"><?= formatMoney($c['rate_per_unit']) ?>/L</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 self-end md:self-center">
                        <a href="<?= url("/customers/{$c['id']}/edit") ?>" class="tap-target px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 transition flex items-center gap-1 shadow-sm">
                            <span>✏️ Edit & Vacations</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Quota Upgrade Paywall Modal (Module 3 Specification) -->
<div id="paywall-modal" class="<?= ($paywall ?? '') === 'limit_reached' ? '' : 'hidden' ?> fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border-4 border-amber-400 relative">
        <button onclick="document.getElementById('paywall-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 font-bold text-xl">✕</button>

        <div class="text-center mb-6">
            <span class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-400 to-orange-500 text-white flex items-center justify-center mx-auto text-3xl font-black shadow-lg mb-3">
                ⚡
            </span>
            <h3 class="text-2xl font-black text-slate-900">Customer Quota Exceeded</h3>
            <p class="text-sm text-slate-600 mt-1">
                You currently have <strong><?= $quota['active_customers'] ?> active customers</strong>, which reaches your maximum limit of <strong><?= $quota['max_allowed'] ?></strong>.
            </p>
        </div>

        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-200 mb-6 text-xs text-amber-950 space-y-2">
            <div class="flex justify-between font-bold">
                <span>Free Plan Allowance:</span>
                <span><?= $quota['free_limit'] ?> Customers</span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Paid Capacity Added:</span>
                <span><?= $quota['purchased_blocks'] * $quota['unit_size'] ?> Customers (<?= $quota['purchased_blocks'] ?> Blocks)</span>
            </div>
            <div class="flex justify-between text-base font-black border-t border-amber-200 pt-2 text-slate-900">
                <span>Current Total Limit:</span>
                <span><?= $quota['max_allowed'] ?> Customers</span>
            </div>
        </div>

        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 mb-6 text-center">
            <span class="text-xs uppercase tracking-wider font-black text-blue-700 bg-blue-100 px-3 py-1 rounded-full">Recommended Upgrade</span>
            <h4 class="text-xl font-black text-slate-900 mt-3">+<?= $quota['unit_size'] ?> Customer Capacity Block</h4>
            <div class="text-3xl font-black text-slate-900 mt-1">
                <?= formatMoney($quota['unit_price'], $quota['currency_symbol']) ?> <span class="text-xs font-semibold text-slate-500">/ block</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Instant activation • Keep expanding your delivery routes without interruptions</p>
        </div>

        <form method="POST" action="<?= url('/subscription/upgrade') ?>">
            <?= \App\Core\Auth::csrfField() ?>
            <input type="hidden" name="blocks" value="1">
            <button type="submit" class="tap-target w-full py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl font-black text-sm shadow-xl transition">
                ⚡ Upgrade Capacity (+<?= $quota['unit_size'] ?> Slots for <?= formatMoney($quota['unit_price'], $quota['currency_symbol']) ?>)
            </button>
        </form>
    </div>
</div>
