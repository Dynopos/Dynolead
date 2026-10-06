<x-auth-card title="Cuba percuma" :subtitle="config('billing.trial_leads').' lead atau '.config('billing.trial_days').' hari, mana dulu. Tanpa kad kredit.'">
    <form wire:submit="register" class="space-y-4">
        <x-field label="Nama anda" name="name" hint="Nama ni muncul dalam mesej, cth: “Saya Ali dari ...”">
            <input type="text" wire:model="name" autocomplete="name" class="input" placeholder="Ali">
        </x-field>
        <x-field label="Nama bisnes" name="business">
            <input type="text" wire:model="business" autocomplete="organization" class="input" placeholder="Ali Digital Enterprise">
        </x-field>
        <x-field label="E-mel" name="email">
            <input type="email" wire:model="email" autocomplete="email" class="input" placeholder="anda@email.com">
        </x-field>
        <x-field label="Kata laluan" name="password" hint="Sekurang-kurangnya 8 aksara.">
            <input type="password" wire:model="password" autocomplete="new-password" class="input">
        </x-field>
        <label class="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="agree" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            <span>Saya setuju dengan <a href="{{ route('terms') }}" target="_blank" class="font-medium text-emerald-700 underline">Terma</a> dan <a href="{{ route('privacy') }}" target="_blank" class="font-medium text-emerald-700 underline">Polisi Privasi</a>.</span>
        </label>
        @error('agree')<p class="-mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary w-full py-3 text-base" wire:loading.attr="disabled">Daftar &amp; mula</button>
    </form>
    <x-slot:footer>
        Dah ada akaun? <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald-700">Masuk</a>
    </x-slot:footer>
</x-auth-card>
