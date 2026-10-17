<?php

namespace App\Services\Ai;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ClaudeException;
use App\Models\Product;
use App\Services\Billing\WalletService;

/**
 * Business analysis before searching: who the product's best customers are, what signals
 * make a shop a good prospect and what rules one out. One call with the write model per
 * request; the result is saved on the product and feeds the search suggestions and scoring.
 */
class BusinessAnalyst
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'targets' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string'],
                    'why' => ['type' => 'string'],
                    'angle' => ['type' => 'string'],
                ],
                'required' => ['type', 'why', 'angle'],
                'additionalProperties' => false,
            ]],
            'signals' => ['type' => 'array', 'items' => ['type' => 'string']],
            'red_flags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'require_no_website' => ['type' => 'boolean'],
        ],
        'required' => ['summary', 'targets', 'signals', 'red_flags', 'require_no_website'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private AiGateway $ai,
        private PromptRepository $prompts,
        private WalletService $wallet,
    ) {}

    /** Analyses left for this product (unlimited for the internal workspace). */
    public function analysesLeft(Product $product): int
    {
        if ($this->wallet->isUnlimited()) {
            return PHP_INT_MAX;
        }

        return max(0, (int) config('billing.max_analyses_per_product', 5) - (int) $product->analysis_count);
    }

    /**
     * @throws AccountLimitReached|BudgetExceeded|ClaudeException
     */
    public function analyse(Product $product): Product
    {
        if ($this->analysesLeft($product) <= 0) {
            throw new AccountLimitReached('Had analisis untuk produk ini dah dicapai. Edit fakta produk dan hubungi kami jika perlu analisis semula.');
        }

        $prompt = $this->prompts->render('analyse', [], [
            'product_name' => $product->name,
            'company' => $product->company,
            'pitch_core' => $product->pitch_core,
            'place_types' => implode(', ', $product->default_place_types ?? []) ?: '-',
            'fit_signals' => $product->fit_signals ?: '-',
        ]);

        $response = $this->ai->call(
            purpose: 'analyse',
            model: (string) config('dynoleads.ai.model_write'),
            system: $prompt->system,
            user: $prompt->user,
            maxTokens: (int) config('dynoleads.ai.max_tokens_analyse', 1500),
            schema: self::SCHEMA,
            effort: config('dynoleads.ai.effort_write'),
        );

        $analysis = $this->clean($response->json());
        if ($analysis === null) {
            throw new ClaudeException('Analisis AI tak lengkap. Cuba sekali lagi.');
        }

        $product->forceFill([
            'analysis' => $analysis + ['version' => $prompt->version],
            'analysis_count' => (int) $product->analysis_count + 1,
            'analysed_at' => now(),
            // The search screen suggests these business types.
            'default_place_types' => array_column($analysis['targets'], 'type'),
            // Keep what the owner wrote; fill the signals only when empty.
            'fit_signals' => $product->fit_signals ?: implode('; ', $analysis['signals']),
        ])->save();

        return $product;
    }

    /** @return array{summary: string, targets: array, signals: array, red_flags: array, require_no_website: bool}|null */
    private function clean(?array $data): ?array
    {
        $text = fn ($v, int $max = 300) => mb_substr(trim((string) $v), 0, $max);

        $targets = collect($data['targets'] ?? [])
            ->filter(fn ($t) => is_array($t) && filled($t['type'] ?? null))
            ->map(fn ($t) => [
                'type' => mb_strtolower($text($t['type'], 40)),
                'why' => $text($t['why'] ?? ''),
                'angle' => $text($t['angle'] ?? ''),
            ])
            ->unique('type')
            ->take(6)
            ->values()
            ->all();

        if ($targets === [] || blank($data['summary'] ?? null)) {
            return null;
        }

        $list = fn ($items) => collect(is_array($items) ? $items : [])->map(fn ($v) => $text($v, 160))->filter()->take(6)->values()->all();

        return [
            'summary' => $text($data['summary'], 500),
            'targets' => $targets,
            'signals' => $list($data['signals'] ?? []),
            'red_flags' => $list($data['red_flags'] ?? []),
            'require_no_website' => (bool) ($data['require_no_website'] ?? false),
        ];
    }
}
