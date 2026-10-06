@props(['uri' => null])
<p {{ $attributes->merge(['class' => 'flex items-center gap-1 text-[11px] text-slate-400']) }}>
    <x-icon name="map-pin" class="h-3.5 w-3.5" />
    Data kedai:
    @if ($uri)
        <a href="{{ $uri }}" target="_blank" rel="noopener" class="font-medium text-slate-500 underline decoration-slate-300 underline-offset-2">Google Maps</a>
    @else
        <span class="font-medium text-slate-500">Google Maps</span>
    @endif
</p>
