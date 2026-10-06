<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Permanent STOP list (place_id and/or phone), for every product. */
class Suppression extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['place_id', 'phone', 'reason'];
}
