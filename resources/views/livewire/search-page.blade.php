<div class="space-y-4" @if($running) wire:poll.3s @endif>
    <h1 class="text-xl font-bold">Cari kedai</h1>

    @if ($blocker)
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $blocker }}</p>
    @endif

    <form wire:submit="calculate" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <x-field label="Produk" name="product_id">
            <select wire:model.live="product_id" class="input">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Jenis bisnes" name="business_type">
            <input type="text" wire:model.blur="business_type" class="input" placeholder="cth: kedai runcit">
        </x-field>
        @if ($suggestedTypes)
            <div class="-mt-1 flex flex-wrap gap-1">
                @foreach ($suggestedTypes as $type)
                    <button type="button" wire:click="pickType(@js($type))"
                            class="rounded-full border border-slate-300 px-3 py-1 text-xs {{ $business_type === $type ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white' }}">{{ $type }}</button>
                @endforeach
            </div>
        @endif

        <x-field label="Kawasan" name="areas" hint="Satu kawasan setiap baris (maksimum 5). Cth: Pasir Mas, Kelantan">
            <textarea wire:model.blur="areas" rows="2" class="input" placeholder="Pasir Mas, Kelantan"></textarea>
        </x-field>

        <x-field label="Bilangan maksimum calon" name="max_candidates" hint="Default 20, had 60 setiap carian.">
            <input type="number" min="1" max="60" wire:model.blur="max_candidates" class="input">
        </x-field>

        <button type="submit" class="w-full rounded-lg bg-slate-800 py-2.5 font-semibold text-white" @disabled($blocker)>Cari</button>
    </form>

    @error('estimate') <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

    @if ($estimate)
        <section class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4" aria-label="Anggaran kos">
            <h2 class="font-semibold text-emerald-900">Anggaran kos carian ni</h2>
            <dl class="grid grid-cols-2 gap-y-1 text-sm">
                <dt class="text-slate-600">Calon maksimum</dt><dd class="text-right">{{ $estimate['candidates'] }}</dd>
                <dt class="text-slate-600">Lulus tapisan (~{{ round($estimate['pass_rate'] * 100) }}%)</dt><dd class="text-right">{{ $estimate['passed'] }}</dd>
                <dt class="text-slate-600">Sesuai (~{{ round($estimate['fit_rate'] * 100) }}%)</dt><dd class="text-right">{{ $estimate['fit'] }}</dd>
                <dt class="text-slate-600">Kos AI</dt><dd class="text-right">RM{{ number_format($estimate['ai'], 2) }}</dd>
                <dt class="text-slate-600">Google Places ({{ $estimate['text_search_calls'] + $estimate['details_calls'] }} panggilan)</dt>
                <dd class="text-right">@if($estimate['places_prices']) RM{{ number_format($estimate['places'], 2) }} @else <span class="text-amber-700">harga belum diisi</span> @endif</dd>
                <dt class="font-semibold">Jumlah anggaran</dt><dd class="text-right font-semibold">RM{{ number_format($estimate['total'], 2) }}</dd>
            </dl>
            @if ($estimate['defaults'])
                <p class="text-xs text-slate-600">Belum cukup data 30 hari, jadi sebahagian anggaran guna nilai default (lulus 50%, sesuai 60%).</p>
            @endif
            <div class="flex gap-2 pt-1">
                <button type="button" wire:click="confirm" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-emerald-600 py-2.5 font-semibold text-white">Sahkan &amp; cari</button>
                <button type="button" wire:click="$set('estimate', null)" class="rounded-lg border border-slate-300 bg-white px-4">Batal</button>
            </div>
        </section>
    @endif

    <section class="space-y-2">
        <h2 class="font-semibold">Carian terkini</h2>
        @forelse ($searches as $search)
            @php($step = $search->status->step())
            <article wire:key="search-{{ $search->id }}" class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ $search->business_type }} · {{ implode(', ', $search->areas) }}</p>
                        <p class="text-xs text-slate-500">{{ $search->product->name }} · {{ $search->created_at->diffForHumans() }}</p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2 py-0.5 text-xs',
                        'bg-emerald-100 text-emerald-800' => $search->status === \App\Enums\SearchStatus::Done,
                        'bg-red-100 text-red-800' => in_array($search->status, [\App\Enums\SearchStatus::Failed, \App\Enums\SearchStatus::BudgetExceeded]),
                        'bg-sky-100 text-sky-800' => ! $search->status->isFinished(),
                    ])>{{ $search->status->label() }}</span>
                </div>

                <ol class="mt-2 grid grid-cols-4 gap-1 text-center text-[11px]" aria-label="Kemajuan">
                    @foreach (['Dicari', 'Ditapis', 'Dinilai', 'Siap'] as $i => $label)
                        <li @class(['rounded py-1', 'bg-emerald-600 text-white' => $step > $i + 1 || $search->status === \App\Enums\SearchStatus::Done, 'bg-emerald-100 text-emerald-900' => $step === $i + 1 && $search->status !== \App\Enums\SearchStatus::Done, 'bg-slate-100 text-slate-500' => $step < $i + 1])>{{ $label }}</li>
                    @endforeach
                </ol>

                <p class="mt-2 text-xs text-slate-600">
                    Jumpa {{ $search->found_count }} · lulus tapisan {{ $search->passed_count }} · lead {{ $search->lead_count }}
                    · dinilai {{ $search->scored_count }} · mesej {{ $search->written_count }}
                </p>
                <p class="text-xs text-slate-500">
                    Anggaran RM{{ number_format((float) $search->estimate_myr, 2) }}
                    @if ($search->status->isFinished()) · kos sebenar RM{{ number_format($search->actual_cost_myr, 2) }} @endif
                </p>
                @if ($search->error)
                    <p class="mt-1 text-xs text-red-700">{{ $search->error }}</p>
                @endif
                @if ($search->status === \App\Enums\SearchStatus::Done)
                    <a href="{{ route('leads', ['product' => $search->product_id]) }}" wire:navigate class="mt-1 inline-block text-xs font-semibold text-emerald-700">Tengok lead →</a>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Belum ada carian.</p>
        @endforelse
    </section>
</div>
