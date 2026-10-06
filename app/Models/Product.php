<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'sender_name', 'company', 'pitch_core', 'pitch_variants', 'cta',
        'banned_words', 'fit_signals', 'filters', 'default_place_types', 'contact_info', 'active',
    ];

    protected function casts(): array
    {
        return [
            'pitch_variants' => 'array',
            'banned_words' => 'array',
            'filters' => 'array',
            'default_place_types' => 'array',
            'active' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function searches(): HasMany
    {
        return $this->hasMany(Search::class);
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return ($this->filters ?? [])[$key] ?? $default;
    }

    public function minRating(): float
    {
        return (float) $this->filter('min_rating', 0);
    }

    public function minReviews(): int
    {
        return (int) $this->filter('min_reviews', 0);
    }

    public function requiresNoWebsite(): bool
    {
        return (bool) $this->filter('require_no_website', false);
    }

    /** Banned words, trimmed and without blanks. */
    public function bannedWords(): array
    {
        return array_values(array_filter(array_map('trim', $this->banned_words ?? []), 'strlen'));
    }

    /**
     * Pick the pitch paragraph for a shop. Variants match first on the search
     * term typed by the user, then on Google place types; falls back to pitch_core.
     */
    public function pitchFor(?string $businessType, array $placeTypes = []): string
    {
        foreach ([[$businessType ?? ''], $placeTypes] as $terms) {
            $haystack = mb_strtolower(trim(implode(' ', $terms)));
            if ($haystack === '') {
                continue;
            }

            foreach ($this->pitch_variants ?? [] as $variant) {
                foreach ($variant['match'] ?? [] as $needle) {
                    $needle = mb_strtolower(trim($needle));
                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        return $variant['pitch'];
                    }
                }
            }
        }

        return $this->pitch_core;
    }
}
