<div class="space-y-5" @if($running) wire:poll.3s @endif>
    <x-page-header title="Cari kedai" subtitle="Pilih produk, jenis bisnes dan kawasan." />

    @if ($blocker)
        <x-alert type="warning">{{ $blocker }}</x-alert>
    @endif

    <form wire:submit="calculate" class="card space-y-5 p-4">
        {{-- Product as cards --}}
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-slate-700">Produk</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($products as $product)
                    @php($picked = (int) $product_id === $product->id)
                    <label wire:key="pick-{{ $product->id }}" @class(['relative cursor-pointer rounded-xl p-3 ring-1 ring-inset transition', 'bg-emerald-50 ring-2 ring-emerald-500' => $picked, 'bg-white ring-slate-200 hover:bg-slate-50' => ! $picked])>
                        <input type="radio" wire:model.live="product_id" value="{{ $product->id }}" class="sr-only">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $product->name }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-slate-500">{{ $product->requiresNoWebsite() ? 'Kedai tanpa website' : 'Semua kedai sesuai' }}</span>
                        @if ($picked)
                            <x-icon name="check-circle" class="absolute right-2 top-2 h-5 w-5 text-emerald-600" />
                        @endif
                    </label>
                @endforeach
            </div>
            @error('product_id')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <x-field label="Jenis bisnes" name="business_type">
                <div class="relative">
                    <x-icon name="store" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input type="text" wire:model.blur="business_type" class="input pl-11" placeholder="cth: kedai runcit">
                </div>
            </x-field>
            @if ($suggestedTypes)
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($suggestedTypes as $type)
                        <button type="button" wire:click="pickType(@js($type))"
                                @class(['rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition', 'bg-emerald-600 text-white ring-emerald-600' => $business_type === $type, 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' => $business_type !== $type])>{{ $type }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <x-field label="Kawasan" name="areas" hint="Satu kawasan setiap baris (maksimum 5).">
            <div class="relative">
                <x-icon name="map-pin" class="pointer-events-none absolute left-3.5 top-3 h-5 w-5 text-slate-400" />
                <textarea wire:model.blur="areas" rows="2" class="input pl-11" placeholder="Pasir Mas, Kelantan"></textarea>
            </div>
        </x-field>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <span class="text-sm font-medium text-slate-700">Bilangan maksimum calon</span>
                <span class="rounded-lg bg-slate-900 px-2 py-0.5 text-sm font-bold tabular-nums text-white">{{ $max_candidates }}</span>
            </div>
            <input type="range" min="1" max="60" step="1" wire:model.live.debounce.250ms="max_candidates" class="w-full accent-emerald-600" aria-label="Bilangan maksimum calon">
            <div class="flex justify-between text-[11px] text-slate-400"><span>1</span><span>Default 20, had 60 setiap carian.</span><span>60</span></div>
            @error('max_candidates')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-dark w-full py-3 text-base" @disabled($blocker) wire:loading.attr="disabled">
            <x-icon name="search" class="h-5 w-5" /> Cari
        </button>
    </form>

    @error('estimate') <x-alert type="danger">{{ $message }}</x-alert> @enderror

    @if ($estimate)
        <section class="card overflow-hidden ring-2 ring-emerald-500" aria-label="Anggaran kos">
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 px-4 py-4 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-50/90">Anggaran kos carian ni</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">RM{{ number_format($estimate['total'], 2) }}</p>
                <p class="text-xs text-emerald-50/90">{{ $estimate['candidates'] }} calon · {{ implode(' · ', $estimate['areas']) }}</p>
            </div>
            <dl class="divide-y divide-dashed divide-slate-200 px-4 text-sm">
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Lulus tapisan (~{{ round($estimate['pass_rate'] * 100) }}%)</dt><dd class="font-medium tabular-nums">{{ $estimate['passed'] }} kedai</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Sesuai (~{{ round($estimate['fit_rate'] * 100) }}%)</dt><dd class="font-medium tabular-nums">{{ $estimate['fit'] }} kedai</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Kos AI</dt><dd class="font-medium tabular-nums">RM{{ number_format($estimate['ai'], 2) }}</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Google Places ({{ $estimate['text_search_calls'] + $estimate['details_calls'] }} panggilan)</dt>
                    <dd class="font-medium tabular-nums">@if($estimate['places_prices']) RM{{ number_format($estimate['places'], 2) }} @else <span class="text-amber-700">harga belum diisi</span> @endif</dd></div>
            </dl>
            <div class="space-y-3 p-4 pt-1">
                @if ($estimate['defaults'])
                    <p class="flex gap-1.5 text-xs text-slate-500"><x-icon name="info" class="h-4 w-4 text-slate-400" />Belum cukup data 30 hari, jadi sebahagian anggaran guna nilai default (lulus 50%, sesuai 60%).</p>
                @endif
                <div class="flex gap-2">
                    <button type="button" wire:click="confirm" wire:loading.attr="disabled" class="btn-primary flex-1 py-3">
                        <x-icon name="bolt" class="h-5 w-5" /> Sahkan &amp; cari
                    </button>
                    <button type="button" wire:click="$set('estimate', null)" class="btn-soft">Batal</button>
                </div>
            </div>
        </section>
    @endif

    <section class="space-y-3">
        <h2 class="label">Carian terkini</h2>
        @forelse ($searches as $search)
            @php($step = $search->status->step())
            @php($done = $search->status === \App\Enums\SearchStatus::Done)
            @php($bad = in_array($search->status, [\App\Enums\SearchStatus::Failed, \App\Enums\SearchStatus::BudgetExceeded]))
            <article wire:key="search-{{ $search->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $search->business_type }}</p>
                        <p class="truncate text-xs text-slate-500">{{ implode(' · ', $search->areas) }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ $search->product->name }} · {{ $search->created_at->diffForHumans() }}</p>
                    </div>
                    <span @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200' => $done,
                        'bg-rose-50 text-rose-700 ring-rose-200' => $bad,
                        'bg-sky-50 text-sky-700 ring-sky-200' => ! $search->status->isFinished(),
                    ])>
                        @unless ($search->status->isFinished())<span class="h-1.5 w-1.5 animate-pulse rounded-full bg-sky-500"></span>@endunless
                        {{ $search->status->label() }}
                    </span>
                </div>

                {{-- Stepper: dicari → ditapis → dinilai → siap --}}
                <ol class="mt-4 flex items-center" aria-label="Kemajuan">
                    @foreach (['Dicari', 'Ditapis', 'Dinilai', 'Siap'] as $i => $label)
                        @php($complete = $done || $step > $i + 1)
                        @php($current = ! $done && $step === $i + 1)
                        <li class="flex flex-1 flex-col items-center gap-1 {{ $loop->last ? '' : 'relative' }}">
                            @unless ($loop->last)
                                <span class="absolute left-1/2 top-3 h-0.5 w-full {{ $complete ? 'bg-emerald-500' : 'bg-slate-200' }}"></span>
                            @endunless
                            <span @class([
                                'relative z-10 grid h-6 w-6 place-items-center rounded-full text-[11px] font-bold',
                                'bg-emerald-500 text-white' => $complete,
                                'bg-white text-sky-600 ring-2 ring-sky-500' => $current && ! $bad,
                                'bg-white text-rose-600 ring-2 ring-rose-500' => $current && $bad,
                                'bg-slate-100 text-slate-400' => ! $complete && ! $current,
                            ])>
                                @if ($complete)<x-icon name="check" class="h-3.5 w-3.5" :solid="true" />@else{{ $i + 1 }}@endif
                            </span>
                            <span class="text-[11px] {{ $complete || $current ? 'font-semibold text-slate-700' : 'text-slate-400' }}">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>

                <dl class="mt-4 grid grid-cols-5 gap-1 rounded-xl bg-slate-50 p-2 text-center">
                    @foreach (['Jumpa' => $search->found_count, 'Lulus' => $search->passed_count, 'Lead' => $search->lead_count, 'Dinilai' => $search->scored_count, 'Mesej' => $search->written_count] as $k => $v)
                        <div><dt class="text-[10px] text-slate-500">{{ $k }}</dt><dd class="text-sm font-bold tabular-nums">{{ $v }}</dd></div>
                    @endforeach
                </dl>

                <div class="mt-3 flex items-center justify-between text-xs">
                    <span class="text-slate-500">
                        Anggaran RM{{ number_format((float) $search->estimate_myr, 2) }}
                        @if ($search->status->isFinished()) · <span class="font-semibold text-slate-700">kos sebenar RM{{ number_format($search->actual_cost_myr, 2) }}</span> @endif
                    </span>
                    @if ($done)
                        <a href="{{ route('leads', ['product' => $search->product_id]) }}" wire:navigate class="inline-flex items-center gap-0.5 font-semibold text-emerald-700">Tengok lead <x-icon name="chevron-right" class="h-4 w-4" /></a>
                    @endif
                </div>
                @if ($search->error)
                    <x-alert type="danger" class="mt-3 text-xs">{{ $search->error }}</x-alert>
                @endif
            </article>
        @empty
            <x-empty icon="search" title="Belum ada carian">Cuba carian kecil dulu, contohnya 10 kedai runcit di Pasir Mas.</x-empty>
        @endforelse
    </section>
</div>
