<?php

namespace App\Services\Ai;

use App\Enums\LeadStatus;
use App\Exceptions\BudgetExceeded;
use App\Models\Lead;
use App\Services\Places\PlaceRepository;

/** Scores a lead with the cheap model (CLAUDE_MODEL_SCORE). */
class LeadScorer
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'fit' => ['type' => 'integer'],
            'reason' => ['type' => 'string'],
            'hook' => ['type' => 'string'],
            'gap' => ['type' => 'string'],
            'flag' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
        ],
        'required' => ['fit', 'reason', 'hook', 'gap', 'flag'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private AiGateway $ai,
        private PromptRepository $prompts,
        private PlaceRepository $places,
    ) {}

    /**
     * Saved results are reused: no new AI call unless $force (user asked).
     *
     * @throws BudgetExceeded
     */
    public function score(Lead $lead, bool $force = false): Lead
    {
        if ($lead->isScored() && ! $force) {
            return $lead;
        }

        $product = $lead->product;
        $place = $this->places->details($lead->place_id, $lead->search_id);

        $prompt = $this->prompts->render('score', [
            'product_name' => $product->name,
            'company' => $product->company,
            'pitch_core' => $product->pitch_core,
            'fit_signals' => $product->fit_signals ?: '-',
        ], [
            'shop_name' => $place['name'] ?? '-',
            'business_type' => $lead->business_type ?? '-',
            'place_types' => implode(', ', array_slice($place['types'] ?? [], 0, 5)),
            'area' => $lead->area ?? ($place['address'] ?? '-'),
            'rating' => $place['rating'] ?? '-',
            'review_count' => $place['review_count'] ?? 0,
            'reviews' => $this->formatReviews($place['reviews'] ?? []),
        ]);

        $response = $this->ai->call(
            purpose: 'score',
            model: (string) config('dynoleads.ai.model_score'),
            system: $prompt->system,
            user: $prompt->user,
            maxTokens: (int) config('dynoleads.ai.max_tokens_score'),
            schema: self::SCHEMA,
            effort: config('dynoleads.ai.effort_score'),
            leadId: $lead->id,
            searchId: $lead->search_id,
        );

        $data = $response->json();

        if ($data === null || ! isset($data['fit']) || ! is_numeric($data['fit'])) {
            $lead->forceFill([
                'needs_review' => true,
                'review_note' => 'Penilaian AI gagal. Tekan Jana semula untuk cuba lagi.',
                'score_prompt_version' => $prompt->version,
            ])->save();

            return $lead;
        }

        $fit = max(0, min(100, (int) $data['fit']));

        $lead->forceFill([
            'fit' => $fit,
            'reason' => $this->clean($data['reason'] ?? null),
            'hook' => $this->clean($data['hook'] ?? null),
            'gap' => $this->clean($data['gap'] ?? null),
            'flag' => $this->clean($data['flag'] ?? null),
            'score_prompt_version' => $prompt->version,
            'needs_review' => false,
            'review_note' => null,
        ]);

        $threshold = (int) config('dynoleads.ai.fit_threshold');
        if ($fit < $threshold && $lead->status === LeadStatus::Baru) {
            $lead->status = LeadStatus::TakSesuai;
            $lead->status_changed_at = now();
        } elseif ($fit >= $threshold && $lead->status === LeadStatus::TakSesuai) {
            $lead->status = LeadStatus::Baru;
            $lead->status_changed_at = now();
        }

        $lead->save();

        return $lead;
    }

    private function formatReviews(array $reviews): string
    {
        if ($reviews === []) {
            return '(tiada review)';
        }

        return collect($reviews)
            ->map(fn (array $r, int $i) => ($i + 1).'. ['.($r['rating'] ?? '-').'★] '.$r['text'])
            ->implode("\n");
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
