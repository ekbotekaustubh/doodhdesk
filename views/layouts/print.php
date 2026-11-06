<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Print Document - DoodhDesk') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-shadow-none {
                box-shadow: none !important;
            }
            @page {
                margin: 8mm;
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900 py-6">
    <div class="no-print max-w-3xl mx-auto mb-4 px-4 flex items-center justify-between">
        <a href="javascript:history.back()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-sm">
            ← Back
        </a>
        <div class="flex items-center gap-2">
            <?php if (!empty($invoice['id'])): ?>
                <a href="<?= url("/invoices/{$invoice['id']}/pdf?type=a4") ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-sm font-bold shadow-sm transition">
                    📥 Download PDF
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow-md transition">
                🖨️ Print Now
            </button>
        </div>
    </div>

    <?= $content ?>
</body>
</html>
