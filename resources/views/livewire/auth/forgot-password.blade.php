<x-auth-card title="Lupa kata laluan" subtitle="Kami hantar pautan untuk tukar kata laluan.">
    @if ($sent)
        <x-alert type="success">{{ $sent }}</x-alert>
    @else
        <form wire:submit="send" class="space-y-4">
            <x-field label="E-mel" name="email">
                <input type="email" wire:model="email" autocomplete="email" class="input" placeholder="anda@email.com" autofocus>
            </x-field>
            <button type="submit" class="btn-primary w-full py-3 text-base" wire:loading.attr="disabled">Hantar pautan</button>
        </form>
    @endif
    <x-slot:footer>
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald-700">Kembali ke Masuk</a>
    </x-slot:footer>
</x-auth-card>
