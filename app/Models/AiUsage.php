<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    protected $table = 'ai_usage';

    public const UPDATED_AT = null;

    protected $fillable = [
        'model', 'purpose', 'input_tokens', 'output_tokens', 'cache_read_tokens',
        'cache_write_tokens', 'cost_estimate', 'lead_id', 'search_id',
    ];

    protected function casts(): array
    {
        return ['cost_estimate' => 'float'];
    }
}
