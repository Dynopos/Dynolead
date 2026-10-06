<?php

/*
|--------------------------------------------------------------------------
| Kredit (bayar ikut carian)
|--------------------------------------------------------------------------
| Pelanggan beli pek kredit melalui CHIP dan guna kredit untuk setiap carian.
| Bob tetapkan harga pek. Kredit tidak luput.
|
| Kos satu carian = ceil(bilangan calon / candidates_per_credit).
| Contoh dengan 20: 10 atau 20 calon = 1 kredit, 40 = 2 kredit, 60 = 3 kredit.
|
| Pastikan harga satu kredit jauh di atas kos sebenar satu carian (AI + Places +
| yuran CHIP). Lihat kos sebenar setiap carian di halaman Kos (admin).
*/

return [

    'candidates_per_credit' => 20,

    // Kredit percuma bila daftar (ganti tempoh percubaan).
    'signup_bonus' => (int) env('SIGNUP_CREDITS', 3),

    // Pulangkan kredit bila carian gagal atau tak hasilkan satu lead pun.
    'refund_empty_searches' => true,

    // Jana semula dan follow-up percuma, tapi dihadkan setiap lead (kawal kos AI).
    'max_regenerations_per_lead' => 3,
    'max_followups_per_lead' => 3,

    'max_products' => 20,

    'packs' => [
        'pek10' => [
            'name' => 'Pek 10',
            'credits' => 10,
            'price_myr' => null, // Bob isi harga jualan
        ],
        'pek30' => [
            'name' => 'Pek 30',
            'credits' => 30,
            'price_myr' => null, // Bob isi harga jualan
            'popular' => true,
        ],
        'pek100' => [
            'name' => 'Pek 100',
            'credits' => 100,
            'price_myr' => null, // Bob isi harga jualan
        ],
    ],
];
