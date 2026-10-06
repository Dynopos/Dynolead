@props(['label', 'hint' => null])
<label class="flex cursor-pointer items-center justify-between gap-3">
    <span>
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($hint)<span class="block text-xs text-slate-500">{{ $hint }}</span>@endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" {{ $attributes }} class="peer sr-only">
        <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2"></span>
        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
