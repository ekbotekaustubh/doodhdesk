<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 flex items-center gap-2">
                <span>⚙️ Business Settings, Taxes & Charges</span>
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Configure your dairy branding, tax rates, UPI payment QR, and custom delivery surcharges.
            </p>
        </div>

        <a href="<?= url('/subscription') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-bold hover:bg-amber-100 transition">
            <span>⚡ Plan & Quota (<?= $quota['active_customers'] ?>/<?= $quota['max_allowed'] ?> Slots)</span>
        </a>
    </div>

    <!-- Section 1: Business Profile & Branding -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
        <h2 class="text-lg font-black text-slate-900 mb-1 flex items-center gap-2">
            <span>🏪 Dairy Profile & Contact Details</span>
        </h2>
        <p class="text-xs text-slate-500 mb-6">Printed at the top of your bills and run-sheets.</p>

        <form method="POST" action="<?= url('/settings/profile') ?>" class="space-y-4">
            <?= \App\Core\Auth::csrfField() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Dairy / Business Name *</label>
                    <input type="text" name="business_name" required value="<?= e($tenant['business_name'] ?? '') ?>" class="w-full text-sm font-bold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Owner Name *</label>
                    <input type="text" name="owner_name" required value="<?= e($tenant['owner_name'] ?? '') ?>" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Contact Phone *</label>
                    <input type="tel" name="phone" required value="<?= e($tenant['phone'] ?? '') ?>" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Email Address</label>
                    <input type="email" name="email" value="<?= e($tenant['email'] ?? '') ?>" class="w-full text-sm font-semibold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="text-right pt-2">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow transition">
                    Save Profile
                </button>
            </div>
        </form>
    </div>

    <!-- Section 2: Tax Configuration & UPI Payment Info -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
        <h2 class="text-lg font-black text-slate-900 mb-1 flex items-center gap-2">
            <span>🏷️ Tax Configuration & UPI Payment Info</span>
        </h2>
        <p class="text-xs text-slate-500 mb-6">Manage GST / VAT calculation rules, GSTIN number, and your UPI payment QR code.</p>

        <form method="POST" action="<?= url('/settings/taxes') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= \App\Core\Auth::csrfField() ?>

            <!-- Tax Enable Toggle -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                <div>
                    <div class="text-sm font-black text-slate-900">Enable Tax on Milk Invoices</div>
                    <div class="text-xs text-slate-500">Apply GST/VAT percentage to subtotal and taxable surcharges.</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="tax_enabled" value="1" <?= !empty($settings['tax_enabled']) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Tax Label</label>
                    <input type="text" name="tax_label" value="<?= e($settings['tax_label'] ?? 'GST') ?>" placeholder="e.g. GST, VAT" class="w-full text-sm font-bold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Tax Rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_percentage" value="<?= e($settings['tax_percentage'] ?? '5.00') ?>" class="w-full text-sm font-bold px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Tax / GSTIN Number</label>
                    <input type="text" name="tax_number" value="<?= e($settings['tax_number'] ?? '') ?>" placeholder="e.g. 24AAAAA0000A1Z5" class="w-full text-sm font-mono px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- UPI ID & QR Code Upload -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">UPI ID for Direct Bill Payments</label>
                    <input type="text" name="upi_id" value="<?= e($settings['upi_id'] ?? '') ?>" placeholder="e.g. yourname@okaxis" class="w-full text-sm font-mono px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                    <p class="text-[11px] text-slate-400 mt-1">If set, a dynamic UPI payment QR code is automatically generated on all printed bills.</p>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-700 mb-1">Or Upload Custom UPI QR Code Image</label>
                    <input type="file" name="upi_qr_file" accept="image/*" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-300 rounded-xl py-1.5 px-2">
                    <?php if (!empty($settings['upi_qr_path'])): ?>
                        <div class="text-[11px] text-emerald-600 font-bold mt-1">✓ Custom QR code uploaded</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoice Footer Note -->
            <div>
                <label class="block text-xs font-black uppercase text-slate-700 mb-1">Invoice Footer Note / Payment Terms</label>
                <textarea name="invoice_footer_note" rows="2" placeholder="e.g. Pure milk delivered daily. Please pay dues before the 5th." class="w-full text-xs px-3.5 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500"><?= e($settings['invoice_footer_note'] ?? '') ?></textarea>
            </div>

            <div class="text-right pt-2">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow transition">
                    Save Tax & UPI Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Section 3: Custom Additional Charges / Surcharges (Module Specification) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm space-y-6">
        <div>
            <h2 class="text-lg font-black text-slate-900 mb-1 flex items-center gap-2">
                <span>➕ Custom Delivery Charges & Surcharges</span>
            </h2>
            <p class="text-xs text-slate-500">
                Configure custom line items (e.g., Monthly Delivery Fee, Glass Bottle Maintenance, Sanitization).
                Surcharges can be calculated as: <strong>Flat Monthly</strong>, <strong>Per Liter</strong>, or <strong>Per Delivery Day</strong>.
            </p>
        </div>

        <!-- Add New Charge Form -->
        <form method="POST" action="<?= url('/settings/charges') ?>" class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
            <?= \App\Core\Auth::csrfField() ?>
            <div class="font-black text-xs text-slate-900 uppercase tracking-wider">Add New Surcharge</div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Charge Name *</label>
                    <input type="text" name="charge_name" required placeholder="e.g. Monthly Bottle Maintenance" class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Calculation Type *</label>
                    <select name="charge_type" class="w-full text-xs font-bold px-3 py-2 border border-slate-300 rounded-xl bg-white">
                        <option value="flat_monthly">Flat / Month (₹)</option>
                        <option value="per_liter">Per Liter Delivered (₹/L)</option>
                        <option value="per_delivery_day">Per Delivery Day (₹/Day)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Amount (₹) *</label>
                    <input type="number" step="0.5" min="0" name="amount" required placeholder="50.00" class="w-full text-xs font-black px-3 py-2 border border-slate-300 rounded-xl bg-white">
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-4 text-xs font-bold text-slate-700">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="is_taxable" value="1" class="rounded text-blue-600">
                        <span>Subject to Tax (GST)</span>
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded text-blue-600">
                        <span>Active</span>
                    </label>
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow transition">
                    + Add Charge
                </button>
            </div>
        </form>

        <!-- Current Custom Charges Table -->
        <div class="divide-y divide-slate-100 border border-slate-200 rounded-2xl overflow-hidden">
            <?php if (empty($charges)): ?>
                <div class="p-6 text-center text-xs text-slate-400 italic">No custom surcharges configured yet.</div>
            <?php else: ?>
                <?php foreach ($charges as $ch): ?>
                    <div class="p-4 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm text-slate-900"><?= e($ch['charge_name']) ?></span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase <?= $ch['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                    <?= $ch['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                                <?php if (!empty($ch['is_taxable'])): ?>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-purple-100 text-purple-800">
                                        Taxable
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                Type: <strong><?= \App\Enums\ChargeType::from($ch['charge_type'])->label() ?></strong>
                                <span class="text-slate-400"> (<?= \App\Enums\ChargeType::from($ch['charge_type'])->description() ?>)</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 self-end sm:self-center">
                            <div class="text-right">
                                <div class="text-sm font-black text-slate-900"><?= formatMoney($ch['amount']) ?></div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold"><?= \App\Enums\ChargeType::from($ch['charge_type'])->shortLabel() ?></div>
                            </div>

                            <form method="POST" action="<?= url("/settings/charges/{$ch['id']}/toggle") ?>">
                                <?= \App\Core\Auth::csrfField() ?>
                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-bold border <?= $ch['is_active'] ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200' ?>">
                                    <?= $ch['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>

                            <form method="POST" action="<?= url("/settings/charges/{$ch['id']}/delete") ?>" onsubmit="return confirm('Delete this surcharge?');">
                                <?= \App\Core\Auth::csrfField() ?>
                                <button type="submit" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 text-xs font-bold">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
