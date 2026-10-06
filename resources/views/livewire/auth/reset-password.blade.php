<x-auth-card title="Kata laluan baru">
    <form wire:submit="resetPassword" class="space-y-4">
        <x-field label="E-mel" name="email">
            <input type="email" wire:model="email" autocomplete="email" class="input">
        </x-field>
        <x-field label="Kata laluan baru" name="password" hint="Sekurang-kurangnya 8 aksara.">
            <input type="password" wire:model="password" autocomplete="new-password" class="input" autofocus>
        </x-field>
        <button type="submit" class="btn-primary w-full py-3 text-base" wire:loading.attr="disabled">Simpan kata laluan</button>
    </form>
</x-auth-card>
