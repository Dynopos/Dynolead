<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/** Permanent STOP list (place_id and/or phone), for every product. */
class Suppression extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $fillable = ['place_id', 'phone', 'reason'];
}
