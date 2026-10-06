@props(['type' => 'info'])
@php
    $styles = [
        'info' => ['bg-sky-50 text-sky-900 ring-sky-200', 'info', 'text-sky-500'],
        'success' => ['bg-emerald-50 text-emerald-900 ring-emerald-200', 'check-circle', 'text-emerald-500'],
        'warning' => ['bg-amber-50 text-amber-900 ring-amber-200', 'warning', 'text-amber-500'],
        'danger' => ['bg-rose-50 text-rose-900 ring-rose-200', 'warning', 'text-rose-500'],
    ][$type];
@endphp
<div {{ $attributes->merge(['class' => "flex gap-2.5 rounded-xl p-3 text-sm ring-1 ring-inset {$styles[0]}"]) }} role="status">
    <x-icon :name="$styles[1]" class="mt-px h-5 w-5 {{ $styles[2] }}" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
