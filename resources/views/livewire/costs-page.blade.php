<div class="space-y-5">
@if (! $isAdmin)
    <x-page-header title="Baki" subtitle="Baki dan sejarah caj carian." />

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 p-5 text-white shadow-lg shadow-emerald-700/20">
        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
        @if (! $activated && ! $unlimited)
            <p class="relative text-xs font-semibold uppercase tracking-wide text-white/80">Percubaan percuma</p>
            <p class="relative mt-1 text-5xl font-bold tabular-nums">{{ min($trialLeadsUsed, config('billing.trial_leads')) }}<span class="text-2xl text-white/70"> / {{ config('billing.trial_leads') }} lead</span></p>
            <p class="relative text-sm text-white/80">{{ $inTrial ? 'Percubaan sedang berjalan' : 'Percubaan dah tamat' }}</p>
            <a href="{{ route('billing') }}" wire:navigate class="btn relative mt-4 bg-white px-4 py-2.5 text-emerald-700 hover:bg-emerald-50">Aktifkan akaun</a>
        @else
            <p class="relative text-xs font-semibold uppercase tracking-wide text-white/80">Baki</p>
            <p class="relative mt-1 text-5xl font-bold tabular-nums">{{ $unlimited ? '∞' : \App\Services\Billing\WalletService::rm($balance) }}</p>
            <p class="relative text-sm text-white/80">Caj bulan ni {{ \App\Services\Billing\WalletService::rm($chargedThisMonth) }} · {{ $searchesThisMonth }} carian</p>
            <a href="{{ route('billing') }}" wire:navigate class="btn relative mt-4 bg-white px-4 py-2.5 text-emerald-700 hover:bg-emerald-50"><x-icon name="plus" class="h-4 w-4" />Tambah baki</a>
        @endif
    </section>

    <section class="space-y-2">
        <h2 class="label">Sejarah</h2>
        <ul class="card divide-y divide-slate-100">
            @forelse ($ledger as $t)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ $t->label() }}</p>
                        <p class="truncate text-xs text-slate-500">
                            @if ($t->search){{ $t->search->business_type }} · {{ implode(', ', $t->search->areas) }} · @endif{{ $t->created_at?->translatedFormat('j M, g:i a') }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p @class(['text-sm font-bold tabular-nums', 'text-emerald-600' => $t->amount_sen > 0, 'text-slate-700' => $t->amount_sen < 0])>{{ $t->amount_sen > 0 ? '+' : '' }}{{ \App\Services\Billing\WalletService::rm($t->amount_sen) }}</p>
                        <p class="text-[11px] text-slate-400">baki {{ \App\Services\Billing\WalletService::rm($t->balance_after_sen) }}</p>
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">Belum ada transaksi.</li>
            @endforelse
        </ul>
    </section>
@else
    <x-page-header title="Kos" subtitle="Penggunaan AI dan Google Places bulan ini." />

    @if ($missingPrices)
        <x-alert type="warning">
            Harga belum diisi dalam <code class="rounded bg-amber-100 px-1">config/ai_prices.php</code> untuk: {{ implode(', ', $missingPrices) }}.
            AI tak akan dipanggil selagi harga kosong.
        </x-alert>
    @endif

    @if ($month['exhausted'])
        <x-alert type="danger">{{ \App\Exceptions\BudgetExceeded::MESSAGE }}</x-alert>
    @endif

    {{-- Hero: AI spend vs limit --}}
    <section @class([
        'relative overflow-hidden rounded-2xl p-5 text-white shadow-lg',
        'bg-gradient-to-br from-emerald-500 to-teal-700 shadow-emerald-700/20' => $month['percent'] < 80,
        'bg-gradient-to-br from-amber-500 to-orange-600 shadow-orange-700/20' => $month['percent'] >= 80 && $month['percent'] < 100,
        'bg-gradient-to-br from-rose-500 to-red-700 shadow-red-700/20' => $month['percent'] >= 100,
    ]) aria-label="Bulan ini">
        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
        <p class="relative text-xs font-semibold uppercase tracking-wide text-white/80">Kos AI · {{ now()->translatedFormat('F Y') }}</p>
        <p class="relative mt-1 text-4xl font-bold tabular-nums">RM{{ number_format($month['ai_cost'], 2) }}</p>
        <p class="relative text-sm text-white/80">daripada had RM{{ number_format($month['limit'], 2) }}</p>
        <div class="relative mt-4 h-2.5 w-full overflow-hidden rounded-full bg-white/25" role="progressbar" aria-valuenow="{{ $month['percent'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-white" style="width: {{ max(2, $month['percent']) }}%"></div>
        </div>
        <p class="relative mt-1.5 text-xs text-white/80">{{ $month['percent'] }}% digunakan · baki RM{{ number_format(max(0, $month['limit'] - $month['ai_cost']), 2) }}</p>
    </section>


    {{-- Stat tiles --}}
    <dl class="grid grid-cols-2 gap-2">
        @foreach ([
            ['Panggilan AI', number_format($month['ai_calls']), 'sparkles', 'text-violet-600 bg-violet-50'],
            ['Token masuk', number_format($month['input_tokens']), 'note', 'text-sky-600 bg-sky-50'],
            ['Token keluar', number_format($month['output_tokens']), 'chat', 'text-emerald-600 bg-emerald-50'],
            ['Token dari cache', number_format($month['cache_read_tokens']), 'bolt', 'text-amber-600 bg-amber-50'],
            ['Panggilan Places', number_format($month['places_calls']), 'map-pin', 'text-rose-600 bg-rose-50'],
            ['Kos Places', 'RM'.number_format($month['places_cost'], 2), 'wallet', 'text-teal-600 bg-teal-50'],
        ] as [$label, $value, $icon, $tone])
            <div class="card p-3.5">
                <dt class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="grid h-7 w-7 place-items-center rounded-lg {{ $tone }}"><x-icon :name="$icon" class="h-4 w-4" /></span>{{ $label }}
                </dt>
                <dd class="mt-2 text-lg font-bold tabular-nums text-slate-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- Limit --}}
    <form wire:submit="saveLimit" class="card space-y-2 p-4">
        <label for="limit" class="block text-sm font-medium text-slate-700">Had kos AI bulanan</label>
        <div class="flex gap-2">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">RM</span>
                <input id="limit" type="number" step="1" min="0" wire:model="limit" class="input pl-11 tabular-nums">
            </div>
            <button type="submit" class="btn-dark">Simpan had</button>
        </div>
        @error('limit')<p class="text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        @if ($saved)<p class="flex items-center gap-1 text-xs font-medium text-emerald-700" role="status"><x-icon name="check-circle" class="h-4 w-4" />{{ $saved }}</p>@endif
    </form>

    {{-- Last calls --}}
    <section class="space-y-2">
        <h2 class="label">50 panggilan AI terakhir</h2>
        <ul class="card divide-y divide-slate-100">
            @forelse ($calls as $call)
                @php($tone = ['score' => 'bg-sky-50 text-sky-700', 'write' => 'bg-emerald-50 text-emerald-700', 'followup' => 'bg-violet-50 text-violet-700'][$call->purpose] ?? 'bg-slate-100 text-slate-600')
                @php($label = ['score' => 'Nilai', 'write' => 'Tulis', 'followup' => 'Follow-up'][$call->purpose] ?? $call->purpose)
                <li class="flex items-center gap-3 px-3.5 py-2.5">
                    <span class="w-[72px] shrink-0 rounded-lg px-2 py-1 text-center text-[11px] font-semibold {{ $tone }}">{{ $label }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] text-slate-400">{{ $call->model }}</p>
                        <p class="text-xs tabular-nums text-slate-600">
                            {{ number_format($call->input_tokens) }} masuk · {{ number_format($call->output_tokens) }} keluar
                            @if ($call->cache_read_tokens) · {{ number_format($call->cache_read_tokens) }} cache @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold tabular-nums">RM{{ number_format($call->cost_estimate, 4) }}</p>
                        <p class="text-[11px] text-slate-400">{{ $call->created_at?->format('d/m H:i') }}</p>
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">Belum ada panggilan AI.</li>
            @endforelse
        </ul>
    </section>
@endif
</div>
