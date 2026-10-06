<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'place_id' => 'place_'.Str::random(10),
            'product_id' => Product::factory(),
            'status' => LeadStatus::Baru,
            'business_type' => 'kedai runcit',
            'area' => 'Pasir Mas, Kelantan',
            'fit' => 80,
            'reason' => 'Kedai sibuk, kaunter selalu panjang.',
            'hook' => 'Ramai puji layanan mesra.',
            'gap' => 'Pelanggan komplen kaunter lambat.',
            'flag' => null,
            'message' => "Salam Kedai Contoh 👋\n\nSaya Bob dari DynoPOS.\n\nKalau tak berminat, balas STOP, saya tak ganggu lagi 🙏",
            'score_prompt_version' => 'score-v1',
            'prompt_version' => 'write-v1',
        ];
    }
}
