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

        <div class="flex items-center gap-2 flex-wrap">
            <a href="<?= url('/customers/sample-csv') ?>" class="tap-target px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-300 transition flex items-center gap-1.5 whitespace-nowrap" title="Download Sample CSV Template">
                <span>📄 Sample CSV</span>
            </a>

            <a href="<?= url('/customers/export' . (!empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '')) ?>" class="tap-target px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-300 transition flex items-center gap-1.5 whitespace-nowrap" title="Export customers to CSV file">
                <span>📤 Export CSV</span>
            </a>

            <button type="button" onclick="document.getElementById('import-modal').classList.remove('hidden')" class="tap-target px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-300 transition flex items-center gap-1.5 whitespace-nowrap" title="Import customers from CSV file">
                <span>📥 Import CSV</span>
            </button>

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

    <!-- Import Skipped Rows Notification -->
    <?php if (!empty($import_skipped)): ?>
        <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="text-amber-600 font-bold">⚠️ Import Notice:</span>
                <span><?= e($import_skipped) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-amber-600 hover:text-amber-800 font-bold px-1 text-sm">✕</button>
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

            <select name="milk_type" onchange="this.form.submit()" class="w-full sm:w-36 text-xs font-bold py-2 px-3 border border-slate-300 rounded-xl bg-slate-50">
                <option value="">All Milk Types</option>
                <option value="cow" <?= ($selectedMilkType ?? '') === 'cow' ? 'selected' : '' ?>>🐄 Cow Milk</option>
                <option value="buffalo" <?= ($selectedMilkType ?? '') === 'buffalo' ? 'selected' : '' ?>>🐃 Buffalo Milk</option>
                <option value="both" <?= ($selectedMilkType ?? '') === 'both' ? 'selected' : '' ?>>🐄+🐃 Both Milks</option>
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
                                <?= formatMilkTypesBadge($c['milk_types'] ?? 'cow') ?>
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
                            <div class="text-xs text-slate-600 mt-1 flex items-center gap-2 flex-wrap">
                                <?php if (!empty($c['subscriptions'])): ?>
                                    <?php $custTotalQty = array_sum(array_column($c['subscriptions'], 'default_quantity')); ?>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <?php foreach ($c['subscriptions'] as $cs): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-800 text-[11px] font-bold border border-slate-200">
                                                <span><?= $cs['product_code'] === 'buffalo' ? '🐃' : ($cs['product_code'] === 'cow' ? '🐄' : ($cs['product_code'] === 'paneer' ? '🧀' : '🥛')) ?></span>
                                                <span><?= e($cs['product_name']) ?>:</span>
                                                <strong class="text-slate-900"><?= number_format((float)$cs['default_quantity'], 1) ?> <?= e($cs['unit']) ?></strong>
                                                <span class="text-slate-400 font-normal">(@ <?= formatMoney($cs['custom_rate'] ?? $cs['product_default_rate']) ?>)</span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <span>•</span>
                                    <span>Daily Total: <strong class="text-slate-900"><?= number_format((float)$custTotalQty, 1) ?> Units</strong></span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-xs">No active subscriptions</span>
                                <?php endif; ?>
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

<!-- Import Customers CSV Modal -->
<div id="import-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border border-slate-200 relative">
        <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 font-bold text-xl">✕</button>

        <div class="flex items-center gap-3 mb-4">
            <span class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-2xl font-black shrink-0">
                📥
            </span>
            <div>
                <h3 class="text-xl font-black text-slate-900">Import Customers from CSV</h3>
                <p class="text-xs text-slate-500">Bulk upload your customer accounts from any CSV spreadsheet.</p>
            </div>
        </div>

        <!-- Sample Download Box -->
        <div class="bg-blue-50/70 rounded-2xl p-4 border border-blue-200 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="font-bold text-xs text-blue-950 flex items-center gap-1.5">
                    <span>📄 Sample Format Template</span>
                </div>
                <p class="text-[11px] text-blue-800 mt-0.5">
                    Includes sample columns & values for Cow, Buffalo, or Both milks.
                </p>
            </div>
            <a href="<?= url('/customers/sample-csv') ?>" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition inline-flex items-center gap-1.5 whitespace-nowrap self-start sm:self-center">
                <span>⬇️ Download Sample CSV</span>
            </a>
        </div>

        <form method="POST" action="<?= url('/customers/import') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= \App\Core\Auth::csrfField() ?>

            <!-- File Upload Input -->
            <div>
                <label class="block text-xs font-black uppercase text-slate-700 mb-1">
                    Select CSV File <span class="text-rose-500">*</span>
                </label>
                <input type="file" 
                       name="csv_file" 
                       accept=".csv,text/csv" 
                       required
                       class="block w-full text-xs text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 border border-slate-300 rounded-xl bg-slate-50 p-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="text-[10px] text-slate-400 mt-1 block">Standard UTF-8 encoded .csv format</span>
            </div>

            <!-- Update Existing Checkbox -->
            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="update_existing" value="1" checked class="mt-0.5 w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                    <span class="text-xs text-slate-700 font-medium">
                        <strong>Update existing customers</strong> if a matching phone number is found. If unchecked, duplicates are skipped.
                    </span>
                </label>
            </div>

            <!-- Expected Format Columns Guide -->
            <div class="text-[11px] text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                <div class="font-bold text-slate-700 text-[11px]">Supported CSV Columns:</div>
                <div class="font-mono text-[10px] text-slate-600">
                    Name, Phone, Address, Route, Cow Qty (L), Cow Rate, Buffalo Qty (L), Buffalo Rate, Status
                </div>
                <div class="text-[10px] text-slate-500 pt-1">
                    • <strong>Quantities:</strong> Fill Cow Qty / Buffalo Qty for milks taken (leave blank if none).<br>
                    • <strong>Rates:</strong> Leave blank to automatically use your default product catalog rate.<br>
                    • Active quota: <strong><?= $quota['remaining_slots'] ?> remaining slots</strong> on your plan.
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="tap-target flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition text-center">
                    🚀 Upload & Import Customers
                </button>
                <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="tap-target px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold border border-slate-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
