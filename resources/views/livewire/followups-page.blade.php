<div class="space-y-5" @if($polling) wire:poll.4s @endif>
    <x-page-header title="Follow-up" :subtitle="'Lead “Dah hantar” lebih 3 hari tanpa perubahan.'" />

    <div class="space-y-4">
        @forelse ($cards as $card)
            @php($lead = $card->lead)
            @php($link = $service->whatsappLink($lead, $card->phone))
            <article wire:key="followup-{{ $lead->id }}" class="card overflow-hidden">
                <div class="space-y-4 p-4">
                    <header class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $card->avatarGradient() }} text-lg font-bold text-white">{{ $card->initial() }}</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-semibold">{{ $card->name ?? 'Nama tak dapat dimuat' }}</h2>
                            <p class="text-xs text-slate-500">{{ $lead->product->name }}</p>
                            @if ($card->phone)<p class="whitespace-nowrap text-xs text-slate-500">{{ $card->phone }}</p>@endif
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-200">
                            <x-icon name="clock" class="h-3.5 w-3.5" />{{ $lead->contacted_at?->diffForHumans(short: true) }}
                        </span>
                    </header>

                    <details class="group rounded-xl bg-slate-50 px-3 py-2">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-slate-500">
                            Mesej pertama <x-icon name="chevron-down" class="h-4 w-4 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-2 whitespace-pre-line text-xs leading-relaxed text-slate-600">{{ $lead->message }}</p>
                    </details>

                    @if ($lead->followup_message)
                        <div>
                            <p class="label mb-1.5">Mesej follow-up</p>
                            <x-wa-bubble :message="$lead->followup_message" />
                        </div>
                    @endif

                    @if ($lead->review_note)
                        <x-alert type="danger">{{ $lead->review_note }}</x-alert>
                    @endif

                    @if (isset($feedback[$lead->id]))
                        <x-alert type="info">{{ $feedback[$lead->id] }}</x-alert>
                    @endif
                </div>

                <div class="space-y-2 border-t border-slate-100 bg-slate-50/60 p-3">
                    @if (! $card->isMobile)
                        <span class="flex items-center gap-2 rounded-xl bg-white px-3 py-2.5 text-sm font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                            <x-icon name="store" class="h-4 w-4 text-slate-400" />Telefon atau singgah
                        </span>
                    @elseif ($link)
                        <a href="{{ $link }}" target="_blank" rel="noopener" class="btn-wa w-full py-3 text-[15px]"><x-icon name="chat" class="h-5 w-5" />Buka WhatsApp</a>
                    @endif
                    <div class="flex gap-2 [&>*]:flex-1">
                        @if ($link || ($lead->followup_message && ! $lead->review_note))
                            <x-copy-button :text="$lead->followup_message" class="flex-col gap-1 px-1 py-2 text-xs" />
                        @endif
                        <button type="button" wire:click="generate({{ $lead->id }})" wire:confirm="Jana mesej follow-up guna AI (model murah)? Teruskan?"
                                class="btn-soft flex-col gap-1 px-1 py-2 text-xs"><x-icon name="sparkles" class="h-4 w-4" />{{ $lead->followup_message ? 'Jana semula' : 'Jana mesej follow-up' }}</button>
                        <button type="button" wire:click="markDone({{ $lead->id }})" class="btn-soft flex-col gap-1 px-1 py-2 text-xs"><x-icon name="check-circle" class="h-4 w-4 text-emerald-600" />Dah follow-up</button>
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 p-4">
                    <div class="grid grid-cols-[auto,1fr] items-center gap-3">
                        <span class="label">Status</span>
                        <x-status-select :lead="$lead" :selectable="\App\Enums\LeadStatus::selectable()" />
                    </div>
                    <x-google-attribution :uri="$card->mapsUri" />
                </div>
            </article>
        @empty
            <x-empty icon="check-circle" title="Takde follow-up hari ni 👍">Lead yang dah dihantar lebih 3 hari akan muncul di sini.</x-empty>
        @endforelse
    </div>
</div>
