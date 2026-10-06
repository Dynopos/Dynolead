<div class="space-y-4" @if($polling) wire:poll.4s @endif>
    <h1 class="text-xl font-bold">Follow-up</h1>
    <p class="text-sm text-slate-600">Lead "Dah hantar" lebih 3 hari tanpa perubahan.</p>

    @forelse ($cards as $card)
        @php($lead = $card->lead)
        @php($link = $service->whatsappLink($lead, $card->phone))
        <article wire:key="followup-{{ $lead->id }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
            <header>
                <h2 class="font-semibold">{{ $card->name ?? 'Nama tak dapat dimuat' }}</h2>
                <p class="text-xs text-slate-600">
                    {{ $lead->product->name }} · dihantar {{ $lead->contacted_at?->diffForHumans() }}
                    @if ($card->phone) · {{ $card->phone }} @endif
                </p>
            </header>

            <details class="text-sm">
                <summary class="cursor-pointer text-xs text-slate-500">Mesej pertama</summary>
                <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-2 text-xs">{{ $lead->message }}</p>
            </details>

            @if ($lead->followup_message)
                <div>
                    <p class="text-xs font-semibold text-slate-500">Mesej follow-up</p>
                    <p class="whitespace-pre-line rounded-lg bg-emerald-50 p-3 text-sm">{{ $lead->followup_message }}</p>
                </div>
            @endif

            @if ($lead->review_note)
                <p class="rounded-lg bg-red-50 p-2 text-xs text-red-800">{{ $lead->review_note }}</p>
            @endif

            <div class="flex flex-wrap gap-2">
                @if (! $card->isMobile)
                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">Telefon atau singgah</span>
                @elseif ($link)
                    <a href="{{ $link }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Buka WhatsApp</a>
                @endif
                @if ($link || ($lead->followup_message && ! $lead->review_note))
                    <x-copy-button :text="$lead->followup_message" />
                @endif
                <button type="button" wire:click="generate({{ $lead->id }})" wire:confirm="Jana mesej follow-up guna AI (model murah)? Teruskan?"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $lead->followup_message ? 'Jana semula' : 'Jana mesej follow-up' }}</button>
                <button type="button" wire:click="markDone({{ $lead->id }})" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Dah follow-up</button>
            </div>

            <label class="block text-xs font-semibold text-slate-500">Status
                <select class="input mt-1 text-sm"
                        x-on:change="if ($event.target.value !== 'tolak' || confirm('Tanda STOP? Kedai ni takkan muncul lagi untuk semua produk.')) { $wire.setStatus({{ $lead->id }}, $event.target.value) } else { $event.target.value = '{{ $lead->status->value }}' }">
                    @foreach (\App\Enums\LeadStatus::selectable() as $s)
                        <option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </label>

            @if (isset($feedback[$lead->id]))
                <p class="text-xs font-medium text-emerald-800" role="status">{{ $feedback[$lead->id] }}</p>
            @endif

            <x-google-attribution :uri="$card->mapsUri" />
        </article>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Takde follow-up hari ni. 👍</p>
    @endforelse
</div>
