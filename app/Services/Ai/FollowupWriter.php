<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceeded;
use App\Models\Lead;
use App\Services\Leads\ContactRules;
use App\Services\Places\PlaceRepository;
use RuntimeException;

/** Short follow-up message with the cheap model (spec §3.4). */
class FollowupWriter
{
    public function __construct(
        private AiGateway $ai,
        private PromptRepository $prompts,
        private PlaceRepository $places,
        private MessageValidator $validator,
        private ContactRules $rules,
    ) {}

    /** @throws BudgetExceeded */
    public function write(Lead $lead): Lead
    {
        if ($this->rules->isSuppressed($lead->place_id)) {
            throw new RuntimeException('Kedai ini dalam senarai STOP.');
        }

        $product = $lead->product;
        $place = $this->places->cached($lead->place_id) ?? $this->places->forDisplay($lead->place_id);
        $banned = $product->bannedWords();

        $prompt = $this->prompts->render('followup', [
            'sender_name' => $product->sender_name,
            'company' => $product->company,
            'product_name' => $product->name,
            'cta' => $product->ctaText(),
            'banned_words' => $banned === [] ? '(tiada)' : implode(', ', $banned),
            'max_chars' => 500,
        ], [
            'shop_name' => $place['name'] ?? 'kedai',
            'days' => $lead->contacted_at ? (int) $lead->contacted_at->diffInDays(now()) : 3,
            'first_message' => mb_substr((string) $lead->message, 0, 900),
        ]);

        $response = $this->ai->call(
            purpose: 'followup',
            model: (string) config('dynoleads.ai.model_score'),
            system: $prompt->system,
            user: $prompt->user,
            maxTokens: (int) config('dynoleads.ai.max_tokens_followup'),
            schema: MessageWriter::SCHEMA,
            effort: config('dynoleads.ai.effort_score'),
            leadId: $lead->id,
            searchId: $lead->search_id,
        );

        $message = trim((string) ($response->json()['message'] ?? ''));
        $problems = $this->validator->problems($product, $message);

        $lead->forceFill([
            'followup_message' => $message === '' ? null : $message,
            'review_note' => $problems === [] ? null : 'Follow-up perlu semak manual: '.implode('; ', $problems),
        ])->save();

        return $lead;
    }
}
