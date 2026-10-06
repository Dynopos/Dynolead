<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Services\Costs\PriceTable;

/**
 * Every Claude call goes through here:
 *   1. price check (no prices → no call),
 *   2. AiBudget::assertCanSpend() with a worst-case estimate,
 *   3. the call,
 *   4. an ai_usage row with real token counts and cost.
 */
class AiGateway
{
    public function __construct(
        private ClaudeClient $client,
        private AiBudget $budget,
        private PriceTable $prices,
    ) {}

    public function call(
        string $purpose,
        string $model,
        string $system,
        string $user,
        int $maxTokens,
        ?array $schema = null,
        ?string $effort = null,
        ?int $leadId = null,
        ?int $searchId = null,
    ): ClaudeResponse {
        // Worst case: ~3 characters per input token, and the full output allowance.
        $estimatedInput = (int) ceil((mb_strlen($system) + mb_strlen($user)) / 3);
        $estimate = $this->prices->aiCostMyr($model, $estimatedInput, $maxTokens);

        $this->budget->assertCanSpend($estimate);

        $response = $this->client->message($model, $system, $user, $maxTokens, $schema, $effort);

        AiUsage::query()->create([
            'model' => $model,
            'purpose' => $purpose,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cache_read_tokens' => $response->cacheReadTokens,
            'cache_write_tokens' => $response->cacheWriteTokens,
            'cost_estimate' => $this->prices->aiCostMyr(
                $model,
                $response->inputTokens,
                $response->outputTokens,
                $response->cacheReadTokens,
                $response->cacheWriteTokens,
            ),
            'lead_id' => $leadId,
            'search_id' => $searchId,
        ]);

        return $response;
    }
}
