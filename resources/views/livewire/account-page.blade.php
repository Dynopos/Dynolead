<div class="space-y-5">
    <x-page-header title="Akaun" subtitle="Maklumat bisnes dan log masuk." />

    <a href="{{ route('billing') }}" wire:navigate class="card flex items-center gap-3 p-4">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="wallet" /></span>
        <span class="flex-1">
            <span class="block font-semibold">Langganan</span>
            <span class="block text-xs text-slate-500">Pelan, kuota dan bayaran</span>
        </span>
        <x-icon name="chevron-right" class="h-5 w-5 text-slate-400" />
    </a>

    <form wire:submit="save" class="card space-y-4 p-4">
        <h2 class="label">Bisnes</h2>
        <x-field label="Nama bisnes" name="business"><input type="text" wire:model="business" class="input"></x-field>
        <x-field label="Nama pengirim" name="sender_name" hint="Nama dalam mesej, cth: “Saya Ali dari ...”. Boleh ubah setiap produk.">
            <input type="text" wire:model="sender_name" class="input">
        </x-field>
        <x-field label="Telefon bisnes" name="phone"><input type="tel" wire:model="phone" class="input" placeholder="012-345 6789"></x-field>

        <h2 class="label pt-2">Log masuk</h2>
        <x-field label="Nama anda" name="name"><input type="text" wire:model="name" class="input"></x-field>
        <x-field label="E-mel" name="email"><input type="email" wire:model="email" class="input"></x-field>

        <button type="submit" class="btn-primary w-full py-3"><x-icon name="check" class="h-4 w-4" />Simpan</button>
        @if ($saved)<x-alert type="success">{{ $saved }}</x-alert>@endif
    </form>

    <form wire:submit="changePassword" class="card space-y-4 p-4">
        <h2 class="label">Tukar kata laluan</h2>
        <x-field label="Kata laluan semasa" name="current_password"><input type="password" wire:model="current_password" autocomplete="current-password" class="input"></x-field>
        <x-field label="Kata laluan baru" name="new_password" hint="Sekurang-kurangnya 8 aksara."><input type="password" wire:model="new_password" autocomplete="new-password" class="input"></x-field>
        <button type="submit" class="btn-dark w-full">Tukar kata laluan</button>
        @if ($passwordSaved)<x-alert type="success">{{ $passwordSaved }}</x-alert>@endif
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-soft w-full text-rose-600"><x-icon name="logout" class="h-4 w-4" />Keluar</button>
    </form>
</div>
