<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\Product;
use App\Support\MalaysianPhone;

/**
 * Cheap rule filter. No AI, no token cost (spec §4).
 *
 * beforeDetails() runs on Text Search results, afterDetails() on Place Details.
 */
class RuleFilter
{
    public const SUPPRESSED = 'Dalam senarai STOP';

    public const CONTACTED = 'Dah dihubungi kurang 30 hari';

    public const EXISTING_LEAD = 'Dah ada dalam senarai lead';

    public const CLOSED = 'Kedai dah tutup';

    public const LOW_RATING = 'Rating bawah minimum';

    public const FEW_REVIEWS = 'Review bawah minimum';

    public const TYPE_EXCLUDED = 'Jenis tak padan';

    public const HAS_WEBSITE = 'Dah ada website';

    public const NO_PHONE = 'Tiada nombor telefon';

    public function __construct(private ContactRules $rules) {}

    /** @param  array<int, array>  $places  normalised PlaceData */
    public function beforeDetails(Product $product, array $places): FilterResult
    {
        $existing = Lead::query()
            ->where('product_id', $product->id)
            ->whereIn('place_id', array_column($places, 'id'))
            ->pluck('place_id')
            ->flip();

        $passed = [];
        $rejected = [];

        foreach ($places as $place) {
            $reason = $this->summaryReason($product, $place, $existing->has($place['id']));

            if ($reason === null) {
                $passed[] = $place;
            } else {
                $rejected[$place['id']] = $reason;
            }
        }

        return new FilterResult($passed, $rejected);
    }

    /** Reason to drop a candidate after Place Details, or null if it passes. */
    public function afterDetails(Product $product, array $place): ?string
    {
        if ($this->rules->isSuppressed($place['id'], $place['phone'] ?? null)) {
            return self::SUPPRESSED;
        }

        if ($this->rules->contactedRecently($place['id'])) {
            return self::CONTACTED;
        }

        if ($product->requiresNoWebsite() && filled($place['website'] ?? null)) {
            return self::HAS_WEBSITE;
        }

        if (MalaysianPhone::toLocal($place['phone'] ?? null) === null) {
            return self::NO_PHONE;
        }

        return $this->ratingReason($product, $place);
    }

    private function summaryReason(Product $product, array $place, bool $hasLead): ?string
    {
        if ($this->rules->isSuppressed($place['id'])) {
            return self::SUPPRESSED;
        }

        if ($this->rules->contactedRecently($place['id'])) {
            return self::CONTACTED;
        }

        if ($hasLead) {
            return self::EXISTING_LEAD;
        }

        if (in_array($place['business_status'] ?? null, ['CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY'], true)) {
            return self::CLOSED;
        }

        $excluded = (array) $product->filter('exclude_types', []);
        if ($excluded !== [] && array_intersect($excluded, $place['types'] ?? []) !== []) {
            return self::TYPE_EXCLUDED;
        }

        return $this->ratingReason($product, $place);
    }

    private function ratingReason(Product $product, array $place): ?string
    {
        if ($product->minRating() > 0 && (float) ($place['rating'] ?? 0) < $product->minRating()) {
            return self::LOW_RATING;
        }

        if ((int) ($place['review_count'] ?? 0) < $product->minReviews()) {
            return self::FEW_REVIEWS;
        }

        return null;
    }
}
