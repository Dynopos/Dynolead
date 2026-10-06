<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class PlacesUsage extends Model
{
    use BelongsToWorkspace;

    protected $table = 'places_usage';

    public const UPDATED_AT = null;

    protected $fillable = ['sku', 'place_id', 'cost_estimate', 'search_id', 'billable_search_id'];

    protected function casts(): array
    {
        return ['cost_estimate' => 'float'];
    }
}
