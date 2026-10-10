<div class="space-y-5">
    <x-page-header title="Produk" subtitle="Profil produk yang AI guna untuk tulis mesej.">
        @unless ($showForm)
            <button wire:click="create" class="btn-primary px-3 py-2"><x-icon name="plus" class="h-4 w-4" /> Tambah</button>
        @endunless
    </x-page-header>

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <h2 class="font-semibold">{{ $editingId ? 'Edit produk' : 'Produk baru' }}</h2>
                <button type="button" wire:click="cancel" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100" title="Tutup"><x-icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="space-y-6 p-4">
                <section class="space-y-3">
                    <h3 class="label">Profil</h3>
                    <x-field label="Nama produk" name="name"><input type="text" wire:model="name" class="input"></x-field>
                    <div class="grid grid-cols-2 gap-2">
                        <x-field label="Nama pengirim" name="sender_name"><input type="text" wire:model="sender_name" class="input"></x-field>
                        <x-field label="Kontak" name="contact_info"><input type="text" wire:model="contact_info" class="input"></x-field>
                    </div>
                    <x-field label="Syarikat" name="company"><input type="text" wire:model="company" class="input"></x-field>
                </section>

                <section class="space-y-3">
                    <h3 class="label">Apa yang anda jual</h3>
                    <x-field label="Fakta produk" name="pitch_core" hint="Tulis fakta ringkas sahaja: apa yang dijual, harga, promosi, kelebihan. AI akan tulis ayat yang menarik untuk setiap kedai. Harga dan promosi hanya diambil dari sini.">
                        <textarea wire:model="pitch_core" rows="4" class="input" placeholder="cth: Website premium RM200 termasuk domain &amp; hosting. Siap dalam 2 hari. Promosi untuk tempahan 10–13 Oktober."></textarea>
                    </x-field>
                    <x-field label="Ayat ajakan (pilihan)" name="cta" hint="Pilihan. Jika kosong, mesej ditutup dengan: “Kalau berminat, balas je mesej ni”."><textarea wire:model="cta" rows="2" class="input"></textarea></x-field>

                    <details class="rounded-xl ring-1 ring-inset ring-slate-200" @if ($pitch_variants !== [] || $errors->hasAny(['banned_words', 'fit_signals', 'pitch_variants.*'])) open @endif>
                        <summary class="cursor-pointer px-3 py-2.5 text-sm font-semibold text-slate-700">Tetapan lanjutan (pilihan)</summary>
                        <div class="space-y-3 border-t border-slate-100 p-3">
                            <x-field label="Tanda kedai perlukan produk ini" name="fit_signals" hint="Contoh sahaja, bukan syarat. Kosongkan jika produk sesuai untuk semua bisnes.">
                                <textarea wire:model="fit_signals" rows="3" class="input"></textarea>
                            </x-field>
                            <x-field label="Perkataan dilarang" name="banned_words" hint="Perkataan yang AI tak boleh guna. Asingkan dengan koma. Cth: demo">
                                <input type="text" wire:model="banned_words" class="input">
                            </x-field>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-700">Fakta lain ikut jenis kedai</span>
                                    <button type="button" wire:click="addVariant" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700"><x-icon name="plus" class="h-4 w-4" />Varian</button>
                                </div>
                                @foreach ($pitch_variants as $i => $variant)
                                    <div wire:key="variant-{{ $i }}" class="space-y-2 rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="text" wire:model="pitch_variants.{{ $i }}.key" class="input text-sm" placeholder="Nama (cth runcit)">
                                            <input type="text" wire:model="pitch_variants.{{ $i }}.match" class="input text-sm" placeholder="Padan: runcit, grocery">
                                        </div>
                                        <textarea wire:model="pitch_variants.{{ $i }}.pitch" rows="4" class="input text-sm" placeholder="Fakta produk untuk jenis kedai ini"></textarea>
                                        <button type="button" wire:click="removeVariant({{ $i }})" class="inline-flex items-center gap-1 text-xs font-medium text-rose-600"><x-icon name="trash" class="h-3.5 w-3.5" />Buang varian</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                </section>

                <section class="space-y-3">
                    <h3 class="label">Tapisan</h3>
                    <x-field label="Jenis bisnes cadangan" name="default_place_types" hint="Asingkan dengan koma.">
                        <input type="text" wire:model="default_place_types" class="input">
                    </x-field>
                    <div class="grid grid-cols-2 gap-2">
                        <x-field label="Rating minimum" name="min_rating"><input type="number" step="0.1" min="0" max="5" wire:model="min_rating" class="input"></x-field>
                        <x-field label="Review minimum" name="min_reviews"><input type="number" min="0" wire:model="min_reviews" class="input"></x-field>
                    </div>
                    <div class="space-y-3 rounded-xl bg-slate-50 p-3">
                        <x-toggle label="Kedai mesti tiada website" hint="Untuk produk website." wire:model="require_no_website" />
                        <x-toggle label="Aktif" hint="Produk tak aktif tak muncul di skrin Cari." wire:model="active" />
                    </div>
                </section>
            </div>

            <div class="flex gap-2 border-t border-slate-100 bg-slate-50/60 p-3">
                <button type="submit" class="btn-primary flex-1 py-3"><x-icon name="check" class="h-4 w-4" />Simpan</button>
                <button type="button" wire:click="cancel" class="btn-soft">Batal</button>
            </div>
        </form>
    @endif

    <ul class="space-y-3">
        @forelse ($products as $product)
            <li wire:key="product-{{ $product->id }}" class="card overflow-hidden">
                <div class="flex items-start gap-3 p-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $product->requiresNoWebsite() ? 'from-sky-400 to-indigo-500' : 'from-emerald-400 to-teal-600' }} text-lg font-bold text-white">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-semibold">
                            {{ $product->name }}
                            @unless ($product->active)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Tak aktif</span>@endunless
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $product->sender_name }} · {{ $product->company }}</p>
                    </div>
                    <button wire:click="edit({{ $product->id }})" class="btn-soft px-3 py-1.5 text-xs"><x-icon name="pencil" class="h-3.5 w-3.5" />Edit</button>
                </div>
                <p class="px-4 text-sm leading-relaxed text-slate-600">{{ $product->pitch_core }}</p>
                <div class="flex flex-wrap gap-1.5 p-4 pt-3">
                    <span class="chip"><x-icon name="star" class="h-3 w-3 text-amber-400" />Rating ≥ {{ $product->minRating() }}</span>
                    <span class="chip">Review ≥ {{ $product->minReviews() }}</span>
                    @if ($product->requiresNoWebsite())<span class="chip bg-sky-50 text-sky-700">Tiada website</span>@endif
                    @foreach ($product->bannedWords() as $word)<span class="chip bg-rose-50 text-rose-700">Dilarang: {{ $word }}</span>@endforeach
                    @if ($product->pitch_variants)<span class="chip bg-violet-50 text-violet-700">{{ count($product->pitch_variants) }} varian</span>@endif
                </div>
            </li>
        @empty
            <li><x-empty icon="cube" title="Belum ada produk">Tekan Tambah untuk cipta profil produk pertama.</x-empty></li>
        @endforelse
    </ul>
</div>
