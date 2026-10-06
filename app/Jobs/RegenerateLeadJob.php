<?php

namespace App\Jobs;

use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\Lead;
use App\Services\Ai\LeadScorer;
use App\Services\Ai\MessageWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** User asked to regenerate one lead ("Jana semula"). The only path that overwrites saved AI results. */
class RegenerateLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $leadId, public bool $rescore = false) {}

    public function handle(LeadScorer $scorer, MessageWriter $writer): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        try {
            if ($this->rescore || ! $lead->isScored()) {
                $lead = $scorer->score($lead, force: true);
            }

            if ($lead->isFit()) {
                $writer->write($lead, force: true);
            }
        } catch (BudgetExceeded|PricesNotConfigured $e) {
            $lead->forceFill(['needs_review' => true, 'review_note' => $e->getMessage()])->save();
        }
    }
}
