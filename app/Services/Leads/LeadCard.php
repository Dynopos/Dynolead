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

    public function telLink(): ?string
    {
        return $this->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $this->phone) : null;
    }
}
