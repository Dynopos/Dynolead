<?php

/*
| Templates for the onboarding wizard. The customer edits every field before saving.
| Text in [kurungan] must be replaced by the customer.
*/

return [
    'pos' => [
        'label' => 'Sistem POS / kaunter',
        'pitch_core' => '[Nama produk] ni sistem POS untuk kedai kecil & sederhana. Rekod jualan dan stok, laporan harian boleh tengok dalam telefon.',
        'cta' => 'Kalau nak info lanjut, balas je mesej ni.',
        'fit_signals' => 'kaunter lambat, barang banyak, stok habis, bil/resit tak tepat, banyak cawangan, waktu buka panjang',
        'default_place_types' => ['kedai runcit', 'restoran', 'kafe', 'butik pakaian'],
        'require_no_website' => false,
    ],
    'website' => [
        'label' => 'Website / kedai online',
        'pitch_core' => 'Kami boleh siapkan website untuk bisnes anda, mesra telefon, senang pelanggan cari lokasi dan menu/katalog.',
        'cta' => 'Nak saya hantar contoh website yang kami dah buat?',
        'fit_signals' => 'review banyak tetapi tiada website, pelancong cari menu/lokasi, perlu katalog atau tempahan',
        'default_place_types' => ['restoran', 'kafe', 'butik', 'bengkel', 'salon', 'bakeri'],
        'require_no_website' => true,
    ],
    'marketing' => [
        'label' => 'Pemasaran / media sosial',
        'pitch_core' => 'Kami bantu urus media sosial dan iklan supaya lebih ramai pelanggan baru jumpa bisnes anda.',
        'cta' => 'Kalau berminat, balas je, saya kongsi contoh hasil kerja kami.',
        'fit_signals' => 'review bagus tapi bilangan review sedikit, bisnes baru buka, kawasan banyak pesaing',
        'default_place_types' => ['kafe', 'salon', 'klinik gigi', 'gym', 'kedai bunga'],
        'require_no_website' => false,
    ],
    'lain' => [
        'label' => 'Lain-lain (isi sendiri)',
        'pitch_core' => '',
        'cta' => 'Kalau nak info lanjut, balas je mesej ni.',
        'fit_signals' => '',
        'default_place_types' => [],
        'require_no_website' => false,
    ],
];
