<?php

namespace App\Support\Billing;

use App\Models\Search;
use Closure;

/**
 * Marks AI and Places usage that belongs to a search the customer pays for.
 *
 * The search pipeline runs each stage inside runFor($search); AiGateway and
 * PlacesClient stamp usage rows with billable_search_id while it is active.
 * Regenerations, follow-ups and lead-card refreshes run outside it, so they
 * are never billed.
 */
class UsageMeter
{
    private ?int $searchId = null;

    public function searchId(): ?int
    {
        return $this->searchId;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runFor(Search $search, Closure $callback): mixed
    {
        $previous = $this->searchId;
        $this->searchId = $search->id;

        try {
            return $callback();
        } finally {
            $this->searchId = $previous;
        }
    }
}
