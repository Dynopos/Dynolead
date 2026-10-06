@props(['title', 'subtitle' => null])
<div class="flex items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
