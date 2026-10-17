<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\RegenerateLeadJob;
use App\Models\Lead;
use App\Services\Ai\PromptRepository;
use App\Services\Leads\ContactRules;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Console\Command;

/**
 * Admin, after a change to the scoring prompt: score "Tak sesuai" leads again with the
 * current prompt. Each lead is re-scored at most once per prompt version (it then carries
 * the new version), so running this twice costs nothing extra. AI budget checks still apply.
 */
class RescoreLeads extends Command
{
    protected $signature = 'dynoleads:rescore
        {--workspace= : Hanya workspace ini (id)}
        {--limit=500 : Had bilangan lead}
        {--dry-run : Kira sahaja, tiada panggilan AI}';

    protected $description = 'Nilai semula lead "Tak sesuai" yang dinilai dengan prompt lama';

    public function handle(PromptRepository $prompts, CurrentWorkspace $current, ContactRules $rules): int
    {
        $version = $prompts->version('score');

        // Admin command: deliberately across workspaces.
        $leads = Lead::query()->withoutGlobalScope('workspace')
            ->where('status', LeadStatus::TakSesuai)
            ->where(fn ($q) => $q->whereNull('score_prompt_version')->orWhere('score_prompt_version', '!=', $version))
            ->when($this->option('workspace'), fn ($q, $id) => $q->where('workspace_id', (int) $id))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id', 'workspace_id', 'place_id']);

        $queued = 0;
        foreach ($leads as $lead) {
            // STOP is permanent: never touch a suppressed shop.
            if ($current->runAs($lead->workspace_id, fn () => $rules->isSuppressed($lead->place_id))) {
                continue;
            }

            if (! $this->option('dry-run')) {
                RegenerateLeadJob::dispatch($lead->id, rescore: true);
            }
            $queued++;
        }

        $this->info($this->option('dry-run')
            ? "{$queued} lead akan dinilai semula dengan {$version}."
            : "{$queued} lead dihantar untuk dinilai semula dengan {$version}. Lead yang sesuai akan kembali ke status Baru dengan mesej.");

        return self::SUCCESS;
    }
}
