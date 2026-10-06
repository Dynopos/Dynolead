<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = 'Produk '.Str::random(5);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sender_name' => 'Bob',
            'company' => 'DynoPOS Technologies, Pasir Mas',
            'pitch_core' => 'Produk ini bantu kedai rekod jualan.',
            'pitch_variants' => null,
            'cta' => 'Kalau nak info lanjut, balas je mesej ni.',
            'banned_words' => [],
            'fit_signals' => 'kaunter lambat',
            'filters' => ['min_rating' => 3.5, 'min_reviews' => 10, 'require_no_website' => false],
            'default_place_types' => ['kedai runcit'],
            'contact_info' => '011-1111 2222',
            'active' => true,
        ];
    }
}
