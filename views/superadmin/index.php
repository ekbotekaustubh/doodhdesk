<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white">SaaS Platform Governance</h1>
            <p class="text-xs text-slate-400 mt-0.5">Global subscription tier rules, tenant monitoring & capacity management</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= url('/demo/tenant1') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-blue-400 text-xs font-bold rounded-xl border border-slate-700">
                🥛 Switch to Krishna Dairy Portal
            </a>
        </div>
    </div>

    <!-- Platform KPI Counters -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Dairies (Tenants)</div>
            <div class="text-3xl font-black text-white mt-1"><?= $totalTenants ?></div>
            <div class="text-xs text-slate-400 mt-1">Multi-tenant data isolation active</div>
        </div>
        <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-purple-400">Total Delivery Customers</div>
            <div class="text-3xl font-black text-purple-300 mt-1"><?= $totalActiveCustomers ?></div>
            <div class="text-xs text-slate-400 mt-1">Active delivery houses managed</div>
        </div>
        <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-400">Platform Subscription Revenue</div>
            <div class="text-3xl font-black text-emerald-400 mt-1"><?= formatMoney($platformRevenue, $systemSettings['currency_symbol']) ?></div>
            <div class="text-xs text-slate-400 mt-1">From paid tier customer blocks</div>
        </div>
    </div>

    <!-- Global SaaS Platform Settings Card (Specification 3.A) -->
    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-6 md:p-8 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-lg font-black text-white flex items-center gap-2">
                    <span>⚙️ Global SaaS Subscription Tiering (system_settings)</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Governs the free quota and the paid capacity block pricing across all dairies.
                </p>
            </div>
        </div>

        <form method="POST" action="<?= url('/admin/settings') ?>" class="space-y-4">
            <?= \App\Core\Auth::csrfField() ?>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <!-- Free Customer Limit -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-300 mb-1">
                        Free Customer Limit
                    </label>
                    <input type="number" min="1" max="1000" name="free_customer_limit" required value="<?= e($systemSettings['free_customer_limit']) ?>" class="w-full text-sm font-black px-3.5 py-2.5 bg-slate-950 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-[10px] text-slate-500 mt-1">Default: 5 active customers</p>
                </div>

                <!-- Paid Tier Unit Size -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-300 mb-1">
                        Paid Tier Block Size
                    </label>
                    <input type="number" min="1" max="10000" name="paid_tier_unit_size" required value="<?= e($systemSettings['paid_tier_unit_size']) ?>" class="w-full text-sm font-black px-3.5 py-2.5 bg-slate-950 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-[10px] text-slate-500 mt-1">Default: 100 customers per block</p>
                </div>

                <!-- Paid Tier Unit Price -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-300 mb-1">
                        Price Per Block (<?= e($systemSettings['currency_symbol']) ?>)
                    </label>
                    <input type="number" step="0.5" min="0" name="paid_tier_unit_price" required value="<?= e($systemSettings['paid_tier_unit_price']) ?>" class="w-full text-sm font-black px-3.5 py-2.5 bg-slate-950 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-[10px] text-slate-500 mt-1">Default: ₹200.00</p>
                </div>

                <!-- Currency Symbol -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-300 mb-1">
                        Currency Symbol
                    </label>
                    <input type="text" name="currency_symbol" required value="<?= e($systemSettings['currency_symbol']) ?>" class="w-full text-sm font-black px-3.5 py-2.5 bg-slate-950 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-[10px] text-slate-500 mt-1">Default: ₹</p>
                </div>
            </div>

            <div class="text-right pt-2">
                <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-black shadow transition">
                    Save Global SaaS Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Tenants Management Directory -->
    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-6 md:p-8 shadow-sm space-y-4">
        <h2 class="text-lg font-black text-white flex items-center gap-2">
            <span>🥛 Registered Tenants (Dairies & Milkmen)</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 font-bold uppercase text-[10px] border-b border-slate-800">
                    <tr>
                        <th class="p-3">Tenant / Business</th>
                        <th class="p-3">Owner Contact</th>
                        <th class="p-3 text-center">Active Customers</th>
                        <th class="p-3 text-center">Quota Limit</th>
                        <th class="p-3 text-center">Plan Status</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($tenants as $t): ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="p-3">
                                <div class="font-bold text-white"><?= e($t['business_name']) ?></div>
                                <div class="text-[10px] text-slate-500">ID: #<?= $t['id'] ?> • Reg: <?= formatDate($t['created_at'], 'd M Y') ?></div>
                            </td>
                            <td class="p-3 font-mono">
                                <div class="text-slate-200"><?= e($t['owner_name']) ?></div>
                                <div class="text-slate-400"><?= e($t['phone']) ?></div>
                            </td>
                            <td class="p-3 text-center font-black text-white">
                                <?= $t['active_customers'] ?>
                            </td>
                            <td class="p-3 text-center font-bold">
                                <?= $t['max_customers_allowed'] ?? 5 ?>
                                <span class="text-[10px] text-slate-500 block">(<?= $t['purchased_blocks'] ?? 0 ?> blocks)</span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= ($t['payment_status'] ?? 'free') === 'active' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700' : 'bg-blue-900/60 text-blue-300 border border-blue-700' ?>">
                                    <?= e($t['payment_status'] ?? 'free') ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $t['status'] === 'active' ? 'bg-emerald-950 text-emerald-400' : 'bg-rose-950 text-rose-400' ?>">
                                    <?= e($t['status']) ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Suspend / Activate -->
                                    <form method="POST" action="<?= url("/admin/tenants/{$t['id']}/toggle-status") ?>">
                                        <?= \App\Core\Auth::csrfField() ?>
                                        <button type="submit" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border <?= $t['status'] === 'active' ? 'border-rose-800 text-rose-400 hover:bg-rose-950' : 'border-emerald-800 text-emerald-400 hover:bg-emerald-950' ?>">
                                            <?= $t['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <!-- Quick Switcher to this Tenant -->
                                    <a href="<?= url("/demo/tenant{$t['id']}") ?>" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-800 text-blue-400 hover:bg-slate-700 border border-slate-700">
                                        Login As
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
