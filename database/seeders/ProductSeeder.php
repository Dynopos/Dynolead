<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/** Seed products from spec §7. Safe to run more than once. */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $runcit = 'DynoPOS ni sistem POS untuk kedai kecil & sederhana: sekali bayar je, takde yuran bulanan atau tahunan. Imbas barcode, rekod jualan dan stok, laporan harian boleh tengok dalam telefon.';
        $butik = 'DynoPOS ni sistem POS untuk kedai kecil & sederhana: sekali bayar je, takde yuran bulanan atau tahunan. Rekod jualan dan stok setiap item, laporan harian boleh tengok dalam telefon.';
        $restoran = 'DynoPOS ni sistem POS untuk kedai makan: sekali bayar je, takde yuran bulanan atau tahunan. Setiap order dan bil direkod dengan tepat, laporan jualan harian boleh tengok dalam telefon.';

        Product::query()->updateOrCreate(['slug' => 'dynopos'], [
            'name' => 'DynoPOS',
            'sender_name' => 'Bob',
            'company' => 'DynoPOS Technologies, Pasir Mas',
            'pitch_core' => $runcit,
            'pitch_variants' => [
                [
                    'key' => 'runcit',
                    'match' => ['runcit', 'grocery', 'convenience', 'supermarket', 'minimarket', 'mini market', 'pasar raya', 'serbaneka', 'borong'],
                    'pitch' => $runcit,
                ],
                [
                    'key' => 'butik',
                    'match' => ['butik', 'boutique', 'pakaian', 'clothing', 'fashion', 'tudung', 'shoe_store', 'kasut'],
                    'pitch' => $butik,
                ],
                [
                    'key' => 'restoran',
                    'match' => ['restoran', 'restaurant', 'kedai makan', 'kafe', 'cafe', 'coffee', 'warung', 'gerai', 'meal_takeaway', 'nasi', 'tomyam', 'mamak'],
                    'pitch' => $restoran,
                ],
            ],
            'cta' => 'Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my',
            'banned_words' => ['demo'],
            'fit_signals' => 'kaunter lambat, barang banyak, stok habis, bil/resit tak tepat, banyak cawangan, waktu buka panjang, jualan dari beberapa saluran (dine-in + Grab)',
            'filters' => [
                'min_rating' => 3.5,
                'min_reviews' => 10,
                'require_no_website' => false,
            ],
            'default_place_types' => ['kedai runcit', 'butik pakaian', 'restoran', 'kafe'],
            'contact_info' => '011-1149 6842, 018-792 2844, dynopos.my',
            'active' => true,
        ]);

        Product::query()->updateOrCreate(['slug' => 'murahwebsite'], [
            'name' => 'murahwebsite.my',
            'sender_name' => 'Bob',
            'company' => 'murahwebsite.my (DynoPOS Technologies, Pasir Mas)',
            'pitch_core' => 'Kami boleh siapkan website premium RM200 je (harga asal RM999), siap dalam 2 hari, termasuk domain & hosting, mesra telefon.',
            'pitch_variants' => null,
            'cta' => 'Nak saya hantar contoh website yang kami dah buat? Info: murahwebsite.my',
            'banned_words' => [],
            'fit_signals' => 'review banyak tetapi tiada website, pelancong cari menu/lokasi, perlu katalog atau tempahan',
            'filters' => [
                'min_rating' => 3.5,
                'min_reviews' => 20,
                'require_no_website' => true,
            ],
            'default_place_types' => ['restoran', 'kafe', 'butik', 'bengkel', 'salon', 'bakeri'],
            'contact_info' => '018-288 9932',
            'active' => true,
        ]);
    }
}
