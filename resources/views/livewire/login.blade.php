<div class="pt-16">
    <h1 class="text-2xl font-bold text-slate-900">Dyno Leads</h1>
    <p class="mt-1 text-sm text-slate-600">Masukkan kata laluan untuk teruskan.</p>

    <form wire:submit="login" class="mt-6 space-y-3">
        <input type="password" wire:model="password" autocomplete="current-password"
               class="w-full rounded-lg border-slate-300 text-base" placeholder="Kata laluan" autofocus>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-3 font-semibold text-white">Masuk</button>
    </form>
</div>
