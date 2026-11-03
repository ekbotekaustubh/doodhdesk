<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($title ?? 'DairyFlow - Multi-Tenant Dairy SaaS') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tap-target { min-height: 48px; }
    </style>
</head>
<body class="h-full flex flex-col justify-center items-center px-4 py-8 bg-slate-900 text-white">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center mx-auto text-3xl font-black shadow-xl mb-3">
                🥛
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">DairyFlow SaaS</h1>
            <p class="text-sm text-slate-400 mt-1">Multi-Tenant Milk Delivery & Zero-Dispute Invoicing</p>
        </div>

        <?= $content ?>

        <!-- Quick Switcher on Auth -->
        <div class="mt-8 pt-6 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-400 mb-2 font-medium">⚡ Quick Demo One-Click Access:</p>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <a href="<?= url('/demo/tenant1') ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-blue-400 text-xs font-semibold border border-slate-700 transition">
                    🥛 Krishna Dairy (Paid)
                </a>
                <a href="<?= url('/demo/tenant2') ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-emerald-400 text-xs font-semibold border border-slate-700 transition">
                    🐄 Radha Dairy (Free 4/5)
                </a>
                <a href="<?= url('/demo/admin') ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-purple-400 text-xs font-semibold border border-slate-700 transition">
                    🛡️ Super Admin
                </a>
            </div>
        </div>
    </div>
</body>
</html>
