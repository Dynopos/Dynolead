<x-layouts.marketing>
    <x-slot:head>
        <x-seo.meta
            title="Susah Cari Customer? Kami Cari Lead untuk SME | Dyno Lead"
            description="Susah cari customer, sales slow? Dyno Lead cari lead di kawasan anda dan sediakan teks WhatsApp custom untuk setiap satu. Anda cuma tekan hantar.">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => array_values(array_filter([
                    [
                        '@type' => 'Organization',
                        'name' => config('dynoleads.company.name'),
                        'url' => url('/'),
                        'logo' => asset('images/logo.png'),
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => 'Dyno Lead',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'inLanguage' => 'ms',
                        'url' => url('/'),
                        'description' => 'Cari prospek kedai tempatan dengan AI dan tulis mesej WhatsApp custom untuk setiap kedai.',
                        'offers' => [
                            '@type' => 'Offer',
                            'name' => 'Aktifkan akaun (sekali bayar)',
                            'price' => number_format($fee, 2, '.', ''),
                            'priceCurrency' => 'MYR',
                            'description' => "Percubaan percuma {$trial['leads']} lead atau {$trial['days']} hari. Selepas itu bayar ikut carian.",
                        ],
                    ],
                    [
                        '@type' => 'FAQPage',
                        'mainEntity' => collect($faqs)->map(fn ($f) => [
                            '@type' => 'Question',
                            'name' => $f[0],
                            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
                        ])->all(),
                    ],
                ])),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        </x-seo.meta>
    </x-slot:head>

    <main>
        {{-- Hero --}}
        <section class="relative overflow-hidden bg-gradient-to-b from-emerald-50 via-white to-white">
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-200/40 blur-3xl" aria-hidden="true"></div>
            <div class="absolute -left-24 top-40 h-64 w-64 rounded-full bg-orange-200/40 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto grid max-w-5xl items-center gap-10 px-4 pb-16 pt-12 md:grid-cols-2 md:pt-20">
                <div class="min-w-0">
                    @if (file_exists(public_path('audio/pesanan-bob.mp3')))
                        {{-- Bob's voice note: tries to play on open, otherwise on the first tap (resources/js/marketing.js). --}}
                        <div data-voice class="relative inline-flex items-center gap-3 overflow-hidden rounded-full bg-slate-900 py-1.5 pl-1.5 pr-4 text-white shadow-lg shadow-slate-900/20">
                            <button type="button" data-voice-toggle aria-pressed="false" aria-label="Dengar pesanan Bob" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-orange-500 text-white transition hover:bg-orange-600">
                                <svg class="voice-play h-5 w-5 translate-x-px" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86A1 1 0 0 0 8 5.14Z"/></svg>
                                <svg class="voice-pause h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                            </button>
                            <span class="text-sm font-semibold">Dengar pesanan Bob</span>
                            <span class="voice-bars flex h-4 items-end gap-0.5" aria-hidden="true">
                                <span class="h-4 w-1 rounded-full bg-orange-400"></span><span class="h-4 w-1 rounded-full bg-orange-400"></span><span class="h-4 w-1 rounded-full bg-orange-400"></span><span class="h-4 w-1 rounded-full bg-orange-400"></span>
                            </span>
                            <span data-voice-time class="text-sm tabular-nums text-slate-300">0:50</span>
                            <span class="absolute inset-x-0 bottom-0 h-0.5 bg-white/10" aria-hidden="true"><span data-voice-progress class="block h-full w-0 bg-orange-400"></span></span>
                            <audio preload="auto" src="{{ asset('audio/pesanan-bob.mp3') }}"></audio>
                        </div>
                        {{-- Shown only while the browser waits for a tap before it allows sound. --}}
                        <button type="button" data-voice-hint hidden
                                class="fixed left-1/2 top-[4.5rem] z-40 inline-flex [&[hidden]]:hidden -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-600/30">
                            <span aria-hidden="true">🔊</span> Ketik skrin untuk dengar pesanan Bob
                        </button>
                    @endif

                    <p class="mt-6 font-hand text-2xl font-semibold text-orange-600">Khas untuk anda yang jual...</p>
                    <div class="marquee relative mt-2 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">
                        <div class="marquee-track flex w-max gap-2">
                            @foreach ([false, true] as $copy)
                                @foreach (['Sistem POS', 'Website', 'Pemasaran digital', 'Katering', 'Percetakan', 'Insurans & takaful', 'Servis IT', 'Barang borong'] as $niche)
                                    <span @if($copy) aria-hidden="true" @endif class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-orange-500"></span>{{ $niche }}</span>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    <h1 class="mt-6 text-4xl font-extrabold leading-[1.15] tracking-tight text-slate-900 md:text-5xl">
                        <span class="box-decoration-clone rounded-lg bg-amber-300 px-1.5">Susah cari customer?</span> Pening sales slow? <span class="text-emerald-600">Biar <span class="text-orange-500">Dyno Lead</span> bantu anda cari lead.</span>
                    </h1>
                    <p class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                        <x-icon name="sparkles" class="h-4 w-4" /> Cari prospek dengan AI untuk SME Malaysia
                    </p>
                    <ul class="mt-6 space-y-3 text-lg text-slate-700">
                        <li class="flex gap-3"><x-icon name="search" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Kami cari lead</b> di kawasan anda, yang sesuai dengan produk anda.</span></li>
                        <li class="flex gap-3"><x-icon name="sparkles" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Kami sediakan teks</b> WhatsApp custom untuk setiap lead.</span></li>
                        <li class="flex gap-3"><x-icon name="chat" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Anda cuma tekan hantar.</b> Itu sahaja.</span></li>
                    </ul>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="btn-accent px-6 py-3.5 text-base">Cuba percuma</a>
                        <a href="#cara" class="btn-soft px-6 py-3.5 text-base">Tengok cara guna</a>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">{{ $trial['leads'] }} lead percuma · tanpa kad kredit · tiada yuran bulanan</p>
                </div>

                {{-- Screen recording of the real app (demo data, no real shops): plays muted on loop. --}}
                <div class="relative mx-auto w-full max-w-[280px]">
                    <p class="absolute -left-4 top-16 z-10 -rotate-6 rounded-xl bg-amber-300 px-3 py-1 font-hand text-xl font-semibold text-slate-900 shadow-md">Lead siap dengan mesej!</p>
                    <div class="rounded-[2.5rem] bg-slate-900 p-2.5 shadow-2xl shadow-emerald-900/25">
                        <div class="relative overflow-hidden rounded-[2rem] bg-white">
                            <video class="block aspect-[600/1298] w-full" data-autoplay autoplay muted loop playsinline preload="metadata"
                                   poster="{{ asset('video/demo-lead.jpg') }}" aria-label="Rakaman skrin app Dyno Lead: senarai lead dengan mesej WhatsApp siap ditulis">
                                <source src="{{ asset('video/demo-lead.mp4') }}" type="video/mp4">
                                <source src="{{ asset('video/demo-lead.webm') }}" type="video/webm">
                            </video>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-xs text-slate-500">Rakaman app sebenar · data kedai contoh</p>
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section id="cara" class="scroll-mt-16 py-16">
            <div class="mx-auto max-w-5xl px-4">
                <h2 class="text-3xl font-bold tracking-tight">Kami cari. Kami tulis. Anda hantar.</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Empat langkah, semua dari telefon.</p>
                <ol class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['search', 'Anda pilih jenis & kawasan', 'Contoh: kedai runcit di Kota Bharu. Anda nampak anggaran caj sebelum mula.'],
                        ['funnel', 'Kami cari & tapis lead', 'Kami cari di Google Maps dan buang kedai rating rendah, yang dah ada website (jika perlu), dan yang pernah minta STOP.'],
                        ['sparkles', 'Kami sediakan teks', 'AI baca review, beri skor kesesuaian dan tulis mesej custom ikut profil produk anda.'],
                        ['chat', 'Anda tekan hantar', 'Buka WhatsApp dengan mesej siap. Tanda status, dapat peringatan follow-up.'],
                    ] as $i => [$icon, $title, $text])
                        <li class="card p-5" data-reveal style="transition-delay: {{ $i * 80 }}ms">
                            <span class="flex items-center gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon :name="$icon" /></span>
                                <span class="text-sm font-bold text-orange-500">0{{ $i + 1 }}</span>
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Why --}}
        <section class="bg-slate-50 py-16">
            <div class="mx-auto grid max-w-5xl gap-10 px-4 md:grid-cols-2">
                <div data-reveal>
                    <h2 class="text-3xl font-bold tracking-tight">Mesej WhatsApp jualan yang orang baca</h2>
                    <p class="mt-3 text-slate-600">Mesej umum mudah diabaikan. Dyno Lead tulis mesej yang sebut perkara sebenar tentang kedai itu, dan masalah yang produk anda boleh bantu.</p>
                    <ul class="mt-6 space-y-3">
                        @foreach ([
                            'Pujian spesifik dari review Google kedai',
                            'Ayat produk dan penutup ikut tulisan anda sendiri',
                            'Perkataan dilarang ditolak automatik',
                            'Tiada harga atau janji yang anda tak tulis',
                        ] as $point)
                            <li class="flex gap-2.5 text-slate-700"><x-icon name="check-circle" class="mt-0.5 h-5 w-5 text-emerald-600" />{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
                <div data-reveal style="transition-delay: 80ms">
                    <h2 class="text-3xl font-bold tracking-tight">Selamat untuk nombor anda</h2>
                    <p class="mt-3 text-slate-600">Tiada blast, tiada bot tak rasmi. Itu cara paling cepat nombor WhatsApp kena sekat.</p>
                    <ul class="mt-6 space-y-3">
                        @foreach ([
                            'Anda sendiri tekan hantar untuk setiap mesej',
                            'Cadangan 10–15 mesej sehari setiap nombor',
                            'STOP kekal: kedai tu takkan muncul lagi',
                            'Satu kedai, satu produk dalam 30 hari',
                        ] as $point)
                            <li class="flex gap-2.5 text-slate-700"><x-icon name="lock" class="mt-0.5 h-5 w-5 text-emerald-600" />{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        {{-- Pricing --}}
        <section id="harga" class="scroll-mt-16 py-16">
            <div class="mx-auto max-w-5xl px-4">
                <h2 class="text-3xl font-bold tracking-tight">Harga mudah, tiada yuran bulanan</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Anda hanya bayar bila cari lead.</p>
                <ol class="mt-8 grid gap-4 md:grid-cols-3">
                    <li class="card p-6" data-reveal>
                        <p class="text-sm font-bold text-emerald-600">1 · Cuba</p>
                        <p class="mt-2 text-3xl font-extrabold">Percuma</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $trial['leads'] }} lead atau {{ $trial['days'] }} hari, mana dulu. Tanpa kad kredit.</p>
                        <a href="{{ route('register') }}" class="btn-soft mt-6 w-full">Mula percuma</a>
                    </li>
                    <li class="card p-6 ring-2 ring-orange-400" data-reveal style="transition-delay: 80ms">
                        <p class="text-sm font-bold text-orange-600">2 · Aktifkan</p>
                        <p class="mt-2"><span class="text-3xl font-extrabold">RM{{ number_format($fee, 2) }}</span> <span class="text-slate-500">sekali</span></p>
                        <p class="mt-2 text-sm text-slate-600">Bayar sekali sahaja untuk teruskan selepas percubaan.</p>
                        <a href="{{ route('register') }}" class="btn-accent mt-6 w-full">Daftar sekarang</a>
                    </li>
                    <li class="card p-6" data-reveal style="transition-delay: 160ms">
                        <p class="text-sm font-bold text-emerald-600">3 · Bayar ikut lead</p>
                        @if ($guide)
                            <p class="mt-2"><span class="text-sm text-slate-500">lebih kurang</span> <span class="text-3xl font-extrabold text-orange-600">RM{{ number_format($guide['per_lead_sen'] / 100, 2) }}</span> <span class="text-slate-500">/ lead</span></p>
                            <p class="mt-2 text-sm text-slate-600">Tambah baki, guna bila perlu. Satu lead = satu kedai yang sesuai, lengkap dengan mesej WhatsApp siap ditulis.</p>
                            <table class="mt-4 w-full text-sm">
                                <thead><tr class="text-left text-xs text-slate-500"><th class="pb-1 font-medium">Tambah baki</th><th class="pb-1 text-right font-medium">Anggaran lead</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($guide['topups'] as $myr => $leads)
                                        <tr><td class="py-2 font-semibold">RM{{ $myr }}</td><td class="py-2 text-right">± {{ $leads }} lead</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="mt-2 text-3xl font-extrabold">Kos + {{ rtrim(rtrim(number_format($markup, 2), '0'), '.') }}%</p>
                            <p class="mt-2 text-sm text-slate-600">Caj ikut kos sebenar AI dan Google Maps untuk carian anda, campur caj perkhidmatan kecil.</p>
                        @endif
                    </li>
                </ol>
                <p class="mt-4 text-sm text-slate-500">Harga lead ialah anggaran: caj sebenar ikut kos AI dan Google Maps untuk carian anda (+{{ rtrim(rtrim(number_format($markup, 2), '0'), '.') }}% caj perkhidmatan), dan bergantung pada kawasan dan jenis bisnes. Anggaran caj ditunjuk sebelum setiap carian. Tambah baki melalui FPX, kad atau e-wallet (CHIP). Jana semula mesej dan follow-up AI percuma.</p>
            </div>
        </section>

        {{-- FAQ --}}
        <section id="soalan" class="scroll-mt-16 bg-slate-50 py-16">
            <div class="mx-auto max-w-3xl px-4">
                <h2 class="text-3xl font-bold tracking-tight">Soalan lazim</h2>
                <div class="mt-8 space-y-3">
                    @foreach ($faqs as [$q, $a])
                        <details class="card group p-5" @if($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold">
                                <h3>{{ $q }}</h3>
                                <x-icon name="chevron-down" class="h-5 w-5 text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 leading-relaxed text-slate-600">{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section class="py-16">
            <div class="mx-auto max-w-5xl px-4">
                <div data-reveal class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-12 text-center text-white">
                    <div class="absolute -bottom-16 -right-16 h-56 w-56 rounded-full bg-orange-400/40 blur-3xl" aria-hidden="true"></div>
                    <h2 class="relative text-3xl font-bold tracking-tight">Biar kami cari lead. Anda fokus closing.</h2>
                    <p class="relative mx-auto mt-3 max-w-xl text-emerald-50/90">Daftar dalam satu minit, isi profil produk, dan dapat lead pertama hari ni.</p>
                    <a href="{{ route('register') }}" class="btn-accent relative mt-7 px-7 py-3.5 text-base">Cuba percuma</a>
                </div>
            </div>
        </section>
    </main>
</x-layouts.marketing>
