@props(['text'])
<button type="button"
        x-data="{ copied: false }"
        x-on:click="navigator.clipboard.writeText(@js($text)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
        {{ $attributes->merge(['class' => 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold']) }}>
    <span x-show="! copied">Salin mesej</span>
    <span x-show="copied" x-cloak>Disalin ✓</span>
</button>
