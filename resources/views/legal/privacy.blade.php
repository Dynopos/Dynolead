<x-layouts.page title="Polisi Privasi">
    @php($c = config('dynoleads.company'))
    <article class="max-w-none space-y-4 text-[15px] leading-relaxed text-slate-700 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-slate-900 [&_li]:ml-5 [&_li]:list-disc">
        <x-alert type="warning">DRAF. Perlu disemak oleh peguam (PDPA 2010) sebelum perkhidmatan dijual.</x-alert>
        <h1 class="text-2xl font-bold text-slate-900">Polisi Privasi Dyno Lead</h1>
        <p>Polisi ini menerangkan bagaimana {{ $c['name'] }} memproses data peribadi di bawah Akta Perlindungan Data Peribadi 2010 (PDPA).</p>

        <h2>1. Data yang kami kumpul</h2>
        <ul>
            <li><strong>Akaun:</strong> nama, e-mel, nama bisnes, telefon bisnes, kata laluan (disimpan dalam bentuk hash).</li>
            <li><strong>Bayaran:</strong> rekod transaksi (jumlah, tarikh, status). Butiran kad/akaun bank diproses oleh CHIP, bukan kami.</li>
            <li><strong>Data kerja anda:</strong> profil produk, nota, status lead, teks yang dijana AI.</li>
            <li><strong>Data bisnes dari Google Maps:</strong> ID tempat disimpan; nama, telefon, rating dan review disimpan sementara (cache) dan dipadam secara automatik.</li>
            <li><strong>Log penggunaan:</strong> bilangan token AI, panggilan Google, alamat IP semasa log masuk.</li>
        </ul>

        <h2>2. Tujuan</h2>
        <p>Menyediakan Perkhidmatan, mengira kuota dan bil, keselamatan akaun, sokongan pelanggan, dan mematuhi undang-undang.</p>

        <h2>3. Pihak ketiga</h2>
        <ul>
            <li><strong>Anthropic</strong> (AI): data kedai dan profil produk dihantar untuk dinilai dan ditulis mesej.</li>
            <li><strong>Google Maps Platform</strong>: carian dan butiran bisnes.</li>
            <li><strong>CHIP</strong>: pemprosesan bayaran.</li>
            <li>Penyedia pelayan dan e-mel kami.</li>
        </ul>
        <p>Sebahagian pihak ini memproses data di luar Malaysia. Kami tidak menjual data anda.</p>

        <h2>4. Senarai STOP</h2>
        <p>Bila anda tanda sesebuah bisnes sebagai STOP, ID tempat dan nombor telefonnya disimpan dalam workspace anda supaya bisnes itu tidak dihubungi lagi. Rekod ini disimpan selagi akaun anda wujud.</p>

        <h2>5. Tempoh simpanan</h2>
        <p>Data akaun disimpan selagi akaun aktif dan sehingga 12 bulan selepas akaun tidak digunakan, kemudian dipadam, kecuali rekod bayaran yang perlu disimpan mengikut undang-undang.</p>

        <h2>6. Hak anda</h2>
        <p>Anda boleh meminta akses, pembetulan atau pemadaman data anda dengan menghubungi kami. Kami akan membalas dalam 21 hari.</p>

        <h2>7. Hubungi kami</h2>
        <p>{{ $c['name'] }} · {{ $c['address'] }} @if($c['email']) · <a href="mailto:{{ $c['email'] }}" class="text-emerald-700 underline">{{ $c['email'] }}</a>@endif · <x-whatsapp-contact class="text-emerald-700 underline" /></p>
    </article>
</x-layouts.page>
