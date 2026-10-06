<?php

namespace App\Services\Leads;

use App\Models\ContactLog;
use App\Models\Suppression;
use App\Support\MalaysianPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Hard rules from CLAUDE.md:
 *  - STOP is permanent (suppressions, by place_id and phone, all products).
 *  - One shop, one product's message per 30 days (contacts_log).
 */
class ContactRules
{
    public function windowDays(): int
    {
        return (int) config('dynoleads.rules.contact_window_days', 30);
    }

    public function isSuppressed(?string $placeId, ?string $phone = null): bool
    {
        $canonical = MalaysianPhone::canonical($phone);

        if ($placeId === null && $canonical === null) {
            return false;
        }

        return Suppression::query()
            ->where(function (Builder $q) use ($placeId, $canonical) {
                if ($placeId !== null) {
                    $q->orWhere('place_id', $placeId);
                }
                if ($canonical !== null) {
                    $q->orWhere('phone', $canonical);
                }
            })
            ->exists();
    }

    /**
     * Was this shop contacted in the last 30 days? Pass $exceptProductId to
     * ignore contacts made for that product (follow-ups for the same product).
     */
    public function contactedRecently(string $placeId, ?int $exceptProductId = null): bool
    {
        return ContactLog::query()
            ->where('place_id', $placeId)
            ->where('contacted_at', '>=', now()->subDays($this->windowDays()))
            ->when($exceptProductId, fn (Builder $q) => $q->where('product_id', '!=', $exceptProductId))
            ->exists();
    }

    /**
     * Constrain a leads query so suppressed shops, and shops contacted for a
     * different product in the last 30 days, are never shown.
     */
    public function applyVisibility(Builder $leads): Builder
    {
        $since = now()->subDays($this->windowDays());

        return $leads
            ->whereNotIn('leads.place_id', fn (QueryBuilder $q) => $q->select('place_id')->from('suppressions')->whereNotNull('place_id'))
            ->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                ->from('contacts_log')
                ->whereColumn('contacts_log.place_id', 'leads.place_id')
                ->whereColumn('contacts_log.product_id', '!=', 'leads.product_id')
                ->where('contacts_log.contacted_at', '>=', $since));
    }
}
