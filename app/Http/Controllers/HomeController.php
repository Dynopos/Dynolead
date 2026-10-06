<?php

namespace App\Http\Controllers;

use App\Services\Billing\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Public sales page. Signed-in users go straight to their leads. */
class HomeController
{
    public function __invoke(PlanService $plans): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('leads');
        }

        $trial = $plans->find((string) config('plans.trial_plan'));

        return view('marketing.home', [
            'trial' => $trial,
            'trialDays' => (int) config('plans.trial_days'),
            'plans' => $plans->forSale(),
            'faqs' => self::faqs((int) config('plans.trial_days')),
        ]);
    }

    public static function faqs(int $trialDays): array
    {
        return [
            ['Adakah Dyno Leads hantar mesej secara automatik?', 'Tidak. Dyno Leads cari kedai, pilih yang sesuai dan tulis mesej. Anda tekan "Buka WhatsApp", semak mesej, dan tekan hantar sendiri. Ini jaga nombor WhatsApp anda daripada disekat dan memastikan setiap mesej betul.'],
            ['Dari mana senarai kedai datang?', 'Dari Google Maps. Anda pilih jenis bisnes dan kawasan (contoh: kedai runcit, Kota Bharu). Sistem tapis ikut rating, bilangan review dan sama ada kedai dah ada website.'],
            ['Boleh guna untuk produk saya?', 'Boleh, untuk apa-apa produk atau servis yang anda jual kepada bisnes lain: sistem POS, website, pemasaran, katering, percetakan dan lain-lain. Anda tulis profil produk sekali, AI guna untuk setiap mesej.'],
            ['AI akan reka harga atau janji palsu?', 'Tidak. AI hanya guna ayat dan fakta dalam profil produk anda dan review kedai. Mesej yang ada harga luar profil, perkataan dilarang atau tiada pilihan STOP akan ditolak secara automatik.'],
            ['Apa jadi bila kedai balas STOP?', 'Tanda lead sebagai "Tolak / STOP". Kedai itu takkan muncul lagi untuk semua produk anda. Kedai yang dah dihubungi juga tak boleh dapat mesej produk lain anda dalam 30 hari.'],
            ['Boleh cuba dulu?', "Boleh. Daftar dan dapat percubaan percuma {$trialDays} hari tanpa kad kredit."],
            ['Bayar macam mana?', 'Bayar setiap 30 hari melalui CHIP: FPX online banking, kad atau e-wallet. Tiada caj automatik, tiada kontrak.'],
        ];
    }
}
