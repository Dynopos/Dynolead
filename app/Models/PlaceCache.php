<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Short-lived copy of Google Places content (spec §6). */
class PlaceCache extends Model
{
    protected $table = 'place_cache';

    public $timestamps = false;

    protected $fillable = ['place_id', 'payload', 'has_details', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'has_details' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    public static function cutoff(): Carbon
    {
        return now()->subHours((int) config('dynoleads.places.cache_hours'));
    }

    public function scopeFresh(Builder $query): Builder
    {
        return $query->where('fetched_at', '>', static::cutoff());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('fetched_at', '<=', static::cutoff());
    }
}
