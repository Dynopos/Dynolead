@props(['title', 'subtitle' => null])
<div class="pb-10 pt-12">
    <a href="{{ url('/') }}" class="inline-flex items-center gap-3 text-white">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l5-5 4 4 8-9"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7h5v5"/>
            </svg>
        </span>
        <span class="text-lg font-bold tracking-tight">Dyno Leads</span>
    </a>
    <h1 class="mt-6 text-3xl font-bold tracking-tight text-white">{{ $title }}</h1>
    @if ($subtitle)<p class="mt-1 text-emerald-50/90">{{ $subtitle }}</p>@endif

    <div class="card mt-7 p-5">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="mt-5 text-center text-sm text-slate-600">{{ $footer }}</div>
    @endisset

    <p class="mt-8 text-center text-xs text-slate-400">
        <a href="{{ route('terms') }}" class="hover:underline">Terma</a> ·
        <a href="{{ route('privacy') }}" class="hover:underline">Privasi</a>
    </p>
</div>
