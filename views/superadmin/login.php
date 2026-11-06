<div class="bg-slate-850 bg-slate-800/80 rounded-3xl border border-slate-700 p-6 md:p-8 shadow-2xl backdrop-blur">
    <div class="text-center mb-6">
        <span class="w-12 h-12 rounded-xl bg-purple-600 text-white flex items-center justify-center mx-auto text-2xl font-black mb-2 shadow-lg">
            🛡️
        </span>
        <h2 class="text-xl font-black text-white">Super Admin Access</h2>
        <p class="text-xs text-slate-400 mt-1">SaaS Platform Management & Governance</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-rose-950 border border-rose-800 text-rose-300 rounded-xl text-xs font-semibold">
            ⚠ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/login') ?>" class="space-y-4">
        <?= \App\Core\Auth::csrfField() ?>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Admin Email</label>
            <input type="email" name="email" required placeholder="admin@doodhdesk.com" class="w-full text-sm font-semibold px-4 py-3 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
        </div>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Password</label>
            <input type="password" name="password" required placeholder="••••••••" class="w-full text-sm font-semibold px-4 py-3 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-purple-500">
        </div>

        <button type="submit" class="tap-target w-full py-3.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-black text-sm shadow-lg transition">
            Sign In as Admin
        </button>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-700/60 text-center text-xs text-slate-400">
        <a href="<?= url('/login') ?>" class="font-bold text-slate-400 hover:text-white">← Return to Milkman Login</a>
    </div>
</div>
