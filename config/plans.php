<?php

/*
|--------------------------------------------------------------------------
| Pelan langganan (Fasa 2)
|--------------------------------------------------------------------------
| Bob tetapkan harga dan kuota (spec §11 soalan 4).
|
| price_myr        RM setiap 30 hari. null = pelan belum dibuka untuk dibeli.
| monthly_leads    Lead baru sebulan (kalendar). null = tanpa had.
| ai_budget_myr    Had kos AI sebulan untuk satu pelanggan (lindungi kos kunci pusat).
|                  null = guna had dalam app (AI_MONTHLY_BUDGET_MYR).
| max_products     Bilangan profil produk. null = tanpa had.
| max_candidates   Calon maksimum setiap carian (had keras 60).
|
| Pastikan ai_budget_myr cukup untuk monthly_leads: lihat kos sebenar setiap lead di
| halaman Kos selepas beberapa carian, kemudian laraskan.
*/

return [

    'trial_plan' => 'percubaan',
    'trial_days' => (int) env('TRIAL_DAYS', 14),

    'plans' => [
        'percubaan' => [
            'name' => 'Percubaan',
            'price_myr' => 0,
            'purchasable' => false,
            'monthly_leads' => 20,
            'ai_budget_myr' => 5,
            'max_products' => 1,
            'max_candidates' => 20,
            'features' => ['20 lead', '1 produk', 'Mesej AI custom'],
        ],
        'asas' => [
            'name' => 'Asas',
            'price_myr' => null, // Bob isi harga jualan
            'purchasable' => true,
            'monthly_leads' => 150,
            'ai_budget_myr' => 40,
            'max_products' => 3,
            'max_candidates' => 40,
            'features' => ['150 lead sebulan', '3 produk', 'Follow-up AI'],
        ],
        'pro' => [
            'name' => 'Pro',
            'price_myr' => null, // Bob isi harga jualan
            'purchasable' => true,
            'monthly_leads' => 500,
            'ai_budget_myr' => 120,
            'max_products' => 10,
            'max_candidates' => 60,
            'features' => ['500 lead sebulan', '10 produk', 'Follow-up AI', 'Carian 60 calon'],
        ],
        // Platform owner's own workspace. Never sold.
        'dalaman' => [
            'name' => 'Dalaman',
            'price_myr' => 0,
            'purchasable' => false,
            'monthly_leads' => null,
            'ai_budget_myr' => null,
            'max_products' => null,
            'max_candidates' => 60,
            'features' => [],
        ],
    ],
];
