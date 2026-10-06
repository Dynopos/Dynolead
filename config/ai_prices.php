<?php

/*
|--------------------------------------------------------------------------
| Jadual harga AI dan Google Places
|--------------------------------------------------------------------------
| Harga Claude diisi Oktober 2026 (semak semula bila Anthropic ubah harga).
| Harga Places diisi Oktober 2026; usd_to_myr ditetapkan Bob.
|
| - Harga Claude: USD setiap 1 juta token (rujuk https://www.anthropic.com/pricing).
|   Kunci = ID model yang sama seperti CLAUDE_MODEL_SCORE / CLAUDE_MODEL_WRITE dalam .env.
|   Tukar model dalam .env? Tambah baris baru di sini juga.
| - Harga Places: USD setiap panggilan (rujuk Google Maps Platform pricing,
|   SKU Text Search Enterprise dan Place Details Enterprise + Atmosphere).
|
| Selagi harga model atau usd_to_myr kosong (null), app TIDAK akan panggil Claude, sebab had
| kos bulanan tidak boleh dikira dengan betul. Ini sengaja (paling selamat).
|
| Jangan tulis harga di tempat lain dalam kod.
*/

return [

    'models' => [
        'claude-haiku-4-5-20251001' => [
            'input' => 1.00,
            'output' => 5.00,
            'cache_write' => 1.25,  // tulis cache 5 minit = 1.25 × input
            'cache_read' => 0.10,   // baca cache = 0.1 × input
        ],
        'claude-sonnet-5-5' => [
            'input' => 2.00,
            'output' => 10.00,
            'cache_write' => 2.50,
            'cache_read' => 0.20,
        ],
    ],

    'places' => [
        // Harga senarai Google, peringkat pertama (Oktober 2026). Kuota percuma bulanan
        // Google tidak ditolak di sini, jadi caj pelanggan tidak pernah di bawah kos.
        'text_search' => 0.035,     // Text Search Enterprise: $35 / 1000 (ada rating)
        'details' => 0.025,         // Place Details Enterprise + Atmosphere: $25 / 1000 (ada review)
        'details_display' => 0.020, // Place Details Enterprise: $20 / 1000 (telefon, tanpa review)
    ],

    // Kadar tukaran USD ke RM (ditetapkan Bob, Oktober 2026).
    'usd_to_myr' => 4.70,
];
