<?php

namespace App\Jobs;

use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\Lead;
use App\Services\Ai\FollowupWriter;
use App\Support\Tenancy\CurrentWorkspace;
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
        $lead = Lead::query()->withoutGlobalScope('workspace')->find($this->leadId);

        if ($lead === null) {
            return;
        }

        app(CurrentWorkspace::class)->runAs($lead->workspace_id, function () use ($lead, $writer) {
            try {
                $writer->write($lead);
            } catch (BudgetExceeded|PricesNotConfigured $e) {
                $lead->forceFill(['review_note' => $e->getMessage()])->save();
            }
        });
    }
}
