<x-layouts.marketing>
    <x-slot:head>
        <x-seo.meta
            title="Susah Cari Customer? Kami Cari Lead untuk SME | Dyno Leads"
            description="Susah cari customer, sales slow? Dyno Leads cari lead di kawasan anda dan sediakan teks WhatsApp custom untuk setiap satu. Anda cuma tekan hantar.">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => array_values(array_filter([
                    [
                        '@type' => 'Organization',
                        'name' => config('dynoleads.company.name'),
                        'url' => url('/'),
                        'logo' => asset('favicon.svg'),
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => 'Dyno Leads',
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
            <div class="relative mx-auto grid max-w-5xl items-center gap-10 px-4 pb-16 pt-12 md:grid-cols-2 md:pt-20">
                <div>
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm ring-1 ring-emerald-200">
                        <x-icon name="sparkles" class="h-4 w-4" /> Cari prospek dengan AI untuk SME Malaysia
                    </p>
                    <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 md:text-5xl">
                        Susah cari customer? Pening sales slow? <span class="text-emerald-600">Biar Dyno Leads bantu anda cari lead.</span>
                    </h1>
                    <ul class="mt-6 space-y-3 text-lg text-slate-700">
                        <li class="flex gap-3"><x-icon name="search" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Kami cari lead</b> di kawasan anda, yang sesuai dengan produk anda.</span></li>
                        <li class="flex gap-3"><x-icon name="sparkles" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Kami sediakan teks</b> WhatsApp custom untuk setiap lead.</span></li>
                        <li class="flex gap-3"><x-icon name="chat" class="mt-1 h-5 w-5 shrink-0 text-emerald-600" /><span><b>Anda cuma tekan hantar.</b> Itu sahaja.</span></li>
                    </ul>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="btn-primary px-6 py-3.5 text-base">Cuba percuma</a>
                        <a href="#cara" class="btn-soft px-6 py-3.5 text-base">Tengok cara guna</a>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">{{ $trial['leads'] }} lead percuma · tanpa kad kredit · tiada yuran bulanan</p>
                </div>

                {{-- Product preview, built in HTML (no image to load) --}}
                <div class="relative mx-auto w-full max-w-sm" aria-hidden="true">
                    <div class="card overflow-hidden shadow-xl shadow-emerald-900/10">
                        <div class="flex items-start gap-3 p-4">
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-lg font-bold text-white">K</span>
                            <div class="flex-1">
                                <p class="font-semibold">Kedai Runcit Pak Mat</p>
                                <p class="flex items-center gap-1 text-xs text-slate-500"><x-icon name="star" class="h-3.5 w-3.5 text-amber-400" /><b class="text-slate-700">4.6</b> (312 review) · kedai runcit</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">Skor 88</span>
                        </div>
                        <div class="mx-4 rounded-xl bg-slate-50 p-3">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><x-icon name="sparkles" class="h-4 w-4" />Kenapa sesuai</p>
                            <p class="mt-1 text-sm text-slate-700">Ramai komplen kaunter lambat waktu petang.</p>
                        </div>
                        <div class="m-4 rounded-xl bg-[#efeae2] p-3">
                            <div class="ml-6 rounded-lg rounded-tr-none bg-[#d9fdd3] px-3 py-2 text-[13px] leading-relaxed text-slate-800 shadow-sm">
                                Salam Kedai Runcit Pak Mat 👋<br><br>Saya Ali dari KedaiPOS. Ramai pelanggan puji barang lengkap dan layanan mesra...<br><br>Kalau tak berminat, balas STOP, saya tak ganggu lagi 🙏
                            </div>
                        </div>
                        <div class="border-t border-slate-100 bg-slate-50/60 p-3">
                            <span class="btn-wa w-full py-3"><x-icon name="chat" class="h-5 w-5" />Buka WhatsApp</span>
                        </div>
                    </div>
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
                        <li class="card p-5">
                            <span class="flex items-center gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon :name="$icon" /></span>
                                <span class="text-sm font-bold text-slate-400">0{{ $i + 1 }}</span>
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
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Mesej WhatsApp jualan yang orang baca</h2>
                    <p class="mt-3 text-slate-600">Mesej umum mudah diabaikan. Dyno Leads tulis mesej yang sebut perkara sebenar tentang kedai itu, dan masalah yang produk anda boleh bantu.</p>
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
                <div>
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
                    <li class="card p-6">
                        <p class="text-sm font-bold text-emerald-600">1 · Cuba</p>
                        <p class="mt-2 text-3xl font-extrabold">Percuma</p>
                        <p class="mt-2 text-sm text-slate-600">{{ $trial['leads'] }} lead atau {{ $trial['days'] }} hari, mana dulu. Tanpa kad kredit.</p>
                        <a href="{{ route('register') }}" class="btn-soft mt-6 w-full">Mula percuma</a>
                    </li>
                    <li class="card p-6 ring-2 ring-emerald-500">
                        <p class="text-sm font-bold text-emerald-600">2 · Aktifkan</p>
                        <p class="mt-2"><span class="text-3xl font-extrabold">RM{{ number_format($fee, 2) }}</span> <span class="text-slate-500">sekali</span></p>
                        <p class="mt-2 text-sm text-slate-600">Bayar sekali sahaja untuk teruskan selepas percubaan.</p>
                        <a href="{{ route('register') }}" class="btn-primary mt-6 w-full">Daftar sekarang</a>
                    </li>
                    <li class="card p-6">
                        <p class="text-sm font-bold text-emerald-600">3 · Bayar ikut lead</p>
                        @if ($guide)
                            <p class="mt-2"><span class="text-sm text-slate-500">lebih kurang</span> <span class="text-3xl font-extrabold">RM{{ number_format($guide['per_lead_sen'] / 100, 2) }}</span> <span class="text-slate-500">/ lead</span></p>
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
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-12 text-center text-white">
                    <h2 class="text-3xl font-bold tracking-tight">Biar kami cari lead. Anda fokus closing.</h2>
                    <p class="mx-auto mt-3 max-w-xl text-emerald-50/90">Daftar dalam satu minit, isi profil produk, dan dapat lead pertama hari ni.</p>
                    <a href="{{ route('register') }}" class="btn mt-7 bg-white px-7 py-3.5 text-base text-emerald-700 hover:bg-emerald-50">Cuba percuma</a>
                </div>
            </div>
        </section>
    </main>
</x-layouts.marketing>
