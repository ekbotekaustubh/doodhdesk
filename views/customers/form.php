<?php
$isEdit = !empty($customer);
$title = $isEdit ? "Edit Customer: {$customer['name']}" : "Add New Customer";
$currentMilkTypes = !empty($customer['milk_types']) ? parseMilkTypes($customer['milk_types']) : ['cow'];
$products = $products ?? [];
$subscriptions = $subscriptions ?? [];

// Map existing subscriptions by product_id
$subByProdId = [];
foreach ($subscriptions as $s) {
    $subByProdId[(int)$s['product_id']] = $s;
}

$initialProducts = [];
foreach ($products as $p) {
    $pId = (int)$p['id'];
    $sub = $subByProdId[$pId] ?? null;
    $isActive = false;
    $qty = 1.0;
    $rate = (float)$p['default_rate'];
    $shift = 'both';

    if ($sub) {
        $isActive = (bool)$sub['is_active'];
        $qty = (float)$sub['default_quantity'];
        $rate = (float)($sub['custom_rate'] ?? $p['default_rate']);
        $shift = (string)($sub['shift'] ?? 'both');
    } elseif (!$isEdit) {
        if ($p['code'] === 'cow') {
            $isActive = true;
            $qty = 1.0;
        }
    } elseif ($isEdit) {
        if (in_array($p['code'], $currentMilkTypes, true)) {
            $isActive = true;
            $qty = (float)($customer['default_quantity'] ?? 1.0);
            if (count($currentMilkTypes) > 1) {
                $qty = round($qty / count($currentMilkTypes), 2);
            }
            $rate = (float)($customer['rate_per_unit'] ?? $p['default_rate']);
        }
    }

    $initialProducts[$pId] = [
        'id' => $pId,
        'name' => $p['name'],
        'code' => $p['code'],
        'unit' => $p['unit'],
        'default_rate' => (float)$p['default_rate'],
        'is_active' => $isActive,
        'quantity' => $qty > 0 ? $qty : 1.0,
        'custom_rate' => $rate,
        'shift' => $shift,
    ];
}
?>
<div class="max-w-3xl mx-auto space-y-6">
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
        <p class="text-xs text-slate-500 mb-6">Customer profile, delivery route & multi-product subscriptions with independent quantities.</p>

        <form method="POST" action="<?= $isEdit ? url("/customers/{$customer['id']}") : url('/customers') ?>" 
              x-data="{
                  products: <?= htmlspecialchars(json_encode($initialProducts), ENT_QUOTES, 'UTF-8') ?>,
                  toggleProduct(id) {
                      if (this.products[id]) {
                          this.products[id].is_active = !this.products[id].is_active;
                      }
                  },
                  selectBothMilks() {
                      for (let id in this.products) {
                          if (this.products[id].code === 'cow' || this.products[id].code === 'buffalo') {
                              this.products[id].is_active = true;
                          }
                      }
                  },
                  totalQuantity() {
                      let sum = 0;
                      for (let id in this.products) {
                          if (this.products[id].is_active) {
                              sum += parseFloat(this.products[id].quantity) || 0;
                          }
                      }
                      return sum.toFixed(2);
                  },
                  totalDailyAmount() {
                      let sum = 0;
                      for (let id in this.products) {
                          if (this.products[id].is_active) {
                              sum += (parseFloat(this.products[id].quantity) || 0) * (parseFloat(this.products[id].custom_rate) || 0);
                          }
                      }
                      return sum.toFixed(2);
                  },
                  activeCount() {
                      return Object.values(this.products).filter(p => p.is_active).length;
                  }
              }" 
              class="space-y-6">
            <?= \App\Core\Auth::csrfField() ?>

            <!-- Basic Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Customer Full Name *</label>
                    <input type="text" name="name" required value="<?= e($customer['name'] ?? '') ?>" placeholder="e.g. Ramesh Patel" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Phone / WhatsApp Number *</label>
                    <input type="tel" name="phone" required value="<?= e($customer['phone'] ?? '') ?>" placeholder="e.g. 9876543210" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Delivery Address / Flat No *</label>
                    <textarea name="address" rows="2" placeholder="e.g. Flat 302, Palm Grove Society, Station Road" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500"><?= e($customer['address'] ?? '') ?></textarea>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Route / Area Name</label>
                        <input type="text" name="route_name" value="<?= e($customer['route_name'] ?? 'Main Route') ?>" placeholder="e.g. Green Valley Route" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-slate-50">
                            <option value="active" <?= ($customer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($customer['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Product Subscriptions (Approach A: Normalized Multi-Product Catalog) -->
            <div class="p-5 bg-slate-50 border border-slate-200 rounded-3xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span>📦 Product Subscriptions & Daily Quantities</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Select products. For customers taking both milks, configure distinct quantities & rates for each.
                        </p>
                    </div>
                    <button type="button" @click="selectBothMilks()" class="text-xs font-black text-blue-600 hover:text-blue-800 hover:underline self-start sm:self-auto bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-200 transition">
                        + Subscribed to Both Milks
                    </button>
                </div>

                <!-- Product Subscription Cards -->
                <div class="space-y-3">
                    <?php foreach ($products as $prod): 
                        $pId = (int)$prod['id'];
                        $icon = $prod['code'] === 'buffalo' ? '🐃' : ($prod['code'] === 'cow' ? '🐄' : ($prod['code'] === 'paneer' ? '🧀' : '🥛'));
                    ?>
                        <div class="p-4 rounded-2xl border transition"
                             :class="products[<?= $pId ?>].is_active ? 'bg-white border-blue-400 shadow-sm ring-1 ring-blue-300' : 'bg-slate-100/70 border-slate-200 opacity-75'">
                            
                            <!-- Product Toggle Row -->
                            <div class="flex items-center justify-between mb-3">
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" 
                                           :name="'subscriptions[' + <?= $pId ?> + '][is_active]'" 
                                           value="1" 
                                           x-model="products[<?= $pId ?>].is_active" 
                                           class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                    <span class="text-base"><?= $icon ?></span>
                                    <span class="font-black text-sm text-slate-900"><?= e($prod['name']) ?></span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Unit: <?= e($prod['unit']) ?>
                                    </span>
                                </label>

                                <span class="text-xs font-black text-slate-800" x-show="products[<?= $pId ?>].is_active">
                                    <span x-text="((parseFloat(products[<?= $pId ?>].quantity) || 0) * (parseFloat(products[<?= $pId ?>].custom_rate) || 0)).toFixed(2)"></span>
                                    <span class="text-[10px] text-slate-400">₹/day</span>
                                </span>
                            </div>

                            <!-- Subscription Parameters (Qty, Rate, Shift) -->
                            <div x-show="products[<?= $pId ?>].is_active" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Daily Quantity (<?= e($prod['unit']) ?>) *
                                    </label>
                                    <input type="number" 
                                           step="0.25" 
                                           min="0.25" 
                                           max="100" 
                                           :name="'subscriptions[' + <?= $pId ?> + '][quantity]'" 
                                           x-model="products[<?= $pId ?>].quantity" 
                                           required 
                                           class="w-full text-sm font-black px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 bg-white">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Rate (₹/<?= e($prod['unit']) ?>)
                                    </label>
                                    <input type="number" 
                                           step="0.5" 
                                           min="1" 
                                           max="1000" 
                                           :name="'subscriptions[' + <?= $pId ?> + '][custom_rate]'" 
                                           x-model="products[<?= $pId ?>].custom_rate" 
                                           class="w-full text-sm font-black px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 bg-white">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Delivery Shift
                                    </label>
                                    <select :name="'subscriptions[' + <?= $pId ?> + '][shift]'" 
                                            x-model="products[<?= $pId ?>].shift" 
                                            class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-blue-500">
                                        <option value="both">Both Shifts (Morning & Evening)</option>
                                        <option value="morning">Morning Shift Only</option>
                                        <option value="evening">Evening Shift Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Live Subscriptions Summary Bar -->
                <div class="p-3.5 bg-blue-50/80 border border-blue-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-black text-blue-900">Summary:</span>
                        <span class="text-blue-800">
                            <strong x-text="activeCount()"></strong> active product(s) subscribed
                        </span>
                    </div>
                    <div class="flex items-center gap-4 font-bold text-slate-700">
                        <span>Total Volume: <strong class="text-slate-900" x-text="totalQuantity()"></strong> Units/day</span>
                        <span>•</span>
                        <span>Estimated: <strong class="text-emerald-700 font-black">₹<span x-text="totalDailyAmount()"></span></strong>/day</span>
                    </div>
                </div>

                <!-- Hidden inputs for backward-compatible fallback -->
                <input type="hidden" name="default_quantity" :value="totalQuantity()">
                <input type="hidden" name="rate_per_unit" :value="Object.values(products).find(p => p.is_active) ? Object.values(products).find(p => p.is_active).custom_rate : 65.0">
            </div>

            <!-- Submit Button -->
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
