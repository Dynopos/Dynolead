<?php

namespace App\Jobs;

use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\Lead;
use App\Services\Ai\FollowupWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateFollowupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $leadId) {}

    public function handle(FollowupWriter $writer): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        try {
            $writer->write($lead);
        } catch (BudgetExceeded|PricesNotConfigured $e) {
            $lead->forceFill(['review_note' => $e->getMessage()])->save();
        }
    }
}
