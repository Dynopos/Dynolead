<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceeded;
use App\Models\Lead;
use App\Services\Leads\ContactRules;
use App\Services\Places\PlaceRepository;
use RuntimeException;

/**
 * Writes the first WhatsApp message with the strong model (CLAUDE_MODEL_WRITE).
 * Checked in code after generation; one retry; then flagged "Semak manual".
 */
class MessageWriter
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => ['message' => ['type' => 'string']],
        'required' => ['message'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private AiGateway $ai,
        private PromptRepository $prompts,
        private PlaceRepository $places,
        private MessageValidator $validator,
        private ContactRules $rules,
    ) {}

    /**
     * Saved messages are reused: no new AI call unless $force (user asked).
     *
     * @throws BudgetExceeded
     */
    public function write(Lead $lead, bool $force = false): Lead
    {
        if ($lead->message !== null && ! $force) {
            return $lead;
        }

        if (! $lead->isFit()) {
            throw new RuntimeException('Lead ini belum dinilai atau tak sesuai, mesej tidak dijana.');
        }

        if ($this->rules->isSuppressed($lead->place_id)) {
            throw new RuntimeException('Kedai ini dalam senarai STOP. Mesej tidak boleh dijana.');
        }

        $product = $lead->product;
        $place = $this->places->cached($lead->place_id) ?? $this->places->forDisplay($lead->place_id);
        $banned = $product->bannedWords();

        $systemVars = [
            'sender_name' => $product->sender_name,
            'company' => $product->company,
            'pitch' => $product->pitchFor($lead->business_type, $place['types'] ?? []),
            'cta' => $product->cta,
            'banned_words' => $banned === [] ? '(tiada)' : implode(', ', $banned),
            'max_chars' => (int) config('dynoleads.rules.message_max_chars', 900),
        ];
        $userVars = [
            'shop_name' => $place['name'] ?? 'kedai',
            'hook' => $lead->hook ?? '',
            'gap' => $lead->gap ?? '',
            'feedback' => '',
        ];

        $message = null;
        $problems = [];
        $version = null;

        // First attempt plus exactly one retry.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            if ($problems !== []) {
                $userVars['feedback'] = 'Mesej sebelum ini ditolak semakan: '.implode('; ', $problems).'. Tulis semula dan betulkan.';
            }

            $prompt = $this->prompts->render('write', $systemVars, $userVars);
            $version = $prompt->version;

            $response = $this->ai->call(
                purpose: 'write',
                model: (string) config('dynoleads.ai.model_write'),
                system: $prompt->system,
                user: $prompt->user,
                maxTokens: (int) config('dynoleads.ai.max_tokens_write'),
                schema: self::SCHEMA,
                effort: config('dynoleads.ai.effort_write'),
                leadId: $lead->id,
                searchId: $lead->search_id,
            );

            $message = trim((string) ($response->json()['message'] ?? ''));
            $problems = $this->validator->problems($product, $message);

            if ($problems === []) {
                break;
            }
        }

        $lead->forceFill([
            'message' => $message === '' ? null : $message,
            'prompt_version' => $version,
            'needs_review' => $problems !== [],
            'review_note' => $problems === [] ? null : 'Semak manual: '.implode('; ', $problems),
        ])->save();

        return $lead;
    }
}
