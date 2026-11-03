<?php
$isEdit = !empty($customer);
$title = $isEdit ? "Edit Customer: {$customer['name']}" : "Add New Customer";
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="<?= url('/customers') ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900">
            ← Back to Customer Directory
        </a>
        <span class="text-xs font-bold text-slate-500">
            Quota Slots Remaining: <strong class="text-blue-600"><?= $quota['remaining_slots'] ?></strong>
        </span>
    </div>

    <!-- Main Customer Form Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
        <h1 class="text-xl md:text-2xl font-black text-slate-900 mb-1"><?= e($title) ?></h1>
        <p class="text-xs text-slate-500 mb-6">Customer details, delivery route, daily milk quantity & price rate.</p>

        <form method="POST" action="<?= $isEdit ? url("/customers/{$customer['id']}") : url('/customers') ?>" class="space-y-4">
            <?= \App\Core\Auth::csrfField() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Customer Full Name *</label>
                    <input type="text" name="name" required value="<?= e($customer['name'] ?? '') ?>" placeholder="e.g. Ramesh Patel" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Phone / WhatsApp Number *</label>
                    <input type="tel" name="phone" required value="<?= e($customer['phone'] ?? '') ?>" placeholder="e.g. 9876543210" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Address -->
            <div>
                <label class="block text-xs font-black uppercase text-slate-700 mb-1">Delivery Address / Flat No *</label>
                <textarea name="address" rows="2" placeholder="e.g. Flat 302, Palm Grove Society, Station Road" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500"><?= e($customer['address'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Route Name -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Route / Area Name</label>
                    <input type="text" name="route_name" value="<?= e($customer['route_name'] ?? 'Main Route') ?>" placeholder="e.g. Green Valley Route" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Default Quantity -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Default Qty (Liters) *</label>
                    <input type="number" step="0.25" min="0.25" max="100" name="default_quantity" required value="<?= e($customer['default_quantity'] ?? '1.00') ?>" class="w-full text-sm font-black px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Rate per Liter -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Milk Rate (₹/Liter) *</label>
                    <input type="number" step="0.5" min="1" max="1000" name="rate_per_unit" required value="<?= e($customer['rate_per_unit'] ?? '65.00') ?>" class="w-full text-sm font-black px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-xs font-black uppercase text-slate-700 mb-1">Customer Account Status</label>
                <select name="status" class="w-full text-sm font-bold px-3.5 py-2.5 border border-slate-300 rounded-xl bg-slate-50">
                    <option value="active" <?= ($customer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Included in Daily Deliveries & Quota)</option>
                    <option value="inactive" <?= ($customer['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Delivery Stopped)</option>
                </select>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <a href="<?= url('/customers') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" class="tap-target px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition">
                    <?= $isEdit ? 'Save Changes' : 'Create Customer' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Vacations / Leaves Section (Available when Editing) -->
    <?php if ($isEdit): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                        <span>🏖 Customer Vacations & Delivery Pauses</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Deliveries during these dates will automatically be marked with 0.00L (Zero-Charge rule applied).
                    </p>
                </div>
            </div>

            <!-- Add Vacation Form -->
            <form method="POST" action="<?= url("/customers/{$customer['id']}/leaves") ?>" class="bg-amber-50/60 p-4 rounded-2xl border border-amber-200 space-y-3">
                <?= \App\Core\Auth::csrfField() ?>
                <div class="font-black text-xs text-amber-900 uppercase tracking-wider">Schedule Vacation / Delivery Pause</div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Start Date *</label>
                        <input type="date" name="start_date" required class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">End Date *</label>
                        <input type="date" name="end_date" required class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Reason / Note</label>
                        <input type="text" name="reason" placeholder="e.g. Out of town" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-xl bg-white">
                    </div>
                </div>

                <div class="text-right">
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black shadow transition">
                        + Record Vacation Pause
                    </button>
                </div>
            </form>

            <!-- Existing Leaves List -->
            <div>
                <h4 class="text-xs font-black uppercase text-slate-500 tracking-wider mb-2">Scheduled Vacations</h4>
                <?php if (empty($leaves)): ?>
                    <p class="text-xs text-slate-400 italic">No vacation schedules recorded for this customer.</p>
                <?php else: ?>
                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden">
                        <?php foreach ($leaves as $l): ?>
                            <div class="p-3.5 bg-slate-50/50 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-black text-slate-800">
                                        <?= formatDate($l['start_date'], 'd M Y') ?> — <?= formatDate($l['end_date'], 'd M Y') ?>
                                    </span>
                                    <span class="text-slate-500 ml-2 italic">(<?= e($l['reason'] ?? 'Pause') ?>)</span>
                                </div>
                                <form method="POST" action="<?= url("/customers/{$customer['id']}/leaves/{$l['id']}/delete") ?>" onsubmit="return confirm('Remove this vacation schedule?');">
                                    <?= \App\Core\Auth::csrfField() ?>
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold px-2 py-1">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
