<?php

namespace App\Services\Search;

use App\Enums\LeadStatus;
use App\Enums\SearchStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Exceptions\WalletEmpty;
use App\Jobs\FetchDetailsJob;
use App\Jobs\FilterCandidatesJob;
use App\Jobs\ScoreLeadsJob;
use App\Jobs\SearchPlacesJob;
use App\Jobs\WriteMessagesJob;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlacesUsage;
use App\Models\Product;
use App\Models\Search;
use App\Services\Ai\LeadScorer;
use App\Services\Ai\MessageWriter;
use App\Services\Billing\WalletService;
use App\Services\Leads\RuleFilter;
use App\Services\Places\PlaceRepository;
use App\Services\Places\PlacesClient;
use InvalidArgumentException;

/**
 * Search pipeline (spec §4). Each step runs in its own queue job and is
 * idempotent, so a failed step can be retried without repeating finished work.
 *
 *   SearchPlacesJob → FilterCandidatesJob → FetchDetailsJob → ScoreLeadsJob → WriteMessagesJob
 */
class SearchPipeline
{
    public function __construct(
        private PlacesClient $places,
        private PlaceRepository $repository,
        private RuleFilter $filter,
        private LeadScorer $scorer,
        private MessageWriter $writer,
        private WalletService $wallet,
    ) {}

    /** @param  array<int, string>  $areas */
    public function start(Product $product, string $businessType, array $areas, int $max, ?float $estimateMyr = null, bool $isTrial = false): Search
    {
        $areas = array_values(array_filter(array_map('trim', $areas), 'strlen'));
        $businessType = trim($businessType);

        if ($businessType === '' || $areas === []) {
            throw new InvalidArgumentException('Jenis bisnes dan kawasan wajib diisi.');
        }

        $search = Search::query()->create([
            'product_id' => $product->id,
            'business_type' => $businessType,
            'areas' => $areas,
            'max_candidates' => self::clampMax($max),
            'is_trial' => $isTrial,
            'status' => SearchStatus::Pending,
            'estimate_myr' => $estimateMyr,
        ]);

        SearchPlacesJob::dispatch($search->id);

        return $search;
    }

    public static function clampMax(int $max): int
    {
        return max(1, min((int) config('dynoleads.search.hard_max', 60), $max));
    }

    /** Step 1: Text Search (cheap fields only). */
    public function searchPlaces(Search $search): void
    {
        if ($search->candidate_place_ids !== null) {
            FilterCandidatesJob::dispatch($search->id);

            return;
        }

        $search->markStatus(SearchStatus::Searching);

        $candidates = [];   // place_id => area
        $max = $search->max_candidates;

        foreach ($search->areas as $area) {
            $token = null;
            // Places returns at most 3 pages (60 results) per query.
            for ($page = 0; $page < 3 && count($candidates) < $max; $page++) {
                $result = $this->places->textSearch(
                    query: $search->business_type.' '.$area,
                    pageSize: min(20, $max - count($candidates)),
                    pageToken: $token,
                    searchId: $search->id,
                );
                $search->increment('places_calls');

                foreach ($result['places'] as $place) {
                    if (count($candidates) >= $max || empty($place['id']) || isset($candidates[$place['id']])) {
                        continue;
                    }
                    $this->repository->storeSummary($place);
                    $candidates[$place['id']] = $area;
                }

                $token = $result['next_page_token'];
                if ($token === null) {
                    break;
                }
            }

            if (count($candidates) >= $max) {
                break;
            }
        }

        $search->forceFill([
            'candidate_place_ids' => $candidates,
            'found_count' => count($candidates),
        ])->save();

        FilterCandidatesJob::dispatch($search->id);
    }

    /** Step 2: rule filter on Text Search data. No AI, no Places calls. */
    public function filterCandidates(Search $search): void
    {
        if ($search->passed_place_ids !== null) {
            FetchDetailsJob::dispatch($search->id);

            return;
        }

        $search->markStatus(SearchStatus::Filtering);

        $places = [];
        foreach (array_keys($search->candidate_place_ids ?? []) as $placeId) {
            $cached = $this->repository->cached($placeId);
            if ($cached === null) {
                $search->addRejection($placeId, 'Data carian dah tamat tempoh');

                continue;
            }
            $places[] = $cached;
        }

        $result = $this->filter->beforeDetails($search->product, $places);
        foreach ($result->rejected as $placeId => $reason) {
            $search->addRejection($placeId, $reason);
        }

        $search->forceFill([
            'passed_place_ids' => array_column($result->passed, 'id'),
            'passed_count' => count($result->passed),
        ])->save();

        FetchDetailsJob::dispatch($search->id);
    }

    /** Step 3: Place Details for candidates that passed, then the post-details rules. */
    public function fetchDetails(Search $search): void
    {
        $search->markStatus(SearchStatus::Details);
        $product = $search->product;
        $areas = $search->candidate_place_ids ?? [];

        foreach ($search->passed_place_ids ?? [] as $placeId) {
            if (isset(($search->rejections ?? [])[$placeId])
                || Lead::query()->where('place_id', $placeId)->where('product_id', $product->id)->exists()) {
                continue;
            }

            // Trial: stop at the lead limit. Paid: charge usage so far, stop when the balance is gone.
            // Both checks run before paying for Place Details or AI.
            if ($search->is_trial && $this->wallet->trialLeadsRemaining() === 0) {
                $search->addRejection($placeId, 'Had lead percubaan dicapai');
                $search->save();

                continue;
            }

            try {
                $this->wallet->ensureFunds($search);
            } catch (WalletEmpty $e) {
                $this->stop($search, SearchStatus::BudgetExceeded, $e->getMessage());

                return;
            }

            $place = $this->repository->details($placeId, $search->id);
            $search->increment('places_calls');

            $reason = $this->filter->afterDetails($product, $place);
            if ($reason !== null) {
                $search->addRejection($placeId, $reason);
                $search->save();

                continue;
            }

            Lead::query()->firstOrCreate(
                ['place_id' => $placeId, 'product_id' => $product->id],
                [
                    'search_id' => $search->id,
                    'status' => LeadStatus::Baru,
                    'business_type' => $search->business_type,
                    'area' => $areas[$placeId] ?? null,
                ],
            );
        }

        $search->forceFill([
            'lead_count' => Lead::query()->where('search_id', $search->id)->count(),
        ])->save();

        $this->afterDetails($search);
    }

    protected function afterDetails(Search $search): void
    {
        ScoreLeadsJob::dispatch($search->id);
    }

    /** Step 4: AI scoring with the cheap model, for leads without a saved score. */
    public function scoreLeads(Search $search): void
    {
        $search->markStatus(SearchStatus::Scoring);

        $leads = Lead::query()
            ->where('search_id', $search->id)
            ->whereNull('fit')
            ->where('needs_review', false)
            ->where('status', LeadStatus::Baru)
            ->orderBy('id')
            ->get();

        if (! $this->guardAi($search, function () use ($leads, $search) {
            foreach ($leads as $lead) {
                $this->wallet->ensureFunds($search);
                $this->scorer->score($lead);
                $search->increment('scored_count');
            }
        })) {
            return;
        }

        WriteMessagesJob::dispatch($search->id);
    }

    /** Step 5: write messages with the strong model, for fit leads without a message. */
    public function writeMessages(Search $search): void
    {
        $search->markStatus(SearchStatus::Writing);
        $threshold = (int) config('dynoleads.ai.fit_threshold');

        $leads = Lead::query()
            ->where('search_id', $search->id)
            ->where('fit', '>=', $threshold)
            ->whereNull('message')
            ->where('needs_review', false)
            ->where('status', LeadStatus::Baru)
            ->orderBy('id')
            ->get();

        if (! $this->guardAi($search, function () use ($leads, $search) {
            foreach ($leads as $lead) {
                $this->wallet->ensureFunds($search);
                $this->writer->write($lead);
                $search->increment('written_count');
            }
        })) {
            return;
        }

        $this->finish($search);
    }

    /** Run AI work; stop the search cleanly when the budget or prices block it. */
    private function guardAi(Search $search, callable $work): bool
    {
        try {
            $work();

            return true;
        } catch (BudgetExceeded $e) {
            $this->stop($search, SearchStatus::BudgetExceeded, $e->getMessage());
        } catch (PricesNotConfigured $e) {
            $this->stop($search, SearchStatus::Failed, $e->getMessage());
        }

        return false;
    }

    private function stop(Search $search, SearchStatus $status, string $message): void
    {
        $search->refresh();
        $search->forceFill(['actual_cost_myr' => $this->actualCost($search)])->save();
        $this->wallet->settle($search);
        $search->markStatus($status, $message);
    }

    public function finish(Search $search): void
    {
        $search->refresh();
        $search->forceFill([
            'actual_cost_myr' => $this->actualCost($search),
        ])->save();
        $this->wallet->settle($search);
        $search->markStatus(SearchStatus::Done);
    }

    public function actualCost(Search $search): float
    {
        $places = (float) PlacesUsage::query()->where('search_id', $search->id)->sum('cost_estimate');
        $ai = (float) AiUsage::query()->where('search_id', $search->id)->sum('cost_estimate');

        return round($places + $ai, 4);
    }
}
