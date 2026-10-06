<div class="space-y-4">
    <h1 class="text-xl font-bold">Kos</h1>

    @if ($missingPrices)
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
            Harga belum diisi dalam <code>config/ai_prices.php</code> untuk: {{ implode(', ', $missingPrices) }}.
            AI tak akan dipanggil selagi harga kosong.
        </p>
    @endif

    @if ($month['exhausted'])
        <p class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ \App\Exceptions\BudgetExceeded::MESSAGE }}</p>
    @endif

    <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4" aria-label="Bulan ini">
        <h2 class="font-semibold">Bulan ini ({{ now()->translatedFormat('F Y') }})</h2>
        <div>
            <div class="flex justify-between text-sm">
                <span>Kos AI</span>
                <span><strong>RM{{ number_format($month['ai_cost'], 2) }}</strong> / RM{{ number_format($month['limit'], 2) }}</span>
            </div>
            <div class="mt-1 h-3 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $month['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <div @class(['h-full rounded-full', 'bg-emerald-500' => $month['percent'] < 80, 'bg-amber-500' => $month['percent'] >= 80 && $month['percent'] < 100, 'bg-red-500' => $month['percent'] >= 100]) style="width: {{ $month['percent'] }}%"></div>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $month['percent'] }}% digunakan</p>
        </div>
        <dl class="grid grid-cols-2 gap-y-1 text-sm">
            <dt class="text-slate-600">Panggilan AI</dt><dd class="text-right">{{ number_format($month['ai_calls']) }}</dd>
            <dt class="text-slate-600">Token masuk</dt><dd class="text-right">{{ number_format($month['input_tokens']) }}</dd>
            <dt class="text-slate-600">Token keluar</dt><dd class="text-right">{{ number_format($month['output_tokens']) }}</dd>
            <dt class="text-slate-600">Token dari cache</dt><dd class="text-right">{{ number_format($month['cache_read_tokens']) }}</dd>
            <dt class="text-slate-600">Panggilan Places</dt><dd class="text-right">{{ number_format($month['places_calls']) }}</dd>
            <dt class="text-slate-600">Kos Places</dt><dd class="text-right">RM{{ number_format($month['places_cost'], 2) }}</dd>
        </dl>
    </section>

    <form wire:submit="saveLimit" class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
        <x-field label="Had kos AI bulanan (RM)" name="limit">
            <input type="number" step="1" min="0" wire:model="limit" class="input">
        </x-field>
        <button type="submit" class="w-full rounded-lg bg-slate-800 py-2.5 font-semibold text-white">Simpan had</button>
        @if ($saved)<p class="text-xs text-emerald-800" role="status">{{ $saved }}</p>@endif
    </form>

    <section class="space-y-2">
        <h2 class="font-semibold">50 panggilan AI terakhir</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-2 py-2">Masa</th>
                        <th class="px-2 py-2">Tujuan</th>
                        <th class="px-2 py-2 text-right">Token</th>
                        <th class="px-2 py-2 text-right">RM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calls as $call)
                        <tr class="border-t border-slate-100">
                            <td class="px-2 py-1.5 whitespace-nowrap">{{ $call->created_at?->format('d/m H:i') }}</td>
                            <td class="px-2 py-1.5">{{ $call->purpose }}<span class="block text-[10px] text-slate-400">{{ $call->model }}</span></td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ number_format($call->input_tokens) }} / {{ number_format($call->output_tokens) }}@if($call->cache_read_tokens)<span class="block text-[10px] text-slate-400">cache {{ number_format($call->cache_read_tokens) }}</span>@endif</td>
                            <td class="px-2 py-1.5 text-right">{{ number_format($call->cost_estimate, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-4 text-center text-slate-500">Belum ada panggilan AI.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
