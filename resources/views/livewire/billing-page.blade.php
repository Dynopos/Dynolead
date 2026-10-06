<div class="space-y-5">
    <x-page-header title="Bayaran" subtitle="Tiada yuran bulanan. Bayar ikut carian." />

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif
    @if (session('warning'))
        <x-alert type="warning">{{ session('warning') }}</x-alert>
    @endif

    @if ($unlimited)
        <x-alert type="info">Akaun dalaman: tiada had dan tiada caj.</x-alert>
    @elseif (! $activated)
        {{-- Trial status + activation --}}
        <section class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="label">Percubaan percuma</p>
                    <p class="mt-1 text-xl font-bold">{{ $inTrial ? 'Sedang berjalan' : 'Dah tamat' }}</p>
                    <p class="text-sm text-slate-500">{{ $trialLeads }} lead atau {{ config('billing.trial_days') }} hari, mana dulu.</p>
                </div>
                <span @class(['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', 'bg-sky-50 text-sky-700 ring-sky-200' => $inTrial, 'bg-rose-50 text-rose-700 ring-rose-200' => ! $inTrial])>{{ $inTrial ? 'Percubaan' : 'Tamat' }}</span>
            </div>
            <dl class="mt-4 space-y-3">
                <div>
                    <div class="flex justify-between text-sm"><dt class="text-slate-500">Lead digunakan</dt><dd class="font-semibold tabular-nums">{{ min($trialLeadsUsed, $trialLeads) }} / {{ $trialLeads }}</dd></div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-sky-500" style="width: {{ min(100, round($trialLeadsUsed / max(1, $trialLeads) * 100)) }}%"></div></div>
                </div>
                @if ($trialEndsAt)
                    <div class="flex justify-between text-sm"><dt class="text-slate-500">Tamat</dt><dd class="font-semibold">{{ $trialEndsAt->translatedFormat('j M Y') }} ({{ $trialEndsAt->diffForHumans() }})</dd></div>
                @endif
            </dl>
        </section>

        <section class="card overflow-hidden ring-2 ring-emerald-500">
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Aktifkan akaun</p>
                <p class="mt-1 text-4xl font-bold tabular-nums">{{ $fee }}</p>
                <p class="text-sm text-white/85">Sekali bayar sahaja. Tiada yuran bulanan.</p>
            </div>
            <ul class="space-y-2 p-4 text-sm text-slate-600">
                <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" :solid="true" />Cari lead tanpa had percubaan</li>
                <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" :solid="true" />Bayar ikut carian dari baki anda</li>
                <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-emerald-600" :solid="true" />Lead dan mesej sedia ada kekal</li>
            </ul>
            <div class="px-4 pb-4">
                <button type="button" wire:click="activate" wire:loading.attr="disabled" class="btn-primary w-full py-3">Aktifkan akaun {{ $fee }}</button>
            </div>
        </section>
    @else
        {{-- Active: balance + top up --}}
        <section class="card flex items-center justify-between p-4">
            <div>
                <p class="label">Baki anda</p>
                <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-rose-600' => $balance <= 0])>{{ \App\Services\Billing\WalletService::rm($balance) }}</p>
            </div>
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600"><x-icon name="wallet" class="h-6 w-6" /></span>
        </section>

        <section class="space-y-3">
            <h2 class="label">Tambah baki</h2>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($topups as $amount)
                    <button type="button" wire:click="topup({{ $amount }})" wire:loading.attr="disabled" class="card py-4 text-center transition hover:ring-2 hover:ring-emerald-500">
                        <span class="block text-xl font-bold tabular-nums">RM{{ $amount }}</span>
                    </button>
                @endforeach
            </div>
            <p class="text-xs text-slate-500">Bayaran sekali melalui CHIP (FPX, kad, e-wallet). Baki tak luput.</p>
        </section>
    @endif

    <x-alert type="info">
        Caj carian = kos sebenar AI{{ $includesPlaces ? ' dan Google Maps' : '' }} + {{ rtrim(rtrim(number_format($markup, 2), '0'), '.') }}%.
        Anggaran ditunjuk sebelum setiap carian. Jana semula mesej dan follow-up AI percuma (had {{ config('billing.max_regenerations_per_lead') }} kali setiap lead).
    </x-alert>

    @if ($payments->isNotEmpty())
        <section class="space-y-2">
            <h2 class="label">Sejarah bayaran</h2>
            <ul class="card divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $payment->kind === 'activation' ? 'Aktifkan akaun' : 'Tambah baki' }} · {{ $payment->reference() }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->paid_at?->translatedFormat('j M Y, g:i a') }}</p>
                        </div>
                        <p class="font-semibold tabular-nums">RM{{ number_format($payment->amountMyr(), 2) }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
