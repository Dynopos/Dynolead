<div class="pt-16">
    <div class="text-white">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l5-5 4 4 8-9"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7h5v5"/>
            </svg>
        </span>
        <h1 class="mt-5 text-3xl font-bold tracking-tight">Dyno Leads</h1>
        <p class="mt-1 text-emerald-50/90">Cari kedai, tulis mesej, jejak lead.</p>
    </div>

    <form wire:submit="login" class="card mt-8 space-y-4 p-5">
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Kata laluan</label>
            <div class="relative">
                <x-icon name="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="password" type="password" wire:model="password" autocomplete="current-password"
                       class="input pl-11" placeholder="Masukkan kata laluan" autofocus>
            </div>
            @error('password') <p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary w-full py-3 text-base" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Sekejap...</span>
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-400">DynoPOS Technologies · Pasir Mas</p>
</div>
