<div class="max-w-md mx-auto text-center py-16 px-4">
    <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-black">
        404
    </div>
    <h1 class="text-2xl font-black text-slate-800 dark:text-white mb-2">Page Not Found</h1>
    <p class="text-slate-600 dark:text-slate-300 mb-6 text-sm">
        The requested URL <code class="px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded font-mono text-xs"><?= e($path ?? '/') ?></code> was not found.
    </p>
    <a href="<?= url('/') ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-md transition">
        Return to Dashboard
    </a>
</div>
