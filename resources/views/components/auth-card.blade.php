@props(['title', 'subtitle' => null])
<div class="pb-10 pt-12">
    <a href="{{ url('/') }}" class="inline-flex items-center gap-3 text-white">
        <x-logo size="lg" class="ring-2 ring-white/40" />
        <span class="text-lg font-bold tracking-tight">Dyno Lead</span>
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
