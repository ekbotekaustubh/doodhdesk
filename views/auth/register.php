<div class="bg-slate-850 bg-slate-800/80 rounded-3xl border border-slate-700 p-6 md:p-8 shadow-2xl backdrop-blur">
    <h2 class="text-xl font-black text-white text-center mb-1">Create Dairy Account</h2>
    <p class="text-xs text-slate-400 text-center mb-6">Start free with up to 5 customers. Upgrade anytime.</p>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-rose-950 border border-rose-800 text-rose-300 rounded-xl text-xs font-semibold">
            ⚠ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/register') ?>" class="space-y-4">
        <?= \App\Core\Auth::csrfField() ?>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Dairy / Business Name *</label>
            <input type="text" name="business_name" required placeholder="e.g. Om Sai Dairy Farm" class="w-full text-sm font-semibold px-4 py-2.5 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Owner Full Name *</label>
            <input type="text" name="owner_name" required placeholder="e.g. Kaustubh Sharma" class="w-full text-sm font-semibold px-4 py-2.5 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-black uppercase text-slate-300 mb-1">Phone Number *</label>
                <input type="tel" name="phone" required placeholder="9876543210" class="w-full text-sm font-semibold px-4 py-2.5 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-black uppercase text-slate-300 mb-1">Email (Optional)</label>
                <input type="email" name="email" placeholder="dairy@example.com" class="w-full text-sm font-semibold px-4 py-2.5 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-black uppercase text-slate-300 mb-1">Password *</label>
            <input type="password" name="password" required placeholder="••••••••" class="w-full text-sm font-semibold px-4 py-2.5 bg-slate-900 border border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" class="tap-target w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black text-sm shadow-lg transition">
            Create Free Account
        </button>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-700/60 text-center text-xs text-slate-400">
        Already have an account? 
        <a href="<?= url('/login') ?>" class="font-bold text-blue-400 hover:underline">Sign In</a>
    </div>
</div>
