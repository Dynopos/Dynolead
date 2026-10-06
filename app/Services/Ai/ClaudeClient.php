<?php

namespace App\Services\Ai;

use App\Exceptions\ClaudeException;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks to the Anthropic Messages API.
 *
 * Uses Laravel's HTTP client so tests can Http::fake() it. The system prompt
 * (product profile + rules) is marked for prompt caching. Model names come
 * from .env via config, never from code. No tools are sent, so the web search
 * tool stays off (CLAUDE_WEB_SEARCH=false).
 *
 * Do not call this directly: go through AiGateway, which enforces the budget
 * and records ai_usage.
 */
class ClaudeClient
{
    /**
     * @param  array|null  $schema  JSON schema for structured output (output_config.format)
     */
    public function message(
        string $model,
        string $system,
        string $user,
        int $maxTokens,
        ?array $schema = null,
        ?string $effort = null,
    ): ClaudeResponse {
        $key = (string) config('services.anthropic.key');

        if ($key === '') {
            throw new ClaudeException('ANTHROPIC_API_KEY belum diset dalam .env.');
        }

        $outputConfig = array_filter([
            'format' => $schema ? ['type' => 'json_schema', 'schema' => $schema] : null,
            'effort' => $effort ?: null,
        ]);

        $payload = array_filter([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
            'output_config' => $outputConfig ?: null,
        ], fn ($v) => $v !== null);

        $response = Http::baseUrl(config('services.anthropic.base_url'))
            ->timeout((int) config('services.anthropic.timeout', 120))
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => config('services.anthropic.version'),
            ])
            ->post('/v1/messages', $payload);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new ClaudeException("Claude API gagal ({$response->status()}): ".mb_substr((string) $message, 0, 300));
        }

        $json = (array) $response->json();
        $text = collect($json['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new ClaudeResponse(
            model: (string) ($json['model'] ?? $model),
            text: $text,
            stopReason: $json['stop_reason'] ?? null,
            inputTokens: (int) ($json['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($json['usage']['output_tokens'] ?? 0),
            cacheReadTokens: (int) ($json['usage']['cache_read_input_tokens'] ?? 0),
            cacheWriteTokens: (int) ($json['usage']['cache_creation_input_tokens'] ?? 0),
        );
    }
}
