<?php

namespace App\Services\Places;

/** Normalises Places API (New) objects into the small shape the app uses. */
final class PlaceData
{
    public static function fromApi(array $place, bool $withDetails = false, bool $withReviews = false): array
    {
        $data = [
            'id' => $place['id'] ?? null,
            'name' => $place['displayName']['text'] ?? null,
            'types' => $place['types'] ?? [],
            'primary_type' => $place['primaryType'] ?? null,
            'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
            'review_count' => (int) ($place['userRatingCount'] ?? 0),
            'address' => $place['shortFormattedAddress'] ?? null,
            'business_status' => $place['businessStatus'] ?? null,
        ];

        if ($withDetails) {
            $data += [
                'phone' => $place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? null,
                'website' => $place['websiteUri'] ?? null,
                'maps_uri' => $place['googleMapsUri'] ?? null,
            ];
        }

        if ($withReviews) {
            $data['reviews'] = self::trimReviews($place['reviews'] ?? []);
        }

        return $data;
    }

    /** Max 5 reviews, 300 characters each (spec §9.2). */
    public static function trimReviews(array $reviews): array
    {
        $limit = (int) config('dynoleads.ai.review_limit', 5);
        $chars = (int) config('dynoleads.ai.review_chars', 300);

        return collect($reviews)
            ->map(function (array $review) use ($chars) {
                $text = trim((string) ($review['originalText']['text'] ?? $review['text']['text'] ?? $review['text'] ?? ''));

                return [
                    'rating' => isset($review['rating']) ? (int) $review['rating'] : null,
                    'text' => mb_substr($text, 0, $chars),
                ];
            })
            ->filter(fn (array $review) => $review['text'] !== '')
            ->take($limit)
            ->values()
            ->all();
    }
}
