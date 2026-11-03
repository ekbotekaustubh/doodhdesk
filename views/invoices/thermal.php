<?php
/** @var array $invoice */
/** @var array $breakdown */
/** @var array $tenant */
/** @var array $settings */
$format = $format ?? '80mm'; // '80mm' or '58mm'
$widthClass = $format === '58mm' ? 'max-w-[240px]' : 'max-w-[320px]';
?>
<!-- Thermal Receipt Format Switcher (Hidden when printing) -->
<div class="no-print max-w-sm mx-auto mb-4 flex items-center justify-center gap-2">
    <span class="text-xs font-bold text-slate-500">Receipt Width:</span>
    <a href="<?= url("/invoices/{$invoice['id']}/thermal?format=80mm") ?>" class="px-3 py-1 text-xs font-bold rounded-lg border <?= $format === '80mm' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">
        80mm Standard POS
    </a>
    <a href="<?= url("/invoices/{$invoice['id']}/thermal?format=58mm") ?>" class="px-3 py-1 text-xs font-bold rounded-lg border <?= $format === '58mm' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">
        58mm Mini POS
    </a>
    <a href="<?= url("/invoices/{$invoice['id']}/pdf?type=thermal80") ?>" target="_blank" class="px-2 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800">
        PDF
    </a>
</div>

<!-- Receipt Paper Area -->
<div class="<?= $widthClass ?> mx-auto bg-white p-4 shadow-md border border-slate-300 font-mono text-[11px] leading-tight text-black print-shadow-none">
    <!-- Header -->
    <div class="text-center pb-2 border-b border-dashed border-black space-y-0.5">
        <div class="text-sm font-black uppercase"><?= e($tenant['business_name']) ?></div>
        <div>Owner: <?= e($tenant['owner_name']) ?></div>
        <div>Tel: <?= e($tenant['phone']) ?></div>
        <?php if (!empty($settings['tax_number'])): ?>
            <div><?= e($settings['tax_label'] ?? 'GST') ?>: <?= e($settings['tax_number']) ?></div>
        <?php endif; ?>
        <div class="font-bold pt-1">*** MILK DELIVERY BILL ***</div>
    </div>

    <!-- Bill Meta -->
    <div class="py-2 border-b border-dashed border-black space-y-0.5">
        <div class="flex justify-between">
            <span>Bill #: INV-<?= $invoice['id'] ?></span>
            <span><?= date('d/m/y', strtotime($invoice['generated_at'])) ?></span>
        </div>
        <div class="flex justify-between">
            <span>Month: <?= date('M Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?></span>
            <span class="font-bold"><?= strtoupper($invoice['payment_status']) ?></span>
        </div>
        <div class="pt-1 font-bold">Customer: <?= e($invoice['customer_name']) ?></div>
        <div class="truncate">Phone: <?= e($invoice['customer_phone']) ?></div>
        <div class="truncate text-[10px]">Route: <?= e($invoice['route_name'] ?? 'Main') ?></div>
    </div>

    <!-- Delivery Tracking Summary (Anti-Error Assurance) -->
    <div class="py-2 border-b border-dashed border-black space-y-0.5 text-[10px]">
        <div class="font-bold uppercase">Deliveries Summary:</div>
        <div class="flex justify-between">
            <span>Delivered Days:</span>
            <span><?= $breakdown['delivered_days'] ?? 0 ?> Days</span>
        </div>
        <div class="flex justify-between text-black">
            <span>Skipped / Leave Days:</span>
            <span><?= ($breakdown['skipped_days'] ?? 0) + ($breakdown['leave_days'] ?? 0) ?> Days (0L)</span>
        </div>
        <div class="flex justify-between font-bold">
            <span>Total Delivered Liters:</span>
            <span><?= number_format((float)$invoice['total_units'], 1) ?> L</span>
        </div>
    </div>

    <!-- Charges Breakdown -->
    <div class="py-2 border-b border-dashed border-black space-y-1">
        <div class="flex justify-between">
            <div>
                <div class="font-bold">Pure Milk Total</div>
                <div class="text-[9px]"><?= number_format((float)$invoice['total_units'], 1) ?>L @ <?= formatMoney($breakdown['customer_rate'] ?? 65) ?>/L</div>
            </div>
            <div class="font-bold"><?= formatMoney($invoice['milk_subtotal']) ?></div>
        </div>

        <!-- Custom Surcharges -->
        <?php if (!empty($breakdown['charges'])): ?>
            <?php foreach ($breakdown['charges'] as $ch): ?>
                <div class="flex justify-between text-[10px]">
                    <div class="truncate max-w-[150px]">+ <?= e($ch['name']) ?></div>
                    <div><?= formatMoney($ch['amount']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Taxes -->
        <?php if (!empty($breakdown['tax_enabled']) && (float)$invoice['tax_amount'] > 0): ?>
            <div class="flex justify-between text-[10px]">
                <div>+ <?= e($breakdown['tax_label'] ?? 'GST') ?> (<?= (float)($breakdown['tax_percentage'] ?? 0) ?>%)</div>
                <div><?= formatMoney($invoice['tax_amount']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Previous Balance -->
        <?php if ((float)$invoice['previous_balance'] > 0): ?>
            <div class="flex justify-between text-[10px]">
                <div>+ Previous Balance:</div>
                <div><?= formatMoney($invoice['previous_balance']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Payments Received -->
        <?php 
        $paymentsRec = (float)($breakdown['payments_received'] ?? 0.0);
        if ($paymentsRec > 0): 
        ?>
            <div class="flex justify-between text-[10px]">
                <div>- Payments Received:</div>
                <div>-<?= formatMoney($paymentsRec) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Total Payable -->
    <div class="py-2 border-b-2 border-black flex justify-between items-center text-xs font-black">
        <span>TOTAL DUE:</span>
        <span class="text-sm"><?= formatMoney($invoice['final_amount']) ?></span>
    </div>

    <!-- QR Code & Footer -->
    <div class="pt-3 text-center space-y-2">
        <div class="text-[10px] font-bold">SCAN & PAY VIA UPI</div>
        <div class="flex justify-center">
            <?= \App\Services\QrCodeService::renderQrTag(
                $settings['upi_qr_path'] ?? null,
                $settings['upi_id'] ?? null,
                $tenant['business_name'] ?? 'DairyFlow',
                (float)$invoice['final_amount'],
                100,
                "Milk INV-{$invoice['id']}"
            ) ?>
        </div>
        <?php if (!empty($settings['upi_id'])): ?>
            <div class="text-[10px]">UPI: <?= e($settings['upi_id']) ?></div>
        <?php endif; ?>
        <div class="text-[9px] pt-1 italic text-slate-700">
            <?= e($settings['invoice_footer_note'] ?? 'Thank you! Pure milk daily.') ?>
        </div>
        <div class="text-[8px] text-slate-500 pt-1">
            DairyFlow Zero-Dispute Invoicing
        </div>
    </div>
</div>
