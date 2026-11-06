<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Super Admin - DoodhDesk SaaS Platform') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="<?= asset('public/assets/js/alpine.min.js') ?>" onerror="this.src='https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js'"></script>
</head>
<body class="h-full flex flex-col bg-slate-950 text-slate-100">
    <!-- Admin Header -->
    <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-black text-xl shadow-lg">
                    🛡️
                </span>
                <div>
                    <div class="font-black tracking-tight text-white flex items-center gap-2">
                        <span>DoodhDesk Platform</span>
                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-purple-900/80 text-purple-300 border border-purple-700">Super Admin</span>
                    </div>
                    <div class="text-xs text-slate-400">Multi-Tenant SaaS Governance</div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-sm font-semibold text-slate-300 hidden sm:inline">
                    <?= e(\App\Core\Auth::admin()['name'] ?? 'Admin') ?>
                </span>
                <a href="<?= url('/logout') ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-rose-400 text-xs font-bold border border-slate-700 transition">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (!empty($success)): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="p-3 bg-emerald-950 border border-emerald-700 text-emerald-200 rounded-xl text-sm font-semibold">
                ✓ <?= e($success) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="p-3 bg-rose-950 border border-rose-700 text-rose-200 rounded-xl text-sm font-semibold">
                ⚠ <?= e($error) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Admin Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8">
        <?= $content ?>
    </main>
</body>
</html>
