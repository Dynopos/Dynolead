<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#059669">
    <title>{{ $title ?? 'Dyno Leads' }} · Dyno Leads</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3">
            <a href="{{ route('leads') }}" class="font-bold text-emerald-700">Dyno Leads</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-slate-500">Keluar</button>
            </form>
        </div>
    </header>

    <main class="mx-auto w-full max-w-md px-4 pb-28 pt-4">
        {{ $slot }}
    </main>

    @php
        $menu = [
            ['route' => 'products', 'label' => 'Produk', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['route' => 'search', 'label' => 'Cari', 'icon' => 'M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z'],
            ['route' => 'leads', 'label' => 'Lead', 'icon' => 'M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'followups', 'label' => 'Follow-up', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'costs', 'label' => 'Kos', 'icon' => 'M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ];
    @endphp

    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white" aria-label="Menu utama">
        <ul class="mx-auto grid max-w-md grid-cols-5">
            @foreach ($menu as $item)
                @php($active = request()->routeIs($item['route']))
                <li>
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @class(['flex flex-col items-center gap-0.5 py-2 text-[11px]', 'text-emerald-700 font-semibold' => $active, 'text-slate-500' => ! $active])
                       @if($active) aria-current="page" @endif>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</body>
</html>
