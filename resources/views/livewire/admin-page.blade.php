<div class="space-y-5">
    <x-page-header title="Admin" subtitle="Semua pelanggan dan kos platform bulan ini." />

    @if ($flash)<x-alert type="success">{{ $flash }}</x-alert>@endif

    <dl class="grid grid-cols-2 gap-2">
        @foreach ([
            ['Pelanggan', $totals['customers'].' · '.$totals['paying'].' pernah bayar', 'users'],
            ['Hasil bulan ni', 'RM'.number_format($totals['revenue_month'], 2), 'wallet'],
            ['Kredit dijual', number_format($totals['credits_sold_month']), 'plus'],
            ['Kredit diguna', number_format($totals['credits_used_month']).' · '.$totals['searches_month'].' carian', 'search'],
            ['Kos AI platform', 'RM'.number_format($totals['ai_cost_month'], 2).' / '.number_format($totals['ai_limit'], 0), 'sparkles'],
            ['Panggilan Places', number_format($totals['places_calls_month']).' · RM'.number_format($totals['places_cost_month'], 2), 'map-pin'],
        ] as [$label, $value, $icon])
            <div class="card p-3.5">
                <dt class="flex items-center gap-1.5 text-xs font-medium text-slate-500"><x-icon :name="$icon" class="h-4 w-4 text-slate-400" />{{ $label }}</dt>
                <dd class="mt-1.5 text-base font-bold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-11" placeholder="Cari nama bisnes atau e-mel">
    </div>

    <ul class="space-y-3">
        @foreach ($rows as $row)
            @php($w = $row['workspace'])
            <li wire:key="ws-{{ $w->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $w->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $row['owner']?->email }}</p>
                    </div>
                    @if ($row['unlimited'])
                        <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">Dalaman</span>
                    @elseif ($w->suspended_at)
                        <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">Digantung</span>
                    @else
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700">{{ $w->credits }} kredit</span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-slate-600">{{ $row['searches_month'] }} carian bulan ni · AI RM{{ number_format($row['ai_month'], 2) }} · daftar {{ $w->created_at->translatedFormat('j M Y') }}</p>
                @unless ($row['unlimited'])
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="manage({{ $w->id }})" class="btn-soft px-3 py-1.5 text-xs"><x-icon name="plus" class="h-3.5 w-3.5" />Kredit / bayaran</button>
                        <button type="button" wire:click="toggleSuspend({{ $w->id }})" wire:confirm="Pasti?" class="btn-soft px-3 py-1.5 text-xs {{ $w->suspended_at ? 'text-emerald-700' : 'text-rose-600' }}">{{ $w->suspended_at ? 'Aktifkan' : 'Gantung' }}</button>
                    </div>
                    @if ($managing === $w->id)
                        <div class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                            <div class="grid grid-cols-2 gap-1 rounded-lg bg-white p-1 text-xs font-semibold ring-1 ring-slate-200">
                                <button type="button" wire:click="$set('mode', 'grant')" @class(['rounded-md py-1.5', 'bg-slate-900 text-white' => $mode === 'grant'])>Beri kredit</button>
                                <button type="button" wire:click="$set('mode', 'payment')" @class(['rounded-md py-1.5', 'bg-slate-900 text-white' => $mode === 'payment'])>Bayaran manual</button>
                            </div>
                            @if ($mode === 'grant')
                                <input type="number" min="1" wire:model="amount" class="input py-2 text-sm" placeholder="Bilangan kredit">
                            @else
                                <select wire:model="pack" class="input py-2 text-sm">
                                    @foreach ($packs as $p)<option value="{{ $p->key }}">{{ $p->name }} ({{ $p->credits }} kredit)</option>@endforeach
                                </select>
                            @endif
                            <input type="text" wire:model="note" class="input py-2 text-sm" placeholder="Nota (cth: pindahan bank 7/10)">
                            @error('amount')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                            <button type="button" wire:click="{{ $mode === 'grant' ? 'grant' : 'recordPayment' }}({{ $w->id }})" class="btn-primary w-full">Simpan</button>
                        </div>
                    @endif
                @endunless
            </li>
        @endforeach
    </ul>
</div>
