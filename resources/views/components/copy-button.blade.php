@props(['text'])
<button type="button"
        x-data="{ copied: false }"
        x-on:click="navigator.clipboard.writeText(@js($text)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
        {{ $attributes->merge(['class' => 'btn-soft']) }}>
    <x-icon name="copy" class="h-4 w-4" x-show="! copied" />
    <x-icon name="check" class="h-4 w-4 text-emerald-600" x-show="copied" x-cloak />
    <span x-show="! copied">Salin mesej</span>
    <span x-show="copied" x-cloak>Disalin</span>
</button>
