<?php

namespace App\Http\Controllers;

use App\Services\Billing\PriceGuide;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Public sales page. Signed-in users go straight to their leads. */
class HomeController
{
    public function __invoke(PriceGuide $prices): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('leads');
        }

        $trial = ['leads' => (int) config('billing.trial_leads'), 'days' => (int) config('billing.trial_days')];

        return view('marketing.home', [
            'trial' => $trial,
            'fee' => (float) config('billing.activation_fee_myr'),
            'markup' => (float) config('billing.markup_percent'),
            'guide' => $guide = $prices->get(),
            'faqs' => self::faqs($trial, $guide),
        ]);
    }

    public static function faqs(array $trial, ?array $guide = null): array
    {
        $perLead = '';
        if ($guide && $guide['topups'] !== []) {
            $example = array_key_exists(50, $guide['topups']) ? 50 : array_key_last($guide['topups']);
            $perLead = ' Anggaran RM'.number_format($guide['per_lead_sen'] / 100, 2).' setiap lead: contohnya baki RM'.$example.' cukup untuk lebih kurang '.$guide['topups'][$example].' lead.';
        }

        return [
            ['Adakah Dyno Leads hantar mesej secara automatik?', 'Tidak. Dyno Leads cari kedai, pilih yang sesuai dan tulis mesej. Anda tekan "Buka WhatsApp", semak mesej, dan tekan hantar sendiri. Ini jaga nombor WhatsApp anda daripada disekat dan memastikan setiap mesej betul.'],
            ['Dari mana senarai kedai datang?', 'Dari Google Maps. Anda pilih jenis bisnes dan kawasan (contoh: kedai runcit, Kota Bharu). Sistem tapis ikut rating, bilangan review dan sama ada kedai dah ada website.'],
            ['Boleh guna untuk produk saya?', 'Boleh, untuk apa-apa produk atau servis yang anda jual kepada bisnes lain: sistem POS, website, pemasaran, katering, percetakan dan lain-lain. Anda tulis profil produk sekali, AI guna untuk setiap mesej.'],
            ['AI akan reka harga atau janji palsu?', 'Tidak. AI hanya guna ayat dan fakta dalam profil produk anda dan review kedai. Mesej yang ada harga luar profil, perkataan dilarang atau tiada pilihan STOP akan ditolak secara automatik.'],
            ['Apa jadi bila kedai balas STOP?', 'Tanda lead sebagai "Tolak / STOP". Kedai itu takkan muncul lagi untuk semua produk anda. Kedai yang dah dihubungi juga tak boleh dapat mesej produk lain anda dalam 30 hari.'],
            ['Berapa harga?', 'Cuba percuma dulu. Lepas tu aktifkan akaun dengan bayaran sekali RM'.number_format((float) config('billing.activation_fee_myr'), 2).'. Selepas itu anda hanya bayar ikut lead dari baki prabayar.'.$perLead.' Tiada yuran bulanan.'],
            ['Berapa caj satu carian?', 'Ikut penggunaan sebenar. Anggaran caj ditunjuk sebelum anda tekan cari, dan caj sebenar biasanya kecil. Carian berhenti sendiri jika baki habis, jadi anda tak akan dicaj lebih dari baki.'],
            ['Boleh cuba dulu?', "Boleh. Percubaan percuma {$trial['leads']} lead atau {$trial['days']} hari (mana dulu), tanpa kad kredit."],
            ['Bayar macam mana?', 'Melalui CHIP: FPX online banking, kad atau e-wallet. Tambah baki bila perlu (RM20, RM50, RM100). Tiada caj automatik, tiada kontrak.'],
        ];
    }
}
