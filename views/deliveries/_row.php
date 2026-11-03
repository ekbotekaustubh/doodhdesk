<?php
/** @var array $item */
/** @var string $date */
/** @var string $shift */

$status = $item['status'] ?? 'pending';
$isOnLeave = !empty($item['is_on_leave']);
$quantity = (float)($item['quantity'] ?? $item['default_quantity']);
$defaultQty = (float)$item['default_quantity'];
$customerId = (int)$item['customer_id'];
?>
<div id="delivery-row-<?= $customerId ?>" class="p-4 bg-white hover:bg-slate-50 transition border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 <?= $isOnLeave ? 'bg-slate-50/80 opacity-90' : '' ?>">
    <!-- Customer Info & Notes -->
    <div class="flex items-start gap-3.5">
        <!-- Status Indicator Ring -->
        <div class="mt-1">
            <?php if ($status === 'delivered'): ?>
                <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center font-black text-lg shadow-sm">
                    ✓
                </span>
            <?php elseif ($status === 'skipped'): ?>
                <span class="w-9 h-9 rounded-full bg-rose-500 text-white flex items-center justify-center font-black text-lg shadow-sm">
                    ✗
                </span>
            <?php elseif ($status === 'leave' || $isOnLeave): ?>
                <span class="w-9 h-9 rounded-full bg-slate-400 text-white flex items-center justify-center font-black text-base shadow-sm">
                    🏖
                </span>
            <?php else: ?>
                <span class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 border-2 border-dashed border-amber-400 flex items-center justify-center font-black text-sm">
                    ?
                </span>
            <?php endif; ?>
        </div>

        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-black text-base text-slate-900"><?= e($item['customer_name']) ?></span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                    <?= e($item['route_name']) ?>
                </span>
                <?php if ($isOnLeave): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-800 font-bold border border-slate-300">
                        🏖 Vacation Active
                    </span>
                <?php endif; ?>
            </div>

            <p class="text-xs text-slate-500 mt-0.5">
                <?= e($item['address']) ?> • <a href="tel:<?= e($item['phone']) ?>" class="text-blue-600 font-mono hover:underline"><?= e($item['phone']) ?></a>
            </p>

            <div class="text-xs text-slate-600 mt-1 flex items-center gap-2">
                <span>Default: <strong class="text-slate-800"><?= number_format($defaultQty, 1) ?> L</strong></span>
                <span>•</span>
                <span>Rate: <strong class="text-slate-800"><?= formatMoney($item['rate_per_unit']) ?>/L</strong></span>
                <?php if (!empty($item['notes'])): ?>
                    <span>•</span>
                    <span class="italic text-slate-500 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                        Note: <?= e($item['notes']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Zero-Charge Rule badge if skipped or leave -->
            <?php if ($status === 'skipped' || $status === 'leave'): ?>
                <div class="mt-1 text-[11px] font-bold text-rose-700 flex items-center gap-1">
                    <span>🛡️ Zero-Charge Applied: 0.00 Liters billed for this shift</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Quantity & Action Buttons (Mobile-first, high sunlight contrast, thumb-friendly tap targets) -->
    <div class="flex flex-wrap items-center gap-2 justify-end self-end md:self-center">
        <!-- Current Recorded Volume Badge -->
        <div class="mr-2 text-right">
            <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Shift Qty</div>
            <div class="text-lg font-black <?= $status === 'delivered' ? 'text-emerald-700' : ($status === 'skipped' ? 'text-rose-600' : 'text-slate-500') ?>">
                <?= number_format($quantity, 1) ?> <span class="text-xs font-bold">L</span>
            </div>
        </div>

        <?php if ($isOnLeave): ?>
            <!-- Auto-greyed out button when on vacation -->
            <button disabled class="tap-target px-4 py-2.5 rounded-xl bg-slate-200 text-slate-500 text-xs font-bold border border-slate-300 cursor-not-allowed flex items-center gap-1.5 shadow-sm">
                <span>🏖 Customer On Leave</span>
            </button>
        <?php else: ?>
            <!-- [Delivered] (Green Button) -->
            <form hx-post="<?= url('/deliveries/toggle') ?>" hx-target="#delivery-row-<?= $customerId ?>" hx-swap="outerHTML" class="inline">
                <?= \App\Core\Auth::csrfField() ?>
                <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="shift" value="<?= e($shift) ?>">
                <input type="hidden" name="status" value="delivered">
                <input type="hidden" name="quantity" value="<?= $quantity > 0 ? $quantity : $defaultQty ?>">
                <button type="submit" class="tap-target px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm <?= $status === 'delivered' ? 'bg-emerald-600 text-white ring-2 ring-emerald-500' : 'bg-emerald-50 text-emerald-800 border-2 border-emerald-300 hover:bg-emerald-100' ?>">
                    <span>✓ Delivered</span>
                </button>
            </form>

            <!-- [Missed / Skipped] (Red Button) -->
            <form hx-post="<?= url('/deliveries/toggle') ?>" hx-target="#delivery-row-<?= $customerId ?>" hx-swap="outerHTML" class="inline">
                <?= \App\Core\Auth::csrfField() ?>
                <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="shift" value="<?= e($shift) ?>">
                <input type="hidden" name="status" value="skipped">
                <input type="hidden" name="quantity" value="0.00">
                <button type="submit" class="tap-target px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm <?= $status === 'skipped' ? 'bg-rose-600 text-white ring-2 ring-rose-500' : 'bg-rose-50 text-rose-800 border-2 border-rose-300 hover:bg-rose-100' ?>">
                    <span>✗ Skipped</span>
                </button>
            </form>

            <!-- [Quantity Edit] (Quick adjustment for extra/less milk on that day) -->
            <div x-data="{ openQty: false, qty: '<?= $quantity > 0 ? $quantity : $defaultQty ?>', note: '<?= e($item['notes'] ?? '') ?>' }" class="relative inline">
                <button @click="openQty = !openQty" type="button" class="tap-target px-3.5 py-2.5 rounded-xl bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-300 text-xs font-bold transition flex items-center gap-1 shadow-sm">
                    <span>✏️ Edit</span>
                </button>

                <!-- Inline Quantity Adjustment Popover -->
                <div x-show="openQty" @click.away="openQty = false" x-cloak class="absolute right-0 bottom-full mb-2 w-72 bg-white rounded-2xl shadow-2xl border-2 border-blue-500 p-4 z-50 text-left">
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-100">
                        <span class="text-xs font-black text-slate-900">Adjust Milk Qty (Liters)</span>
                        <button @click="openQty = false" type="button" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
                    </div>

                    <form hx-post="<?= url('/deliveries/edit-quantity') ?>" hx-target="#delivery-row-<?= $customerId ?>" hx-swap="outerHTML" @submit="openQty = false">
                        <?= \App\Core\Auth::csrfField() ?>
                        <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                        <input type="hidden" name="date" value="<?= e($date) ?>">
                        <input type="hidden" name="shift" value="<?= e($shift) ?>">

                        <!-- Quick Increment/Decrement Buttons -->
                        <div class="flex items-center justify-center gap-3 mb-3">
                            <button type="button" @click="qty = Math.max(0, (parseFloat(qty) - 0.5)).toFixed(1)" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-lg border border-slate-300 flex items-center justify-center">
                                -0.5
                            </button>
                            <input type="number" step="0.25" min="0" max="100" name="quantity" x-model="qty" class="w-24 text-center font-black text-xl py-1.5 border-2 border-blue-400 rounded-xl focus:ring-2 focus:ring-blue-500">
                            <button type="button" @click="qty = (parseFloat(qty) + 0.5).toFixed(1)" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-lg border border-slate-300 flex items-center justify-center">
                                +0.5
                            </button>
                        </div>

                        <!-- Optional Note -->
                        <div class="mb-3">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Reason / Note (e.g. Extra 1L for festival)</label>
                            <input type="text" name="notes" x-model="note" placeholder="Reason for change..." class="w-full text-xs px-2.5 py-1.5 border border-slate-300 rounded-lg">
                        </div>

                        <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow transition">
                            Save Adjusted Volume
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
