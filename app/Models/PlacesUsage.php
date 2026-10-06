<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacesUsage extends Model
{
    protected $table = 'places_usage';

    public const UPDATED_AT = null;

    protected $fillable = ['sku', 'place_id', 'cost_estimate', 'search_id'];

    protected function casts(): array
    {
        return ['cost_estimate' => 'float'];
    }
}
