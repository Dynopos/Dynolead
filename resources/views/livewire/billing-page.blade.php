<div class="space-y-5">
    <x-page-header title="Tambah kredit" subtitle="Bayar ikut carian. Kredit tak luput." />

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif
    @if (session('warning'))
        <x-alert type="warning">{{ session('warning') }}</x-alert>
    @endif

    <section class="card flex items-center justify-between p-4">
        <div>
            <p class="label">Baki anda</p>
            <p class="mt-1 text-3xl font-bold tabular-nums">{{ $unlimited ? '∞' : $balance }} <span class="text-base font-semibold text-slate-500">kredit</span></p>
        </div>
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600"><x-icon name="wallet" class="h-6 w-6" /></span>
    </section>

    <x-alert type="info">1 kredit = 1 carian sehingga {{ $perCredit }} kedai. Carian {{ $perCredit * 2 }} kedai guna 2 kredit, {{ $perCredit * 3 }} kedai guna 3 kredit. Kredit dipulangkan jika carian tak jumpa satu lead pun.</x-alert>

    <section class="space-y-3">
        <h2 class="label">Pilih pek</h2>
        @foreach ($packs as $p)
            <article wire:key="pack-{{ $p->key }}" @class(['card relative p-4', 'ring-2 ring-emerald-500' => $p->popular])>
                @if ($p->popular)
                    <span class="absolute -top-2.5 right-4 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[11px] font-bold text-white">Paling popular</span>
                @endif
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">{{ $p->name }}</h3>
                        <p class="text-sm text-slate-500">{{ $p->credits }} carian · sehingga {{ $p->credits * $perCredit }} kedai</p>
                    </div>
                    <div class="text-right">
                        @if ($p->priceMyr !== null)
                            <p class="text-2xl font-bold tabular-nums">RM{{ number_format($p->priceMyr, 0) }}</p>
                            <p class="text-xs text-slate-500">RM{{ number_format($p->pricePerCredit(), 2) }} / kredit</p>
                        @else
                            <p class="text-sm text-slate-400">Harga belum ditetapkan</p>
                        @endif
                    </div>
                </div>
                @if ($p->isForSale())
                    <button type="button" wire:click="buy('{{ $p->key }}')" wire:loading.attr="disabled" class="{{ $p->popular ? 'btn-primary' : 'btn-dark' }} mt-4 w-full py-3">Beli {{ $p->name }}</button>
                @else
                    <button type="button" disabled class="btn-soft mt-4 w-full">Belum dibuka</button>
                @endif
            </article>
        @endforeach
        <p class="text-xs text-slate-500">Bayaran sekali melalui CHIP (FPX, kad, e-wallet). Tiada langganan, tiada caj automatik.</p>
    </section>

    @if ($payments->isNotEmpty())
        <section class="space-y-2">
            <h2 class="label">Sejarah pembelian</h2>
            <ul class="card divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $payment->credits }} kredit · {{ $payment->reference() }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->paid_at?->translatedFormat('j M Y, g:i a') }}</p>
                        </div>
                        <p class="font-semibold tabular-nums">RM{{ number_format($payment->amountMyr(), 2) }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
