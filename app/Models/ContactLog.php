<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ContactLog extends Model
{
    use BelongsToWorkspace;

    protected $table = 'contacts_log';

    public $timestamps = false;

    protected $fillable = ['place_id', 'product_id', 'lead_id', 'contacted_at'];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime'];
    }
}
