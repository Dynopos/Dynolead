<!DOCTYPE html>
<html lang="ms" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{ $head }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-slate-100 bg-white/85 backdrop-blur-lg">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5"><x-logo /><span class="font-bold tracking-tight">Dyno Leads</span></a>
            <div class="flex items-center gap-1 text-sm">
                <a href="#cara" class="hidden px-3 py-2 font-medium text-slate-600 hover:text-slate-900 sm:block">Cara guna</a>
                <a href="#harga" class="hidden px-3 py-2 font-medium text-slate-600 hover:text-slate-900 sm:block">Harga</a>
                <a href="{{ route('login') }}" class="px-3 py-2 font-medium text-slate-600 hover:text-slate-900">Masuk</a>
                <a href="{{ route('register') }}" class="btn-primary px-3.5 py-2">Daftar percuma</a>
            </div>
        </nav>
    </header>
    {{ $slot }}
    <footer class="border-t border-slate-100 bg-slate-50">
        <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2"><x-logo /><span>© {{ date('Y') }} {{ config('dynoleads.company.name') }}</span></div>
            <nav class="flex gap-4" aria-label="Pautan kaki">
                <a href="{{ route('terms') }}" class="hover:text-slate-800">Terma</a>
                <a href="{{ route('privacy') }}" class="hover:text-slate-800">Privasi</a>
                <a href="{{ route('login') }}" class="hover:text-slate-800">Masuk</a>
            </nav>
        </div>
    </footer>
</body>
</html>
