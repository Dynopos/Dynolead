<?php

namespace App\Http\Controllers;

use App\Services\Billing\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Public sales page. Signed-in users go straight to their leads. */
class HomeController
{
    public function __invoke(CreditService $credits): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('leads');
        }

        $bonus = (int) config('credits.signup_bonus');
        $perCredit = (int) config('credits.candidates_per_credit');

        return view('marketing.home', [
            'bonus' => $bonus,
            'perCredit' => $perCredit,
            'packs' => $credits->packs(),
            'faqs' => self::faqs($bonus, $perCredit),
        ]);
    }

    public static function faqs(int $bonus, int $perCredit): array
    {
        return [
            ['Adakah Dyno Leads hantar mesej secara automatik?', 'Tidak. Dyno Leads cari kedai, pilih yang sesuai dan tulis mesej. Anda tekan "Buka WhatsApp", semak mesej, dan tekan hantar sendiri. Ini jaga nombor WhatsApp anda daripada disekat dan memastikan setiap mesej betul.'],
            ['Dari mana senarai kedai datang?', 'Dari Google Maps. Anda pilih jenis bisnes dan kawasan (contoh: kedai runcit, Kota Bharu). Sistem tapis ikut rating, bilangan review dan sama ada kedai dah ada website.'],
            ['Boleh guna untuk produk saya?', 'Boleh, untuk apa-apa produk atau servis yang anda jual kepada bisnes lain: sistem POS, website, pemasaran, katering, percetakan dan lain-lain. Anda tulis profil produk sekali, AI guna untuk setiap mesej.'],
            ['AI akan reka harga atau janji palsu?', 'Tidak. AI hanya guna ayat dan fakta dalam profil produk anda dan review kedai. Mesej yang ada harga luar profil, perkataan dilarang atau tiada pilihan STOP akan ditolak secara automatik.'],
            ['Apa jadi bila kedai balas STOP?', 'Tanda lead sebagai "Tolak / STOP". Kedai itu takkan muncul lagi untuk semua produk anda. Kedai yang dah dihubungi juga tak boleh dapat mesej produk lain anda dalam 30 hari.'],
            ['Berapa kos satu carian?', "Satu carian sehingga {$perCredit} kedai guna 1 kredit. Carian lebih besar guna lebih kredit (contoh ".($perCredit * 2).' kedai = 2 kredit). Jumlah kredit ditunjuk sebelum anda tekan cari, dan kredit dipulangkan jika carian tak jumpa satu lead pun.'],
            ['Ada yuran bulanan?', 'Tiada. Anda beli pek kredit bila perlu dan guna bila nak cari prospek. Kredit tak luput. Jana semula mesej dan follow-up AI adalah percuma (dengan had munasabah setiap lead).'],
            ['Boleh cuba dulu?', "Boleh. Daftar dan dapat {$bonus} kredit percuma, tanpa kad kredit."],
            ['Bayar macam mana?', 'Beli pek kredit melalui CHIP: FPX online banking, kad atau e-wallet. Bayar sekali, tiada caj automatik, tiada kontrak.'],
        ];
    }
}
