<x-auth-card title="Selamat kembali" subtitle="Masuk untuk tengok lead anda.">
    @if (session('status'))
        <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif
    <form wire:submit="login" class="space-y-4">
        <x-field label="E-mel" name="email">
            <input type="email" wire:model="email" autocomplete="email" class="input" placeholder="anda@email.com" autofocus>
        </x-field>
        <x-field label="Kata laluan" name="password">
            <input type="password" wire:model="password" autocomplete="current-password" class="input">
        </x-field>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Ingat saya
            </label>
            <a href="{{ route('password.request') }}" wire:navigate class="font-medium text-emerald-700">Lupa kata laluan?</a>
        </div>
        <button type="submit" class="btn-primary w-full py-3 text-base" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Sekejap...</span>
        </button>
    </form>
    <x-slot:footer>
        Belum ada akaun? <a href="{{ route('register') }}" wire:navigate class="font-semibold text-emerald-700">Daftar percuma</a>
    </x-slot:footer>
</x-auth-card>
