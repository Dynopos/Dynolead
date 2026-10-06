<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <title>{{ $title ?? 'Dyno Lead' }} · Dyno Lead</title>
    <meta name="description" content="{{ $title ?? 'Dyno Lead' }} untuk Dyno Lead, app cari prospek dengan AI untuk SME Malaysia.">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white text-slate-900 antialiased">
    <header class="border-b border-slate-100">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5"><x-logo /><span class="font-bold tracking-tight">Dyno Lead</span></a>
            @auth
                <a href="{{ route('leads') }}" class="btn-soft px-3 py-1.5 text-xs">Buka app</a>
            @else
                <a href="{{ route('login') }}" class="btn-soft px-3 py-1.5 text-xs">Masuk</a>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-8">
        {{ $slot }}
    </main>
    <footer class="border-t border-slate-100 py-6 text-center text-xs text-slate-400">
        <a href="{{ route('terms') }}" class="hover:underline">Terma</a> ·
        <a href="{{ route('privacy') }}" class="hover:underline">Privasi</a> ·
        © {{ date('Y') }} {{ config('dynoleads.company.name') }}
    </footer>
</body>
</html>
