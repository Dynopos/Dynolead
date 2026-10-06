<?php

namespace App\Services\Leads;

use App\Models\Lead;

/** Everything one lead card needs. Google fields come from the short cache. */
final class LeadCard
{
    public function __construct(
        public readonly Lead $lead,
        public readonly ?string $name,
        public readonly ?float $rating,
        public readonly int $reviewCount,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $mapsUri,
        public readonly bool $isMobile,
        public readonly bool $isLandline,
        public readonly ?string $whatsappLink,
        public readonly bool $messageOk,
        public readonly array $messageProblems,
        public readonly bool $placeDataMissing,
    ) {}

    public function initial(): string
    {
        $name = preg_replace('/^(kedai|restoran|kafe|cafe|butik)\s+/iu', '', trim((string) $this->name)) ?: '?';

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    /** Stable gradient per shop, so cards are easy to tell apart. */
    public function avatarGradient(): string
    {
        $palette = [
            'from-emerald-400 to-teal-600',
            'from-sky-400 to-indigo-500',
            'from-amber-400 to-orange-500',
            'from-fuchsia-400 to-purple-600',
            'from-rose-400 to-pink-600',
            'from-lime-400 to-green-600',
        ];

        return $palette[crc32($this->lead->place_id) % count($palette)];
    }

    public function telLink(): ?string
    {
        return $this->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $this->phone) : null;
    }
}
