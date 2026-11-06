<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="<?= url('/settings') ?>" class="text-xs font-bold text-slate-600 hover:text-slate-900">
            ← Back to Business Settings
        </a>
    </div>

    <!-- Current Subscription Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
            <div>
                <span class="text-xs px-2.5 py-1 rounded-full font-black uppercase tracking-wider <?= $quota['payment_status'] === 'active' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-blue-100 text-blue-800 border border-blue-300' ?>">
                    <?= ucfirst($quota['payment_status']) ?> Subscription Plan
                </span>
                <h1 class="text-2xl font-black text-slate-900 mt-2">SaaS Quota & Capacity</h1>
                <p class="text-xs text-slate-500 mt-0.5">Scale your dairy deliveries as your customer base expands.</p>
            </div>

            <div class="text-left sm:text-right">
                <div class="text-xs text-slate-400 font-bold uppercase">Customer Capacity</div>
                <div class="text-3xl font-black text-slate-900">
                    <?= $quota['active_customers'] ?> <span class="text-sm font-semibold text-slate-500">/ <?= $quota['max_allowed'] ?> Slots</span>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="py-6 border-b border-slate-100">
            <div class="flex justify-between text-xs font-bold text-slate-700 mb-2">
                <span>Capacity Utilized: <?= $quota['usage_percent'] ?>%</span>
                <span class="<?= $quota['remaining_slots'] <= 1 ? 'text-rose-600' : 'text-emerald-600' ?>">
                    <?= $quota['remaining_slots'] ?> Slots Remaining
                </span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-3.5 overflow-hidden p-0.5 border border-slate-200">
                <div class="h-full rounded-full transition-all duration-500 <?= $quota['usage_percent'] >= 90 ? 'bg-rose-500' : 'bg-emerald-500' ?>" style="width: <?= $quota['usage_percent'] ?>%"></div>
            </div>
        </div>

        <!-- Quota Rule Explanation -->
        <div class="py-6 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-black text-slate-400">Free Tier Allowance</div>
                <div class="text-xl font-black text-slate-900 mt-1"><?= $quota['free_limit'] ?> Customers</div>
                <p class="text-slate-500 mt-0.5">Free forever for startup milkmen.</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-black text-slate-400">Purchased Blocks</div>
                <div class="text-xl font-black text-slate-900 mt-1"><?= $quota['purchased_blocks'] ?> Blocks</div>
                <p class="text-slate-500 mt-0.5">+<?= $quota['purchased_blocks'] * $quota['unit_size'] ?> additional customer slots.</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-black text-slate-400">Block Pricing</div>
                <div class="text-xl font-black text-blue-700 mt-1"><?= formatMoney($quota['unit_price'], $quota['currency_symbol']) ?></div>
                <p class="text-slate-500 mt-0.5">per <?= $quota['unit_size'] ?> active customers.</p>
            </div>
        </div>

        <!-- Upgrade Capacity Form -->
        <div class="pt-6">
            <h3 class="text-base font-black text-slate-900 mb-2">⚡ Purchase Additional Customer Capacity</h3>
            <p class="text-xs text-slate-500 mb-4">Select the number of capacity blocks to instantly expand your customer allowance.</p>

            <form method="POST" action="<?= url('/subscription/upgrade') ?>" class="bg-gradient-to-tr from-slate-50 to-blue-50/50 p-6 rounded-2xl border border-blue-200 space-y-4">
                <?= \App\Core\Auth::csrfField() ?>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="p-4 rounded-xl border-2 border-slate-200 bg-white hover:border-blue-500 cursor-pointer transition flex flex-col justify-between">
                        <div>
                            <input type="radio" name="blocks" value="1" checked class="text-blue-600 mb-2">
                            <div class="font-black text-slate-900 text-sm">+<?= $quota['unit_size'] ?> Customers</div>
                            <div class="text-xs text-slate-500">1 Block</div>
                        </div>
                        <div class="font-black text-base text-blue-700 mt-3"><?= formatMoney($quota['unit_price'] * 1, $quota['currency_symbol']) ?></div>
                    </label>

                    <label class="p-4 rounded-xl border-2 border-slate-200 bg-white hover:border-blue-500 cursor-pointer transition flex flex-col justify-between">
                        <div>
                            <input type="radio" name="blocks" value="2" class="text-blue-600 mb-2">
                            <div class="font-black text-slate-900 text-sm">+<?= $quota['unit_size'] * 2 ?> Customers</div>
                            <div class="text-xs text-slate-500">2 Blocks</div>
                        </div>
                        <div class="font-black text-base text-blue-700 mt-3"><?= formatMoney($quota['unit_price'] * 2, $quota['currency_symbol']) ?></div>
                    </label>

                    <label class="p-4 rounded-xl border-2 border-slate-200 bg-white hover:border-blue-500 cursor-pointer transition flex flex-col justify-between">
                        <div>
                            <input type="radio" name="blocks" value="5" class="text-blue-600 mb-2">
                            <div class="font-black text-slate-900 text-sm">+<?= $quota['unit_size'] * 5 ?> Customers</div>
                            <div class="text-xs text-slate-500">5 Blocks</div>
                        </div>
                        <div class="font-black text-base text-blue-700 mt-3"><?= formatMoney($quota['unit_price'] * 5, $quota['currency_symbol']) ?></div>
                    </label>
                </div>

                <button type="submit" class="tap-target w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition">
                    ⚡ Confirm & Activate Capacity Block
                </button>
            </form>
        </div>
    </div>
</div>
