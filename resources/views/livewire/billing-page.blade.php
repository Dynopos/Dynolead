<div class="space-y-5">
    <x-page-header title="Langganan" subtitle="Pelan, kuota dan bayaran." />

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif
    @if (session('warning'))
        <x-alert type="warning">{{ session('warning') }}</x-alert>
    @endif
    @if ($blocker)
        <x-alert type="warning">{{ $blocker }}</x-alert>
    @endif

    {{-- Current plan --}}
    <section class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="label">Pelan semasa</p>
                <p class="mt-1 text-xl font-bold">{{ $plan->name }}</p>
                <p class="text-sm text-slate-500">
                    @if ($endsAt === null)
                        Tiada tarikh tamat.
                    @elseif ($active)
                        {{ $isTrial ? 'Percubaan tamat' : 'Aktif sehingga' }} {{ $endsAt->translatedFormat('j M Y') }} ({{ $endsAt->diffForHumans() }})
                    @else
                        Tamat {{ $endsAt->translatedFormat('j M Y') }}
                    @endif
                </p>
            </div>
            <span @class(['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $active, 'bg-rose-50 text-rose-700 ring-rose-200' => ! $active])>
                {{ $active ? 'Aktif' : 'Tamat' }}
            </span>
        </div>

        <dl class="mt-4 space-y-3">
            <div>
                <div class="flex justify-between text-sm"><dt class="text-slate-500">Lead bulan ni</dt><dd class="font-semibold tabular-nums">{{ $leadsUsed }}{{ $plan->monthlyLeads !== null ? ' / '.$plan->monthlyLeads : '' }}</dd></div>
                @if ($plan->monthlyLeads)
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, round($leadsUsed / max(1, $plan->monthlyLeads) * 100)) }}%"></div></div>
                @endif
            </div>
            <div>
                <div class="flex justify-between text-sm"><dt class="text-slate-500">Penggunaan AI bulan ni</dt><dd class="font-semibold tabular-nums">{{ $aiLimit > 0 ? min(100, round($aiSpent / $aiLimit * 100)) : 100 }}%</dd></div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-sky-500" style="width: {{ $aiLimit > 0 ? min(100, round($aiSpent / $aiLimit * 100)) : 100 }}%"></div></div>
            </div>
        </dl>
    </section>

    {{-- Plans --}}
    <section class="space-y-3">
        <h2 class="label">Pilih pelan</h2>
        @foreach ($plans as $p)
            <article wire:key="plan-{{ $p->key }}" @class(['card p-4', 'ring-2 ring-emerald-500' => $p->key === $plan->key])>
                <div class="flex items-baseline justify-between">
                    <h3 class="text-lg font-bold">{{ $p->name }}</h3>
                    <p class="text-right">
                        @if ($p->priceMyr !== null)
                            <span class="text-2xl font-bold tabular-nums">RM{{ number_format($p->priceMyr, 0) }}</span><span class="text-sm text-slate-500"> / 30 hari</span>
                        @else
                            <span class="text-sm text-slate-400">Harga belum ditetapkan</span>
                        @endif
                    </p>
                </div>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    @foreach ($p->features as $f)
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" :solid="true" />{{ $f }}</li>
                    @endforeach
                </ul>
                @if ($p->isForSale())
                    <button type="button" wire:click="subscribe('{{ $p->key }}')" wire:loading.attr="disabled" class="btn-primary mt-4 w-full py-3">
                        {{ $p->key === $plan->key && $active ? 'Sambung 30 hari' : 'Langgan '.$p->name }}
                    </button>
                @else
                    <button type="button" disabled class="btn-soft mt-4 w-full">Belum dibuka</button>
                @endif
            </article>
        @endforeach
        <p class="text-xs text-slate-500">Bayaran sekali untuk setiap 30 hari melalui CHIP (FPX, kad, e-wallet). Tiada caj automatik.</p>
    </section>

    @if ($payments->isNotEmpty())
        <section class="space-y-2">
            <h2 class="label">Sejarah bayaran</h2>
            <ul class="card divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ ucfirst($payment->plan) }} · {{ $payment->reference() }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->period_start?->translatedFormat('j M') }} – {{ $payment->period_end?->translatedFormat('j M Y') }}</p>
                        </div>
                        <p class="font-semibold tabular-nums">RM{{ number_format($payment->amountMyr(), 2) }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
