<div class="space-y-5">
    <x-page-header title="Admin" subtitle="Semua pelanggan dan kos platform bulan ini." />

    @if ($flash)<x-alert type="success">{{ $flash }}</x-alert>@endif

    <dl class="grid grid-cols-2 gap-2">
        @foreach ([
            ['Pelanggan', $totals['customers'], 'users'],
            ['Langganan aktif', $totals['active_paid'], 'check-circle'],
            ['Dalam percubaan', $totals['trials'], 'clock'],
            ['Hasil bulan ni', 'RM'.number_format($totals['revenue_month'], 2), 'wallet'],
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
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                        'bg-rose-50 text-rose-700 ring-rose-200' => $w->suspended_at || ! $row['active'],
                        'bg-emerald-50 text-emerald-700 ring-emerald-200' => ! $w->suspended_at && $row['active'],
                    ])>{{ $w->suspended_at ? 'Digantung' : ($row['active'] ? $row['plan']->name : 'Tamat') }}</span>
                </div>
                <p class="mt-2 text-xs text-slate-600">
                    {{ $row['ends_at'] ? 'Sehingga '.$row['ends_at']->translatedFormat('j M Y') : 'Tiada tamat' }}
                    · lead {{ $row['leads_month'] }}{{ $row['plan']->monthlyLeads ? '/'.$row['plan']->monthlyLeads : '' }}
                    · AI RM{{ number_format($row['ai_month'], 2) }}
                </p>
                @if ($w->plan !== 'dalaman')
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="manage({{ $w->id }})" class="btn-soft px-3 py-1.5 text-xs">+30 hari (manual)</button>
                        <button type="button" wire:click="extendTrial({{ $w->id }})" class="btn-soft px-3 py-1.5 text-xs">Percubaan +7 hari</button>
                        <button type="button" wire:click="toggleSuspend({{ $w->id }})" wire:confirm="Pasti?" class="btn-soft px-3 py-1.5 text-xs {{ $w->suspended_at ? 'text-emerald-700' : 'text-rose-600' }}">{{ $w->suspended_at ? 'Aktifkan' : 'Gantung' }}</button>
                    </div>
                    @if ($managing === $w->id)
                        <div class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                            <select wire:model="plan" class="input py-2 text-sm">
                                @foreach ($salePlans as $p)<option value="{{ $p->key }}">{{ $p->name }}</option>@endforeach
                            </select>
                            <input type="text" wire:model="note" class="input py-2 text-sm" placeholder="Nota (cth: pindahan bank 7/10)">
                            <button type="button" wire:click="addPeriod({{ $w->id }})" class="btn-primary w-full">Rekod bayaran &amp; tambah 30 hari</button>
                        </div>
                    @endif
                @endif
            </li>
        @endforeach
    </ul>
</div>
