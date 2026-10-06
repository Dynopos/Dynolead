<x-layouts.page title="Terma Perkhidmatan">
    @php($c = config('dynoleads.company'))
    <article class="prose-sm max-w-none space-y-4 text-[15px] leading-relaxed text-slate-700 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-slate-900 [&_li]:ml-5 [&_li]:list-disc">
        <x-alert type="warning">DRAF. Perlu disemak oleh peguam sebelum perkhidmatan dijual.</x-alert>
        <h1 class="text-2xl font-bold text-slate-900">Terma Perkhidmatan Dyno Leads</h1>
        <p>Dikemas kini: {{ \Illuminate\Support\Carbon::parse('2026-10-07')->translatedFormat('j F Y') }}</p>

        <p>Dyno Leads ("Perkhidmatan") disediakan oleh {{ $c['name'] }}@if($c['registration']) ({{ $c['registration'] }})@endif, {{ $c['address'] }} ("kami"). Dengan mendaftar, anda ("Pelanggan") bersetuju dengan terma ini.</p>

        <h2>1. Apa Perkhidmatan ini buat</h2>
        <ul>
            <li>Mencari bisnes tempatan melalui Google Maps Platform berdasarkan carian anda.</li>
            <li>Menggunakan AI untuk menilai kesesuaian dan menulis draf mesej WhatsApp berdasarkan profil produk yang anda masukkan.</li>
            <li>Menjejak status lead dan peringatan follow-up.</li>
        </ul>
        <p><strong>Perkhidmatan tidak menghantar mesej bagi pihak anda.</strong> Setiap mesej dibuka dalam WhatsApp anda sendiri dan anda yang menekan hantar.</p>

        <h2>2. Tanggungjawab anda</h2>
        <ul>
            <li>Anda bertanggungjawab sepenuhnya atas setiap mesej yang anda hantar, termasuk kandungannya.</li>
            <li>Semak setiap draf sebelum hantar. AI boleh tersilap; jangan hantar maklumat yang tidak benar.</li>
            <li>Maklumat produk (harga, tawaran, dakwaan) yang anda masukkan mestilah benar.</li>
            <li>Patuhi Syarat Perkhidmatan WhatsApp, Akta Perlindungan Data Peribadi 2010 dan undang-undang lain yang berkaitan.</li>
            <li>Hormati permintaan STOP. Jangan hubungi semula pihak yang meminta berhenti.</li>
            <li>Jangan gunakan Perkhidmatan untuk spam, penipuan, kandungan menyalahi undang-undang, atau menghantar mesej secara pukal/automatik.</li>
        </ul>

        <h2>3. Akaun</h2>
        <p>Anda bertanggungjawab menjaga kerahsiaan kata laluan. Satu akaun untuk satu bisnes. Kami boleh menggantung akaun yang melanggar terma ini.</p>

        <h2>4. Kredit dan bayaran</h2>
        <ul>
            <li>Perkhidmatan dibayar ikut carian menggunakan kredit prabayar. Tiada yuran bulanan atau caj automatik.</li>
            <li>Kos setiap carian (dalam kredit) dipaparkan sebelum anda mengesahkan carian.</li>
            <li>Akaun baru mungkin menerima kredit percuma. Kredit percuma tiada nilai tunai.</li>
            <li>Pek kredit dibayar melalui CHIP. Kami tidak menyimpan maklumat kad atau akaun bank anda.</li>
            <li>Kredit tidak luput selagi akaun aktif, dan tidak boleh ditukar kepada wang tunai atau dipindah ke akaun lain.</li>
            <li>Kredit dipulangkan secara automatik jika carian gagal atau tidak menghasilkan sebarang lead. Kredit yang telah digunakan untuk carian yang berjaya tidak dipulangkan.</li>
            <li>Jana semula mesej dan follow-up AI disediakan percuma dengan had penggunaan munasabah bagi setiap lead.</li>
            <li>Bayaran pek kredit tidak dikembalikan, kecuali dikehendaki undang-undang.</li>
        </ul>

        <h2>5. Data Google Maps</h2>
        <p>Maklumat bisnes (nama, telefon, rating, review) datang dari Google Maps Platform dan hanya disimpan sementara. Penggunaan anda tertakluk kepada <a href="https://maps.google.com/help/terms_maps/" class="text-emerald-700 underline" target="_blank" rel="noopener">Syarat Tambahan Google Maps</a>.</p>

        <h2>6. Had liabiliti</h2>
        <p>Perkhidmatan disediakan "seadanya". Kami tidak menjamin bilangan lead, balasan atau jualan. Setakat yang dibenarkan undang-undang, liabiliti kami terhad kepada jumlah yang anda bayar dalam 3 bulan terakhir.</p>

        <h2>7. Perubahan</h2>
        <p>Kami boleh mengubah terma ini. Perubahan penting akan dimaklumkan melalui e-mel atau dalam app.</p>

        <h2>8. Hubungi kami</h2>
        <p>{{ $c['name'] }} · {{ $c['address'] }} @if($c['email']) · <a href="mailto:{{ $c['email'] }}" class="text-emerald-700 underline">{{ $c['email'] }}</a>@endif</p>
    </article>
</x-layouts.page>
