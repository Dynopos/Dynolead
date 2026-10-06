<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#059669">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <title>{{ $title ?? 'Dyno Leads' }} · Dyno Leads</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
    <div class="relative mx-auto min-h-dvh max-w-md overflow-hidden bg-slate-50">
        <div class="absolute inset-x-0 top-0 h-72 bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700"></div>
        <div class="absolute -right-16 top-10 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
        <main class="relative px-5">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
