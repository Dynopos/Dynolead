<?php

/*
|--------------------------------------------------------------------------
| Jadual harga AI dan Google Places
|--------------------------------------------------------------------------
| Bob isi ikut harga semasa.
|
| - Harga Claude: USD setiap 1 juta token (rujuk https://www.anthropic.com/pricing).
|   Kunci = ID model yang sama seperti CLAUDE_MODEL_SCORE / CLAUDE_MODEL_WRITE dalam .env.
|   Tukar model dalam .env? Tambah baris baru di sini juga.
| - Harga Places: USD setiap panggilan (rujuk Google Maps Platform pricing,
|   SKU Text Search Enterprise dan Place Details Enterprise + Atmosphere).
|
| Selagi harga model kosong (null), app TIDAK akan panggil Claude, sebab had
| kos bulanan tidak boleh dikira dengan betul. Ini sengaja (paling selamat).
|
| Jangan tulis harga di tempat lain dalam kod.
*/

return [

    'models' => [
        'claude-haiku-4-5-20251001' => [
            'input' => null,        // Bob isi ikut harga semasa
            'output' => null,       // Bob isi ikut harga semasa
            'cache_write' => null,  // Bob isi ikut harga semasa (tulis cache 5 minit)
            'cache_read' => null,   // Bob isi ikut harga semasa
        ],
        'claude-sonnet-5-5' => [
            'input' => null,        // Bob isi ikut harga semasa
            'output' => null,       // Bob isi ikut harga semasa
            'cache_write' => null,  // Bob isi ikut harga semasa
            'cache_read' => null,   // Bob isi ikut harga semasa
        ],
    ],

    'places' => [
        'text_search' => null,      // Bob isi ikut harga semasa (USD setiap panggilan)
        'details' => null,          // Bob isi ikut harga semasa (USD setiap panggilan)
        'details_display' => null,  // Bob isi ikut harga semasa (Details tanpa review)
    ],

    // Kadar tukaran USD ke RM. Bob isi ikut kadar semasa.
    'usd_to_myr' => null,
];
