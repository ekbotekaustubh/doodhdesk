<div class="bg-slate-850 bg-slate-800/80 rounded-3xl border border-slate-700 p-6 md:p-8 shadow-2xl backdrop-blur">
    <h2 class="text-xl font-black text-white text-center mb-1">Milkman Sign In</h2>
    <p class="text-xs text-slate-400 text-center mb-6">Enter your phone or email to manage deliveries & billing.</p>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-rose-950 border border-rose-800 text-rose-300 rounded-xl text-xs font-semibold">
            ⚠ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="mb-4 p-3 bg-emerald-950 border border-emerald-800 text-emerald-300 rounded-xl text-xs font-semibold">
            ✓ <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/login') ?>" class="space-y-4">
        <?= \App\Core\Auth::csrfField() ?>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Phone Number or Email</label>
            <input type="text" name="login" required placeholder="e.g. 9876543210 or ramesh@krishnadairy.com" class="w-full text-sm font-semibold px-4 py-3 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Password</label>
            <input type="password" name="password" required placeholder="••••••••" class="w-full text-sm font-semibold px-4 py-3 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" class="tap-target w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black text-sm shadow-lg transition">
            Sign In to DoodhDesk
        </button>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-700/60 text-center text-xs text-slate-400">
        New milk delivery business? 
        <a href="<?= url('/register') ?>" class="font-bold text-blue-400 hover:underline">Register Free (5 Customers Free)</a>
    </div>
</div>
