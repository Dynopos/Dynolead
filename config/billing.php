<?php

/*
|--------------------------------------------------------------------------
| Model bayaran (Bob, Okt 2026)
|--------------------------------------------------------------------------
| 1. Percubaan percuma: trial_leads lead ATAU trial_days hari, mana dulu.
| 2. Aktifkan akaun: activation_fee_myr, sekali bayar.
| 3. Bayar ikut guna: pelanggan tambah baki RM; setiap carian ditolak ikut
|    kos sebenar (AI, dan Places jika include_places_cost) + markup_percent.
|    Contoh markup 20%: kos RM50 → caj RM60.
|
| Kos sebenar dikira dari config/ai_prices.php, jadi harga di situ MESTI tepat.
*/

return [

    'trial_days' => (int) env('TRIAL_DAYS', 14),
    'trial_leads' => (int) env('TRIAL_LEADS', 20),

    'activation_fee_myr' => (float) env('ACTIVATION_FEE_MYR', 23.90),

    'markup_percent' => (float) env('USAGE_MARKUP_PERCENT', 20),

    // Google Places juga kos sebenar setiap carian (selalunya lebih tinggi dari token AI).
    // false = caj ikut kos AI sahaja (Bob tanggung kos Places).
    'include_places_cost' => (bool) env('BILL_PLACES_COST', true),

    // Pilihan tambah baki (RM).
    'topup_options' => [20, 50, 100],

    // Jana semula dan follow-up AI percuma, tapi dihadkan setiap lead.
    'max_regenerations_per_lead' => 3,
    'max_followups_per_lead' => 3,

    'max_products' => 20,
];
