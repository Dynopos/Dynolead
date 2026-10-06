<div class="space-y-5">
    <x-page-header title="Admin" subtitle="Semua pelanggan, hasil dan kos platform bulan ini." />

    @if ($flash)<x-alert type="success">{{ $flash }}</x-alert>@endif

    @php
        $margin = $totals['usage_charged'] - $totals['usage_cost'];
    @endphp
    <dl class="grid grid-cols-2 gap-2">
        @foreach ([
            ['Pelanggan', $totals['customers'].' · '.$totals['activated'].' aktif · '.$totals['in_trial'].' percubaan', 'users'],
            ['Yuran aktif', 'RM'.number_format($totals['activation_revenue'], 2), 'check-circle'],
            ['Tambah baki', 'RM'.number_format($totals['topup_revenue'], 2), 'wallet'],
            ['Caj guna / untung', 'RM'.number_format($totals['usage_charged'], 2).' · +RM'.number_format($margin, 2), 'plus'],
            ['Kos AI platform', 'RM'.number_format($totals['ai_cost_month'], 2).' / '.number_format($totals['ai_limit'], 0), 'sparkles'],
            ['Places', number_format($totals['places_calls_month']).' · RM'.number_format($totals['places_cost_month'], 2), 'map-pin'],
        ] as [$label, $value, $icon])
            <div class="card p-3.5">
                <dt class="flex items-center gap-1.5 text-xs font-medium text-slate-500"><x-icon :name="$icon" class="h-4 w-4 text-slate-400" />{{ $label }}</dt>
                <dd class="mt-1.5 text-sm font-bold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-11" placeholder="Cari nama bisnes atau e-mel">
    </div>

    <ul class="space-y-3">
        @foreach ($rows as $row)
            @php
                $w = $row['workspace'];
                $tone = match ($row['status']) {
                    'Aktif', 'Dalaman' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                    'Percubaan' => 'bg-sky-50 text-sky-700 ring-sky-200',
                    default => 'bg-rose-50 text-rose-700 ring-rose-200',
                };
            @endphp
            <li wire:key="ws-{{ $w->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $w->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $row['owner']?->email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tone }}">{{ $row['status'] }}</span>
                </div>
                <p class="mt-2 text-xs text-slate-600">
                    @if ($row['status'] === 'Percubaan') lead {{ $row['trial_leads_used'] }}/{{ $trialLeads }} · tamat {{ $w->trial_ends_at?->translatedFormat('j M') }} ·
                    @elseif ($row['status'] !== 'Dalaman') baki <strong>{{ \App\Services\Billing\WalletService::rm($w->balance_sen) }}</strong> ·
                    @endif
                    {{ $row['searches_month'] }} carian bulan ni · kos AI RM{{ number_format($row['ai_month'], 2) }}
                </p>
                @if ($row['status'] !== 'Dalaman')
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="manage({{ $w->id }})" class="btn-soft px-3 py-1.5 text-xs"><x-icon name="plus" class="h-3.5 w-3.5" />Baki / bayaran</button>
                        @unless ($w->activated_at)
                            <button type="button" wire:click="extendTrial({{ $w->id }})" class="btn-soft px-3 py-1.5 text-xs">Percubaan +7 hari</button>
                        @endunless
                        <button type="button" wire:click="toggleSuspend({{ $w->id }})" wire:confirm="Pasti?" class="btn-soft px-3 py-1.5 text-xs {{ $w->suspended_at ? 'text-emerald-700' : 'text-rose-600' }}">{{ $w->suspended_at ? 'Aktifkan semula' : 'Gantung' }}</button>
                    </div>
                    @if ($managing === $w->id)
                        <div class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                            <select wire:model.live="mode" class="input py-2 text-sm">
                                <option value="balance">Beri baki percuma (RM)</option>
                                <option value="topup">Rekod bayaran tambah baki (RM)</option>
                                @unless ($w->activated_at)<option value="activation">Rekod bayaran yuran aktif</option>@endunless
                            </select>
                            @if ($mode !== 'activation')
                                <input type="number" step="0.01" min="1" wire:model="amount" class="input py-2 text-sm" placeholder="Jumlah RM">
                            @endif
                            <input type="text" wire:model="note" class="input py-2 text-sm" placeholder="Nota (cth: pindahan bank 7/10)">
                            @error('amount')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                            <button type="button" wire:click="save({{ $w->id }})" class="btn-primary w-full">Simpan</button>
                        </div>
                    @endif
                @endif
            </li>
        @endforeach
    </ul>
</div>
