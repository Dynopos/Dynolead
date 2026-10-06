<div class="space-y-5" @if($regenerating) wire:poll.4s @endif>
    <x-page-header title="Lead" subtitle="Semak mesej, buka WhatsApp, hantar sendiri.">
        <a href="{{ route('search') }}" wire:navigate class="btn-primary px-3 py-2">
            <x-icon name="plus" class="h-4 w-4" /> Cari
        </a>
    </x-page-header>

    @if ($notice)
        <x-alert type="success">{{ $notice }}</x-alert>
    @endif

    {{-- Summary per status --}}
    <div class="grid grid-cols-3 gap-2" aria-label="Ringkasan">
        @foreach ($statuses as $s)
            @php($isActive = $status === $s->value)
            <button type="button" wire:click="$set('status', '{{ $s === \App\Enums\LeadStatus::Tolak ? '' : $s->value }}')"
                    @class(['card px-3 py-2.5 text-left transition', 'ring-2 ring-emerald-500' => $isActive])>
                <span class="flex items-center gap-1.5 text-[11px] font-medium text-slate-500">
                    <span class="h-2 w-2 rounded-full {{ $s->colors()[0] }}"></span>{{ $s->label() }}
                </span>
                <span class="mt-0.5 block text-xl font-bold tabular-nums text-slate-900">{{ $summary[$s->value] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Daily pace --}}
    @php($pace = min(100, round($sentToday / 15 * 100)))
    <div class="card p-3.5">
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-slate-700">Hantar hari ni</span>
            <span class="font-semibold tabular-nums">{{ $sentToday }} <span class="font-normal text-slate-400">/ 15</span></span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
            <div @class(['h-full rounded-full transition-all', 'bg-emerald-500' => $sentToday < 15, 'bg-amber-500' => $sentToday >= 15]) style="width: {{ $pace }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-500">Hari ni dah tanda <strong>{{ $sentToday }}</strong> mesej dihantar. Cadangan: 10–15 mesej sehari setiap nombor supaya nombor tak disekat.</p>
    </div>

    {{-- Filters --}}
    @php($filterCount = collect([$productId, $businessType, $status, $area])->filter()->count())
    <details class="card group" @if($filterCount) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
            <span class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="funnel" class="h-4 w-4 text-slate-400" /> Penapis
                @if ($filterCount)<span class="rounded-full bg-emerald-600 px-1.5 text-[11px] text-white">{{ $filterCount }}</span>@endif
            </span>
            <x-icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
        </summary>
        <div class="grid grid-cols-2 gap-2 px-4 pb-4">
            <select wire:model.live="productId" class="input py-2 text-sm" aria-label="Produk">
                <option value="">Semua produk</option>
                @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="input py-2 text-sm" aria-label="Status">
                <option value="">Semua (aktif)</option>
                @foreach ($statuses as $s)
                    @continue($s === \App\Enums\LeadStatus::Tolak)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="businessType" class="input py-2 text-sm" aria-label="Jenis">
                <option value="">Semua jenis</option>
                @foreach ($options['business_types'] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>
            <select wire:model.live="area" class="input py-2 text-sm" aria-label="Kawasan">
                <option value="">Semua kawasan</option>
                @foreach ($options['areas'] as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
            </select>
            @if ($filterCount)
                <button type="button" wire:click="clearFilters" class="col-span-2 text-left text-xs font-medium text-slate-500 underline">Kosongkan penapis</button>
            @endif
        </div>
    </details>

    {{-- Cards --}}
    <div class="space-y-4">
        @forelse ($cards as $card)
            @php($lead = $card->lead)
            <article wire:key="lead-{{ $lead->id }}" class="card overflow-hidden">
                <div class="space-y-4 p-4">
                    <header class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $card->avatarGradient() }} text-lg font-bold text-white">{{ $card->initial() }}</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-semibold leading-snug text-slate-900">{{ $card->name ?? 'Nama tak dapat dimuat' }}</h2>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500">
                                @if ($card->rating)
                                    <span class="inline-flex items-center gap-0.5 font-semibold text-slate-700"><x-icon name="star" class="h-3.5 w-3.5 text-amber-400" />{{ number_format($card->rating, 1) }}</span>
                                    <span>({{ $card->reviewCount }} review)</span><span aria-hidden="true">·</span>
                                @endif
                                <span>{{ $lead->business_type }}</span>
                            </p>
                        </div>
                        <x-score :fit="$lead->fit" />
                    </header>

                    <div class="flex flex-wrap gap-1.5">
                        @if ($lead->area)<span class="chip"><x-icon name="map-pin" class="h-3.5 w-3.5" />{{ $lead->area }}</span>@endif
                        @if ($card->phone)<span class="chip"><x-icon name="phone" class="h-3.5 w-3.5" />{{ $card->phone }}</span>@endif
                        <span class="chip bg-emerald-50 text-emerald-700"><x-icon name="cube" class="h-3.5 w-3.5" />{{ $lead->product->name }}</span>
                    </div>

                    @if ($lead->reason)
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><x-icon name="sparkles" class="h-4 w-4" />Kenapa sesuai</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $lead->reason }}</p>
                        </div>
                    @endif

                    @if ($lead->flag)
                        <x-alert type="warning">{{ $lead->flag }}</x-alert>
                    @endif

                    @if ($lead->review_note)
                        <x-alert :type="$lead->review_note === \App\Services\Leads\LeadService::REGENERATING ? 'info' : 'danger'">{{ $lead->review_note }}</x-alert>
                    @endif

                    @if ($editingMessageId === $lead->id)
                        <div class="space-y-2">
                            <textarea wire:model="messages.{{ $lead->id }}" rows="10" class="input text-sm leading-relaxed"></textarea>
                            <p class="text-xs text-slate-500">Maksimum 900 aksara, mesti ada STOP.</p>
                            <div class="flex gap-2">
                                <button type="button" wire:click="saveMessage({{ $lead->id }})" class="btn-primary flex-1"><x-icon name="check" class="h-4 w-4" />Simpan mesej</button>
                                <button type="button" wire:click="$set('editingMessageId', null)" class="btn-soft">Batal</button>
                            </div>
                        </div>
                    @elseif ($lead->message)
                        <div>
                            <p class="label mb-1.5">Mesej</p>
                            <x-wa-bubble :message="$lead->message" />
                            @if (! $card->messageOk && $card->messageProblems)
                                <p class="mt-1.5 text-xs font-medium text-rose-600">Semak manual: {{ implode('; ', $card->messageProblems) }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Actions: links only. A human opens WhatsApp and presses send. --}}
                <div class="space-y-2 border-t border-slate-100 bg-slate-50/60 p-3">
                    @if (! $card->isMobile)
                        <div class="flex items-center gap-2">
                            <span class="flex flex-1 items-center gap-2 rounded-xl bg-white px-3 py-2.5 text-sm font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                <x-icon name="store" class="h-4 w-4 text-slate-400" />Telefon atau singgah
                            </span>
                            @if ($card->telLink())
                                <a href="{{ $card->telLink() }}" class="btn-dark"><x-icon name="phone" class="h-4 w-4" />Telefon</a>
                            @endif
                        </div>
                    @elseif ($card->whatsappLink)
                        <a href="{{ $card->whatsappLink }}" target="_blank" rel="noopener" class="btn-wa w-full py-3 text-[15px]">
                            <x-icon name="chat" class="h-5 w-5" />Buka WhatsApp
                        </a>
                    @endif

                    <div class="flex gap-2 [&>*]:flex-1">
                        @if ($card->messageOk)
                            <x-copy-button :text="$lead->message" class="flex-col gap-1 px-1 py-2 text-xs" />
                        @endif
                        @if ($lead->message)
                            <button type="button" wire:click="editMessage({{ $lead->id }})" class="btn-soft flex-col gap-1 px-1 py-2 text-xs"><x-icon name="pencil" class="h-4 w-4" />Edit</button>
                        @endif
                        @php($left = $service->regenerationsLeft($lead))
                        @php($est = $isAdmin ? $service->regenerateEstimate($lead) : null)
                        <button type="button" wire:click="regenerate({{ $lead->id }})"
                                wire:confirm="Jana semula mesej guna AI?{{ $est !== null ? ' Anggaran kos RM'.number_format($est, 3).'.' : '' }}{{ ! $isAdmin && $left < PHP_INT_MAX ? ' Percuma, baki '.$left.' kali untuk lead ini.' : '' }} Teruskan?"
                                class="btn-soft flex-col gap-1 px-1 py-2 text-xs"><x-icon name="refresh" class="h-4 w-4" />Jana semula</button>
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 p-4">
                    <div class="grid grid-cols-[auto,1fr] items-center gap-3">
                        <span class="label">Status</span>
                        <x-status-select :lead="$lead" :selectable="$selectable" />
                    </div>

                    <details class="group" @if($lead->notes) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-1.5 text-sm font-medium text-slate-600">
                            <x-icon name="note" class="h-4 w-4 text-slate-400" />Nota
                            <x-icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <textarea wire:model="notes.{{ $lead->id }}" wire:blur="saveNotes({{ $lead->id }})" rows="2" class="input mt-2 text-sm" placeholder="Cth: owner balik pukul 5"></textarea>
                    </details>

                    @if (isset($feedback[$lead->id]))
                        <p class="flex items-center gap-1.5 text-xs font-medium text-emerald-700" role="status"><x-icon name="check-circle" class="h-4 w-4" />{{ $feedback[$lead->id] }}</p>
                    @endif

                    <x-google-attribution :uri="$card->mapsUri" />
                </div>
            </article>
        @empty
            <x-empty icon="users" title="Takde lead lagi">
                Mula dengan satu carian kecil.
                <a href="{{ route('search') }}" wire:navigate class="mt-3 inline-flex items-center gap-1 font-semibold text-emerald-700">Mula cari <x-icon name="chevron-right" class="h-4 w-4" /></a>
            </x-empty>
        @endforelse
    </div>

    {{ $leads->links() }}
</div>
