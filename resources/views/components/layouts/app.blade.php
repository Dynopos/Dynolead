<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <title>{{ $title ?? 'Dyno Leads' }} · Dyno Leads</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
    <div class="relative mx-auto min-h-dvh max-w-md bg-slate-50 sm:shadow-xl sm:shadow-slate-900/5">
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 backdrop-blur-lg">
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('leads') }}" wire:navigate class="flex items-center gap-2.5">
                    <x-logo />
                    <span class="leading-tight">
                        <span class="block text-[15px] font-bold tracking-tight">Dyno Leads</span>
                        <span class="block max-w-[200px] truncate text-[11px] font-medium text-slate-500">{{ app(\App\Support\Tenancy\CurrentWorkspace::class)->get()?->name }}</span>
                    </span>
                </a>
                @php
                    $ws = app(\App\Support\Tenancy\CurrentWorkspace::class)->get();
                @endphp
                <div class="flex items-center gap-2">
                @if ($ws && $ws->plan !== 'dalaman')
                    <a href="{{ route('billing') }}" wire:navigate class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold tabular-nums text-emerald-700 ring-1 ring-inset ring-emerald-200" title="Baki">
                        <x-icon name="wallet" class="h-3.5 w-3.5" />{{ $ws->activated_at ? \App\Services\Billing\WalletService::rm($ws->balance_sen) : 'Percubaan' }}
                    </a>
                @endif
                <a href="{{ route('account') }}" wire:navigate title="Akaun"
                   @class(['grid h-9 w-9 place-items-center rounded-full text-sm font-bold', 'bg-emerald-600 text-white' => request()->routeIs('account'), 'bg-slate-100 text-slate-600 hover:bg-slate-200' => ! request()->routeIs('account')])>
                    {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    <span class="sr-only">Akaun</span>
                </a>
                </div>
            </div>
        </header>

        <main class="px-4 pb-32 pt-5">
            {{ $slot }}
        </main>

        @php
            $menu = [
                ['route' => 'products', 'label' => 'Produk', 'icon' => 'cube'],
                ['route' => 'search', 'label' => 'Cari', 'icon' => 'search'],
                ['route' => 'leads', 'label' => 'Lead', 'icon' => 'users'],
                ['route' => 'followups', 'label' => 'Follow-up', 'icon' => 'clock'],
                ['route' => 'costs', 'label' => auth()->user()?->isAdmin() ? 'Kos' : 'Baki', 'icon' => 'wallet'],
            ];
        @endphp

        <nav class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-md border-t border-slate-200/70 bg-white/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg" aria-label="Menu utama">
            <ul class="grid grid-cols-5 px-1">
                @foreach ($menu as $item)
                    @php($active = request()->routeIs($item['route']))
                    <li>
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @class(['group flex flex-col items-center gap-1 py-2 text-[11px] font-medium', 'text-emerald-700' => $active, 'text-slate-500' => ! $active])
                           @if($active) aria-current="page" @endif>
                            <span @class(['grid h-8 w-12 place-items-center rounded-full transition', 'bg-emerald-100' => $active, 'group-hover:bg-slate-100' => ! $active])>
                                <x-icon :name="$item['icon']" class="h-[22px] w-[22px]" :solid="$active" />
                            </span>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</body>
</html>
