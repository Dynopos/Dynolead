<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Produk</h1>
        @unless ($showForm)
            <button wire:click="create" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">+ Tambah</button>
        @endunless
    </div>

    @if (session('status'))
        <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-semibold">{{ $editingId ? 'Edit produk' : 'Produk baru' }}</h2>

            <x-field label="Nama produk" name="name"><input type="text" wire:model="name" class="input"></x-field>
            <div class="grid grid-cols-2 gap-2">
                <x-field label="Nama pengirim" name="sender_name"><input type="text" wire:model="sender_name" class="input"></x-field>
                <x-field label="Kontak" name="contact_info"><input type="text" wire:model="contact_info" class="input"></x-field>
            </div>
            <x-field label="Syarikat" name="company"><input type="text" wire:model="company" class="input"></x-field>
            <x-field label="Apa produk buat (pitch_core)" name="pitch_core" hint="AI guna ayat ini, maksud tak diubah.">
                <textarea wire:model="pitch_core" rows="3" class="input"></textarea>
            </x-field>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">Varian pitch ikut jenis kedai</span>
                    <button type="button" wire:click="addVariant" class="text-sm font-semibold text-emerald-700">+ Varian</button>
                </div>
                @foreach ($pitch_variants as $i => $variant)
                    <div wire:key="variant-{{ $i }}" class="space-y-2 rounded-lg bg-slate-50 p-3">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" wire:model="pitch_variants.{{ $i }}.key" class="input" placeholder="Nama (cth runcit)">
                            <input type="text" wire:model="pitch_variants.{{ $i }}.match" class="input" placeholder="Padan: runcit, grocery">
                        </div>
                        <textarea wire:model="pitch_variants.{{ $i }}.pitch" rows="3" class="input" placeholder="Ayat pitch untuk jenis ini"></textarea>
                        <button type="button" wire:click="removeVariant({{ $i }})" class="text-xs text-red-600">Buang varian</button>
                    </div>
                @endforeach
            </div>

            <x-field label="CTA (ayat penutup)" name="cta"><textarea wire:model="cta" rows="2" class="input"></textarea></x-field>
            <x-field label="Perkataan dilarang" name="banned_words" hint="Asingkan dengan koma. Cth: demo">
                <input type="text" wire:model="banned_words" class="input">
            </x-field>
            <x-field label="Tanda kedai perlukan produk ini" name="fit_signals">
                <textarea wire:model="fit_signals" rows="3" class="input"></textarea>
            </x-field>
            <x-field label="Jenis bisnes cadangan" name="default_place_types" hint="Asingkan dengan koma.">
                <input type="text" wire:model="default_place_types" class="input">
            </x-field>

            <fieldset class="space-y-2 rounded-lg bg-slate-50 p-3">
                <legend class="text-sm font-medium text-slate-700">Tapisan</legend>
                <div class="grid grid-cols-2 gap-2">
                    <x-field label="Rating minimum" name="min_rating"><input type="number" step="0.1" min="0" max="5" wire:model="min_rating" class="input"></x-field>
                    <x-field label="Review minimum" name="min_reviews"><input type="number" min="0" wire:model="min_reviews" class="input"></x-field>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="require_no_website" class="rounded border-slate-300 text-emerald-600">
                    Kedai mesti tiada website
                </label>
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="active" class="rounded border-slate-300 text-emerald-600"> Aktif
            </label>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 py-2.5 font-semibold text-white">Simpan</button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-slate-300 px-4 py-2.5">Batal</button>
            </div>
        </form>
    @endif

    <ul class="space-y-3">
        @forelse ($products as $product)
            <li wire:key="product-{{ $product->id }}" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ $product->name }} @unless($product->active)<span class="text-xs text-slate-400">(tak aktif)</span>@endunless</p>
                        <p class="text-xs text-slate-500">{{ $product->sender_name }} · {{ $product->company }}</p>
                    </div>
                    <button wire:click="edit({{ $product->id }})" class="text-sm font-semibold text-emerald-700">Edit</button>
                </div>
                <p class="mt-2 text-sm text-slate-700">{{ $product->pitch_core }}</p>
                <div class="mt-2 flex flex-wrap gap-1 text-xs">
                    <span class="rounded bg-slate-100 px-2 py-0.5">Rating ≥ {{ $product->minRating() }}</span>
                    <span class="rounded bg-slate-100 px-2 py-0.5">Review ≥ {{ $product->minReviews() }}</span>
                    @if ($product->requiresNoWebsite())<span class="rounded bg-amber-100 px-2 py-0.5">Tiada website</span>@endif
                    @foreach ($product->bannedWords() as $word)<span class="rounded bg-red-50 px-2 py-0.5 text-red-700">Dilarang: {{ $word }}</span>@endforeach
                    @if ($product->pitch_variants)<span class="rounded bg-sky-50 px-2 py-0.5 text-sky-700">{{ count($product->pitch_variants) }} varian</span>@endif
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-500">Belum ada produk.</li>
        @endforelse
    </ul>
</div>
