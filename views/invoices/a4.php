<?php
/** @var array $invoice */
/** @var array $breakdown */
/** @var array $tenant */
/** @var array $settings */
$isPdf = !empty($isPdf);
?>
<?php if ($isPdf): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #INV-<?= $invoice['billing_year'] ?>-<?= sprintf('%02d', $invoice['billing_month']) ?>-<?= sprintf('%04d', $invoice['id']) ?></title>
<?php endif; ?>

<style>
    @page {
        margin: 8mm 10mm;
        size: A4 portrait;
    }
    * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body {
        margin: 0;
        padding: 0;
        background: <?= $isPdf ? '#ffffff' : '#f1f5f9' ?>;
        font-family: 'DejaVu Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #0f172a;
        font-size: 10px;
        line-height: 1.35;
    }
    .invoice-card {
        width: 100%;
        max-width: 780px;
        margin: 0 auto;
        background: #ffffff;
        <?= $isPdf ? '' : 'padding: 32px 36px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;' ?>
    }
    @media print {
        body {
            background: #ffffff !important;
            padding: 0 !important;
        }
        .invoice-card {
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            max-width: 100% !important;
        }
        .no-print {
            display: none !important;
        }
    }
    
    table {
        border-collapse: collapse;
        width: 100%;
    }
    
    /* Header layout */
    .header-table {
        width: 100%;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .business-title {
        font-size: 18px;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
        letter-spacing: -0.3px;
        margin: 0;
    }
    .business-sub {
        font-size: 9.5px;
        color: #475569;
        margin-top: 2px;
    }
    .tax-badge {
        display: inline-block;
        background-color: #0f172a;
        color: #ffffff;
        font-size: 9.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 3px 8px;
        border-radius: 3px;
    }
    .meta-table {
        font-size: 9.5px;
        font-family: 'DejaVu Sans Mono', Consolas, monospace;
        text-align: right;
        margin-top: 4px;
        margin-left: auto;
        margin-right: 0;
    }
    .meta-table td {
        padding: 1px 0 1px 6px;
    }

    /* Bill To & Verification */
    .info-table {
        width: 100%;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }
    .label-caps {
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 2px;
    }
    .customer-name {
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .customer-detail {
        font-size: 9.5px;
        color: #475569;
        line-height: 1.3;
    }
    .verification-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        padding: 8px 10px;
    }
    .stat-row {
        width: 100%;
        font-size: 9px;
    }
    .stat-row td {
        padding: 1.5px 0;
    }
    .text-emerald { color: #047857; font-weight: bold; }
    .text-rose { color: #be123c; font-weight: bold; }
    .text-slate { color: #475569; }

    /* Line Items Table */
    .items-table {
        width: 100%;
        border: 1px solid #e2e8f0;
        margin-bottom: 10px;
    }
    .items-table th {
        background-color: #f1f5f9;
        border-bottom: 1px solid #cbd5e1;
        padding: 6px 8px;
        font-size: 8.5px;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
    }
    .items-table td {
        padding: 5px 8px;
        font-size: 9.5px;
        border-bottom: 1px solid #f1f5f9;
    }
    .item-title {
        font-weight: bold;
        color: #0f172a;
    }
    .item-desc {
        font-size: 8px;
        color: #64748b;
    }
    .items-table tfoot td {
        padding: 7px 8px;
        background-color: #f8fafc;
        border-top: 2px solid #0f172a;
        font-weight: 800;
    }
    .total-amount {
        font-size: 13px;
        color: #1d4ed8;
    }

    /* Calendar Matrix */
    .matrix-title {
        font-size: 8.5px;
        font-weight: 800;
        text-transform: uppercase;
        color: #334155;
        margin-bottom: 3px;
    }
    .matrix-sub {
        font-size: 7.5px;
        color: #64748b;
        font-weight: normal;
    }
    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #cbd5e1;
        text-align: center;
        margin-bottom: 10px;
    }
    .matrix-table th {
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 3px;
        font-size: 8px;
        font-weight: 700;
        color: #334155;
        width: 14.28%;
    }
    .matrix-table td {
        border: 1px solid #cbd5e1;
        padding: 2px;
        height: 22px;
        vertical-align: top;
        font-size: 7.5px;
    }

    /* Footer */
    .footer-table {
        width: 100%;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
    }
    .footer-note {
        font-size: 9px;
        color: #475569;
        margin-top: 2px;
    }
    .upi-badge {
        display: inline-block;
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'DejaVu Sans Mono', Consolas, monospace;
        font-size: 9px;
        margin-top: 3px;
    }
    .powered-by {
        font-size: 7.5px;
        color: #94a3b8;
        margin-top: 6px;
        padding-top: 4px;
        border-top: 1px solid #f1f5f9;
    }
</style>

<?php if ($isPdf): ?>
</head>
<body>
<?php endif; ?>

<div class="invoice-card">
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <h1 class="business-title"><?= e($tenant['business_name']) ?></h1>
                <div class="business-sub">
                    Owner: <strong><?= e($tenant['owner_name']) ?></strong> • Phone: <strong><?= e($tenant['phone']) ?></strong>
                </div>
                <?php if (!empty($settings['tax_number'])): ?>
                    <div class="business-sub" style="font-family: 'DejaVu Sans Mono', monospace; color: #334155; margin-top: 1px;">
                        <strong><?= e($settings['tax_label'] ?? 'GST') ?>IN:</strong> <?= e($settings['tax_number']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td style="vertical-align: top; text-align: right; width: 230px;">
                <div class="tax-badge">TAX INVOICE</div>
                <table class="meta-table">
                    <tr><td><strong>Invoice #:</strong></td><td>INV-<?= $invoice['billing_year'] ?>-<?= sprintf('%02d', $invoice['billing_month']) ?>-<?= sprintf('%04d', $invoice['id']) ?></td></tr>
                    <tr><td><strong>Date:</strong></td><td><?= formatDate($invoice['generated_at'], 'd M Y') ?></td></tr>
                    <tr><td><strong>Billing Month:</strong></td><td><?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?></td></tr>
                    <tr><td><strong>Status:</strong></td><td style="color: #047857; font-weight: bold;"><?= strtoupper($invoice['payment_status']) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Bill To & Delivery Summary -->
    <table class="info-table">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 12px;">
                <div class="label-caps">Bill To Customer</div>
                <div class="customer-name"><?= e($invoice['customer_name']) ?></div>
                <div class="customer-detail"><?= e($invoice['customer_address']) ?></div>
                <div class="customer-detail">Phone: <strong style="font-family: 'DejaVu Sans Mono', monospace;"><?= e($invoice['customer_phone']) ?></strong></div>
                <div class="customer-detail">Route: <strong><?= e($invoice['route_name'] ?? 'Main Route') ?></strong></div>
                <div class="customer-detail" style="margin-top: 3px;">
                    Milk Subscribed: <strong><?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?></strong>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="verification-box">
                    <div class="label-caps" style="color: #475569; margin-bottom: 4px;">Monthly Delivery Run-Sheet Verification</div>
                    <table class="stat-row">
                        <tr>
                            <td>Month Total Days:</td>
                            <td style="text-align: right; font-weight: bold;"><?= $breakdown['days_in_month'] ?? 30 ?> Days</td>
                        </tr>
                        <tr class="text-emerald">
                            <td>✓ Delivered Days:</td>
                            <td style="text-align: right;"><?= $breakdown['delivered_days'] ?? 0 ?> Days (<?= number_format((float)$invoice['total_units'], 1) ?> Liters)</td>
                        </tr>
                        <tr class="text-rose">
                            <td>✗ Missed / Skipped Days:</td>
                            <td style="text-align: right;"><?= $breakdown['skipped_days'] ?? 0 ?> Days (0.00 L billed)</td>
                        </tr>
                        <tr class="text-slate">
                            <td>• Vacation Leave Days:</td>
                            <td style="text-align: right;"><?= $breakdown['leave_days'] ?? 0 ?> Days (0.00 L billed)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Item Breakdown Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="text-align: left;">Item Description</th>
                <th style="text-align: center; width: 100px;">Unit / Rate</th>
                <th style="text-align: center; width: 90px;">Qty / Days</th>
                <th style="text-align: right; width: 95px;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($breakdown['products'])): ?>
                <?php foreach ($breakdown['products'] as $prod): ?>
                    <tr>
                        <td>
                            <div class="item-title">Farm Fresh <?= e($prod['name']) ?> Delivered</div>
                            <div class="item-desc">Tracked on daily run-sheet (Zero charges on skipped days)</div>
                        </td>
                        <td style="text-align: center; font-family: 'DejaVu Sans', sans-serif;"><?= formatMoney($prod['unit_price']) ?> / <?= e($prod['unit']) ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= number_format((float)$prod['total_units'], 2) ?> <?= e($prod['unit']) ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= formatMoney($prod['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td>
                        <div class="item-title">Farm Fresh <?= formatMilkTypeText($invoice['milk_types'] ?? ($breakdown['customer']['milk_types'] ?? 'cow')) ?> Delivered</div>
                        <div class="item-desc">Actual deliveries tracked on daily run-sheet (Zero charges on skipped days)</div>
                    </td>
                    <td style="text-align: center; font-family: 'DejaVu Sans', sans-serif;"><?= formatMoney($breakdown['customer_rate'] ?? 65.00) ?> / L</td>
                    <td style="text-align: center; font-weight: bold;"><?= number_format((float)$invoice['total_units'], 2) ?> L</td>
                    <td style="text-align: right; font-weight: bold;"><?= formatMoney($invoice['milk_subtotal']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if (!empty($breakdown['charges'])): ?>
                <?php foreach ($breakdown['charges'] as $ch): ?>
                    <tr>
                        <td>
                            <div class="item-title"><?= e($ch['name']) ?></div>
                            <div class="item-desc"><?= e($ch['note'] ?? '') ?><?= !empty($ch['is_taxable']) ? ' (Taxable)' : '' ?></div>
                        </td>
                        <td style="text-align: center; font-family: 'DejaVu Sans', sans-serif;"><?= formatMoney($ch['rate']) ?></td>
                        <td style="text-align: center; font-family: 'DejaVu Sans', sans-serif;">
                            <?= match($ch['type']) {
                                'per_liter' => number_format((float)$invoice['total_units'], 1) . ' L',
                                'per_delivery_day' => ($breakdown['distinct_delivered_days'] ?? $breakdown['delivered_days'] ?? 0) . ' Days',
                                default => '1 Month',
                            } ?>
                        </td>
                        <td style="text-align: right; font-weight: bold;"><?= formatMoney($ch['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($breakdown['tax_enabled']) && (float)$invoice['tax_amount'] > 0): ?>
                <tr>
                    <td colspan="3" style="font-weight: bold;">
                        <?= e($breakdown['tax_label'] ?? 'GST') ?> (<?= number_format((float)($breakdown['tax_percentage'] ?? 0), 2) ?>%)
                        <span style="font-size: 8px; color: #64748b; font-weight: normal;">on taxable base of <?= formatMoney($breakdown['taxable_base'] ?? 0) ?></span>
                    </td>
                    <td style="text-align: right; font-weight: bold;"><?= formatMoney($invoice['tax_amount']) ?></td>
                </tr>
            <?php endif; ?>

            <?php if ((float)$invoice['previous_balance'] > 0): ?>
                <tr style="background-color: #fff1f2;">
                    <td colspan="3" style="font-weight: bold; color: #9f1239;">Previous Unpaid Balance Carried Forward</td>
                    <td style="text-align: right; font-weight: bold; color: #9f1239;">+ <?= formatMoney($invoice['previous_balance']) ?></td>
                </tr>
            <?php endif; ?>

            <?php 
            $paymentsRec = (float)($breakdown['payments_received'] ?? 0.0);
            if ($paymentsRec > 0): 
            ?>
                <tr style="background-color: #ecfdf5;">
                    <td colspan="3" style="font-weight: bold; color: #065f46;">Less: Payments Received / Advance</td>
                    <td style="text-align: right; font-weight: bold; color: #065f46;">- <?= formatMoney($paymentsRec) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-transform: uppercase;">Total Amount Payable</td>
                <td style="text-align: right;" class="total-amount"><?= formatMoney($invoice['final_amount']) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Calendar Matrix -->
    <?php
    $matrix = $breakdown['calendar_matrix'] ?? [];
    $firstDayOfWeek = (int)($breakdown['first_day_of_week'] ?? 1);
    $daysInMonth = (int)($breakdown['days_in_month'] ?? count($matrix));
    ?>
    <?php if (!empty($matrix)): ?>
        <div style="margin-bottom: 8px;">
            <div class="matrix-title">
                Daily Delivery Matrix & Verification (<?= date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year'])) ?>)
                <span class="matrix-sub">• Each date marked with delivered quantity (0L = Skipped / Leave)</span>
            </div>
            
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th style="color: #92400e;">Sat</th>
                        <th style="color: #9f1239;">Sun</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                    <?php
                    $col = 1;
                    for ($pad = 1; $pad < $firstDayOfWeek; $pad++) {
                        echo '<td style="background-color: #f8fafc; border: 1px solid #e2e8f0;">&nbsp;</td>';
                        $col++;
                    }

                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $cell = $matrix[$d] ?? ['quantity' => 0.0, 'status' => 'unrecorded'];
                        $st = $cell['status'] ?? 'unrecorded';
                        $q = (float)($cell['quantity'] ?? 0);
                        
                        $bg = $st === 'delivered' ? '#ecfdf5' : ($st === 'skipped' ? '#fff1f2' : ($st === 'leave' ? '#f1f5f9' : '#ffffff'));
                        $qtyText = $st === 'delivered' ? number_format($q, 1) . 'L' : ($st === 'skipped' ? '0L (Skip)' : ($st === 'leave' ? '0L (Lv)' : '-'));
                        $qtyColor = $st === 'delivered' ? '#047857' : ($st === 'skipped' ? '#be123c' : '#64748b');

                        echo "<td style=\"background-color: {$bg};\">";
                        echo "<div style=\"font-size: 7.5px; font-weight: bold; color: #475569;\">{$d}</div>";
                        echo "<div style=\"font-size: 8px; font-weight: bold; color: {$qtyColor}; line-height: 1;\">{$qtyText}</div>";
                        echo "</td>";

                        if ($col % 7 === 0 && $d < $daysInMonth) {
                            echo "</tr><tr>";
                        }
                        $col++;
                    }

                    while (($col - 1) % 7 !== 0) {
                        echo '<td style="background-color: #f8fafc; border: 1px solid #e2e8f0;">&nbsp;</td>';
                        $col++;
                    }
                    ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <table class="footer-table">
        <tr>
            <td style="vertical-align: top; padding-right: 15px;">
                <div class="label-caps" style="color: #0f172a;">Payment Terms & Notes</div>
                <div class="footer-note">
                    <?= e($settings['invoice_footer_note'] ?? 'Please pay via UPI or cash before the 5th of this month. For any questions, contact your milk delivery partner.') ?>
                </div>
                <?php if (!empty($settings['upi_id'])): ?>
                    <div style="margin-top: 3px;">
                        <span class="upi-badge"><strong>UPI ID:</strong> <?= e($settings['upi_id']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="powered-by">
                    Powered by DairyFlow SaaS • Multi-Tenant Zero-Dispute Invoicing
                </div>
            </td>
            <td style="width: 105px; vertical-align: top; text-align: center;">
                <div class="label-caps" style="color: #64748b; margin-bottom: 2px;">Scan to Pay UPI</div>
                <?= \App\Services\QrCodeService::renderQrTag(
                    $settings['upi_qr_path'] ?? null,
                    $settings['upi_id'] ?? null,
                    $tenant['business_name'] ?? 'DairyFlow',
                    (float)$invoice['final_amount'],
                    90,
                    "Milk Bill INV-{$invoice['id']}"
                ) ?>
            </td>
        </tr>
    </table>
</div>

<?php if ($isPdf): ?>
</body>
</html>
<?php endif; ?>
