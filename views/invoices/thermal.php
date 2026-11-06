<?php
/** @var array $invoice */
/** @var array $breakdown */
/** @var array $tenant */
/** @var array $settings */
$isPdf = !empty($isPdf);
$format = $format ?? '80mm'; // '80mm' or '58mm'
$widthPx = $format === '58mm' ? 190 : 260;
?>
<?php if ($isPdf): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Thermal Bill #INV-<?= $invoice['id'] ?></title>
<style>
    @page {
        margin: 4mm;
        size: <?= $format === '58mm' ? '58mm 250mm' : '80mm 250mm' ?>;
    }
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0;
        padding: 0;
        font-family: 'DejaVu Sans Mono', Consolas, monospace;
        font-size: 9px;
        line-height: 1.25;
        color: #000000;
        background: #ffffff;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .border-b-dashed { border-bottom: 1px dashed #000000; }
    .py-1 { padding-top: 3px; padding-bottom: 3px; }
    .py-2 { padding-top: 5px; padding-bottom: 5px; }
</style>
</head>
<body>
<?php else: ?>
<!-- Thermal Receipt Format Switcher (Hidden when printing) -->
<div class="no-print max-w-sm mx-auto mb-4 flex items-center justify-center gap-2">
    <span class="text-xs font-bold text-slate-500">Receipt Width:</span>
    <a href="<?= url("/invoices/{$invoice['id']}/thermal?format=80mm") ?>" class="px-3 py-1 text-xs font-bold rounded-lg border <?= $format === '80mm' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">
        80mm Standard POS
    </a>
    <a href="<?= url("/invoices/{$invoice['id']}/thermal?format=58mm") ?>" class="px-3 py-1 text-xs font-bold rounded-lg border <?= $format === '58mm' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700' ?>">
        58mm Mini POS
    </a>
    <a href="<?= url("/invoices/{$invoice['id']}/pdf?type=thermal" . ($format === '58mm' ? '58' : '80')) ?>" target="_blank" class="px-2 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800">
        📥 PDF
    </a>
</div>

<style>
    .thermal-box {
        width: <?= $widthPx ?>px;
        margin: 0 auto;
        background: #ffffff;
        padding: 12px;
        border: 1px solid #cbd5e1;
        font-family: 'DejaVu Sans Mono', Consolas, monospace;
        font-size: 10px;
        line-height: 1.25;
        color: #000000;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    @media print {
        body { background: #ffffff !important; padding: 0 !important; }
        .thermal-box { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .no-print { display: none !important; }
    }
    table { width: 100%; border-collapse: collapse; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .border-b-dashed { border-bottom: 1px dashed #000000; }
    .py-1 { padding-top: 3px; padding-bottom: 3px; }
    .py-2 { padding-top: 5px; padding-bottom: 5px; }
</style>
<?php endif; ?>

<div class="<?= $isPdf ? '' : 'thermal-box' ?>" style="<?= $isPdf ? 'width: 100%; padding: 4px;' : '' ?>">
    <!-- Header -->
    <div class="text-center border-b-dashed py-1">
        <div style="font-size: 12px; font-weight: bold; text-transform: uppercase;"><?= e($tenant['business_name']) ?></div>
        <div>Owner: <?= e($tenant['owner_name']) ?></div>
        <div>Tel: <?= e($tenant['phone']) ?></div>
        <?php if (!empty($settings['tax_number'])): ?>
            <div><?= e($settings['tax_label'] ?? 'GST') ?>: <?= e($settings['tax_number']) ?></div>
        <?php endif; ?>
        <div class="font-bold" style="padding-top: 3px;">*** MILK DELIVERY BILL ***</div>
    </div>

    <!-- Bill Meta -->
    <div class="border-b-dashed py-1">
        <table>
            <tr>
                <td>Bill #: INV-<?= $invoice['id'] ?></td>
                <td class="text-right"><?= date('d/m/y', strtotime($invoice['generated_at'])) ?></td>
            </tr>
            <tr>
                <td>Month: <?= date('M Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?></td>
                <td class="text-right font-bold"><?= strtoupper($invoice['payment_status']) ?></td>
            </tr>
        </table>
        <div class="font-bold" style="padding-top: 2px;">Customer: <?= e($invoice['customer_name']) ?></div>
        <div>Phone: <?= e($invoice['customer_phone']) ?></div>
        <div>Route: <?= e($invoice['route_name'] ?? 'Main') ?></div>
        <div class="font-bold">Milk: <?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?></div>
    </div>

    <!-- Delivery Tracking Summary -->
    <div class="border-b-dashed py-1" style="font-size: 8.5px;">
        <div class="font-bold" style="text-transform: uppercase;">Deliveries Summary:</div>
        <table>
            <tr>
                <td>Delivered Days:</td>
                <td class="text-right font-bold"><?= $breakdown['delivered_days'] ?? 0 ?> Days</td>
            </tr>
            <tr>
                <td>Skipped / Leave Days:</td>
                <td class="text-right"><?= ($breakdown['skipped_days'] ?? 0) + ($breakdown['leave_days'] ?? 0) ?> Days (0L)</td>
            </tr>
            <tr class="font-bold">
                <td>Total Liters:</td>
                <td class="text-right"><?= number_format((float)$invoice['total_units'], 1) ?> L</td>
            </tr>
        </table>
    </div>

    <!-- Charges Breakdown -->
    <div class="border-b-dashed py-1">
        <table>
            <?php if (!empty($breakdown['products'])): ?>
                <?php foreach ($breakdown['products'] as $prod): ?>
                    <tr>
                        <td style="padding-bottom: 2px;">
                            <div class="font-bold"><?= e($prod['name']) ?></div>
                            <div style="font-size: 8px;"><?= number_format((float)$prod['total_units'], 1) ?> <?= e($prod['unit']) ?> @ <?= formatMoney($prod['unit_price']) ?></div>
                        </td>
                        <td class="text-right font-bold" style="vertical-align: top;"><?= formatMoney($prod['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td style="padding-bottom: 2px;">
                        <div class="font-bold"><?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?> Total</div>
                        <div style="font-size: 8px;"><?= number_format((float)$invoice['total_units'], 1) ?>L @ <?= formatMoney($breakdown['customer_rate'] ?? 65) ?>/L</div>
                    </td>
                    <td class="text-right font-bold" style="vertical-align: top;"><?= formatMoney($invoice['milk_subtotal']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if (!empty($breakdown['charges'])): ?>
                <?php foreach ($breakdown['charges'] as $ch): ?>
                    <tr style="font-size: 8.5px;">
                        <td>+ <?= e($ch['name']) ?></td>
                        <td class="text-right"><?= formatMoney($ch['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($breakdown['tax_enabled']) && (float)$invoice['tax_amount'] > 0): ?>
                <tr style="font-size: 8.5px;">
                    <td>+ <?= e($breakdown['tax_label'] ?? 'GST') ?> (<?= (float)($breakdown['tax_percentage'] ?? 0) ?>%)</td>
                    <td class="text-right"><?= formatMoney($invoice['tax_amount']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if ((float)$invoice['previous_balance'] > 0): ?>
                <tr style="font-size: 8.5px;">
                    <td>+ Previous Balance:</td>
                    <td class="text-right"><?= formatMoney($invoice['previous_balance']) ?></td>
                </tr>
            <?php endif; ?>

            <?php 
            $paymentsRec = (float)($breakdown['payments_received'] ?? 0.0);
            if ($paymentsRec > 0): 
            ?>
                <tr style="font-size: 8.5px;">
                    <td>- Payments Received:</td>
                    <td class="text-right">-<?= formatMoney($paymentsRec) ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Total Payable -->
    <div class="py-2" style="border-bottom: 2px solid #000000;">
        <table>
            <tr class="font-bold" style="font-size: 11px;">
                <td>TOTAL DUE:</td>
                <td class="text-right" style="font-size: 13px;"><?= formatMoney($invoice['final_amount']) ?></td>
            </tr>
        </table>
    </div>

    <!-- QR Code & Footer -->
    <div class="text-center py-2">
        <div class="font-bold" style="font-size: 8.5px; margin-bottom: 4px;">SCAN & PAY VIA UPI</div>
        <div style="margin: 0 auto; text-align: center;">
            <?= \App\Services\QrCodeService::renderQrTag(
                $settings['upi_qr_path'] ?? null,
                $settings['upi_id'] ?? null,
                $tenant['business_name'] ?? 'DairyFlow',
                (float)$invoice['final_amount'],
                85,
                "Milk INV-{$invoice['id']}"
            ) ?>
        </div>
        <?php if (!empty($settings['upi_id'])): ?>
            <div style="font-size: 8.5px; margin-top: 4px;">UPI: <?= e($settings['upi_id']) ?></div>
        <?php endif; ?>
        <div style="font-size: 8px; font-style: italic; color: #475569; margin-top: 4px;">
            <?= e($settings['invoice_footer_note'] ?? 'Thank you! Pure milk daily.') ?>
        </div>
        <div style="font-size: 7.5px; color: #64748b; margin-top: 3px;">
            DairyFlow Zero-Dispute Invoicing
        </div>
    </div>
</div>

<?php if ($isPdf): ?>
</body>
</html>
<?php endif; ?>
