<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactLog extends Model
{
    protected $table = 'contacts_log';

    public $timestamps = false;

    protected $fillable = ['place_id', 'product_id', 'lead_id', 'contacted_at'];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime'];
    }
}
