<?php

namespace Database\Factories;

use App\Enums\SearchStatus;
use App\Models\Product;
use App\Models\Search;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Search> */
class SearchFactory extends Factory
{
    protected $model = Search::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'business_type' => 'kedai runcit',
            'areas' => ['Pasir Mas, Kelantan'],
            'max_candidates' => 20,
            'status' => SearchStatus::Pending,
        ];
    }
}
