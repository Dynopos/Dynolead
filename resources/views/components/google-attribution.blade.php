@props(['uri' => null])
<p {{ $attributes->merge(['class' => 'text-[11px] text-slate-500']) }}>
    Data kedai:
    @if ($uri)
        <a href="{{ $uri }}" target="_blank" rel="noopener" class="underline">Google Maps</a>
    @else
        <span>Google Maps</span>
    @endif
</p>
