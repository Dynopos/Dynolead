<div class="space-y-4" @if($regenerating) wire:poll.4s @endif>
    <h1 class="text-xl font-bold">Lead</h1>

    @if ($notice)
        <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ $notice }}</p>
    @endif

    {{-- Summary per status --}}
    <div class="grid grid-cols-3 gap-2 text-center text-xs" aria-label="Ringkasan">
        @foreach ($statuses as $s)
            <button type="button" wire:click="$set('status', '{{ $s === \App\Enums\LeadStatus::Tolak ? '' : $s->value }}')"
                    @class(['rounded-lg border bg-white px-1 py-2', 'border-emerald-500 ring-1 ring-emerald-500' => $status === $s->value, 'border-slate-200' => $status !== $s->value])>
                <span class="block text-lg font-bold">{{ $summary[$s->value] }}</span>
                {{ $s->label() }}
            </button>
        @endforeach
    </div>

    <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-900">
        Hari ni dah tanda <strong>{{ $sentToday }}</strong> mesej dihantar. Cadangan: 10–15 mesej sehari setiap nombor supaya nombor tak disekat.
    </p>

    {{-- Filters --}}
    <details class="rounded-xl border border-slate-200 bg-white p-3" @if($productId || $businessType || $status || $area) open @endif>
        <summary class="cursor-pointer text-sm font-semibold">Penapis</summary>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <select wire:model.live="productId" class="input text-sm" aria-label="Produk">
                <option value="">Semua produk</option>
                @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="input text-sm" aria-label="Status">
                <option value="">Semua (aktif)</option>
                @foreach ($statuses as $s)
                    @continue($s === \App\Enums\LeadStatus::Tolak)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="businessType" class="input text-sm" aria-label="Jenis">
                <option value="">Semua jenis</option>
                @foreach ($options['business_types'] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>
            <select wire:model.live="area" class="input text-sm" aria-label="Kawasan">
                <option value="">Semua kawasan</option>
                @foreach ($options['areas'] as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
            </select>
        </div>
        <button type="button" wire:click="clearFilters" class="mt-2 text-xs text-slate-600 underline">Kosongkan penapis</button>
    </details>

    {{-- Cards --}}
    @forelse ($cards as $card)
        @php($lead = $card->lead)
        <article wire:key="lead-{{ $lead->id }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
            <header class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h2 class="truncate font-semibold">{{ $card->name ?? 'Nama tak dapat dimuat' }}</h2>
                    <p class="text-xs text-slate-600">
                        @if ($card->rating) ⭐ {{ number_format($card->rating, 1) }} ({{ $card->reviewCount }} review) · @endif
                        {{ $lead->business_type }} · {{ $lead->area }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $lead->product->name }} @if($card->phone) · {{ $card->phone }} @endif</p>
                </div>
                @if ($lead->fit !== null)
                    <span @class(['shrink-0 rounded-full px-2 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $lead->fit >= 70, 'bg-amber-100 text-amber-800' => $lead->fit >= 50 && $lead->fit < 70, 'bg-slate-100 text-slate-600' => $lead->fit < 50])>
                        Skor {{ $lead->fit }}
                    </span>
                @else
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Belum dinilai</span>
                @endif
            </header>

            @if ($lead->reason)
                <div>
                    <p class="text-xs font-semibold text-slate-500">Kenapa sesuai</p>
                    <p class="text-sm">{{ $lead->reason }}</p>
                </div>
            @endif

            @if ($lead->flag)
                <p class="rounded-lg bg-amber-50 p-2 text-xs text-amber-900">⚠️ {{ $lead->flag }}</p>
            @endif

            @if ($lead->review_note)
                <p class="rounded-lg bg-red-50 p-2 text-xs text-red-800">{{ $lead->review_note }}</p>
            @endif

            @if ($editingMessageId === $lead->id)
                <div class="space-y-2">
                    <textarea wire:model="messages.{{ $lead->id }}" rows="10" class="input text-sm"></textarea>
                    <p class="text-xs text-slate-500">Maksimum 900 aksara, mesti ada STOP.</p>
                    <div class="flex gap-2">
                        <button type="button" wire:click="saveMessage({{ $lead->id }})" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Simpan mesej</button>
                        <button type="button" wire:click="$set('editingMessageId', null)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Batal</button>
                    </div>
                </div>
            @elseif ($lead->message)
                <div>
                    <p class="text-xs font-semibold text-slate-500">Mesej</p>
                    <p class="whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm">{{ $lead->message }}</p>
                    @if (! $card->messageOk && $card->messageProblems)
                        <p class="mt-1 text-xs text-red-700">Semak manual: {{ implode('; ', $card->messageProblems) }}</p>
                    @endif
                </div>
            @endif

            {{-- Actions: links only. A human opens WhatsApp and presses send. --}}
            <div class="flex flex-wrap gap-2">
                @if (! $card->isMobile)
                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">Telefon atau singgah</span>
                    @if ($card->telLink())<a href="{{ $card->telLink() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Telefon</a>@endif
                @elseif ($card->whatsappLink)
                    <a href="{{ $card->whatsappLink }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Buka WhatsApp</a>
                @endif
                @if ($card->messageOk)
                    <x-copy-button :text="$lead->message" />
                @endif
                @if ($lead->message)
                    <button type="button" wire:click="editMessage({{ $lead->id }})" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Edit</button>
                @endif
                @php($est = $service->regenerateEstimate($lead))
                <button type="button" wire:click="regenerate({{ $lead->id }})"
                        wire:confirm="Jana semula mesej guna AI?{{ $est !== null ? ' Anggaran kos RM'.number_format($est, 3).'.' : '' }} Teruskan?"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Jana semula</button>
            </div>

            <div class="grid grid-cols-1 gap-2">
                <label class="text-xs font-semibold text-slate-500">Status
                    <select class="input mt-1 text-sm"
                            x-on:change="if ($event.target.value !== 'tolak' || confirm('Tanda STOP? Kedai ni takkan muncul lagi untuk semua produk.')) { $wire.setStatus({{ $lead->id }}, $event.target.value) } else { $event.target.value = '{{ $lead->status->value }}' }">
                        @if ($lead->status === \App\Enums\LeadStatus::TakSesuai)
                            <option value="tak_sesuai" selected>Tak sesuai</option>
                        @endif
                        @foreach ($selectable as $s)
                            <option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Nota
                    <textarea wire:model="notes.{{ $lead->id }}" wire:blur="saveNotes({{ $lead->id }})" rows="2" class="input mt-1 text-sm" placeholder="Cth: owner balik pukul 5"></textarea>
                </label>
            </div>

            @if (isset($feedback[$lead->id]))
                <p class="text-xs font-medium text-emerald-800" role="status">{{ $feedback[$lead->id] }}</p>
            @endif

            <x-google-attribution :uri="$card->mapsUri" />
        </article>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
            Takde lead lagi. <a href="{{ route('search') }}" wire:navigate class="font-semibold text-emerald-700">Mula cari →</a>
        </p>
    @endforelse

    {{ $leads->links() }}
</div>
