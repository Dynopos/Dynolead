@props(['lead', 'selectable'])
@php($current = $lead->status)
<label class="block">
    <span class="sr-only">Status</span>
    <span class="relative block">
        <span class="pointer-events-none absolute left-3.5 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full {{ $current->colors()[0] }}"></span>
        <select class="input appearance-none py-2 pl-9 text-sm font-medium"
                x-on:change="if ($event.target.value !== 'tolak' || confirm('Tanda STOP? Kedai ni takkan muncul lagi untuk semua produk.')) { $wire.setStatus({{ $lead->id }}, $event.target.value) } else { $event.target.value = '{{ $current->value }}' }">
            @if ($current === \App\Enums\LeadStatus::TakSesuai)
                <option value="tak_sesuai" selected>Tak sesuai</option>
            @endif
            @foreach ($selectable as $s)
                <option value="{{ $s->value }}" @selected($current === $s)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </span>
</label>
