<?php

return [

    // Seller of the service (shown on Terma/Privasi and invoices).
    // Redirect other hosts (www, Forge test domain) to APP_URL's host. See RedirectToCanonicalHost.
    'canonical_redirect' => (bool) env('CANONICAL_REDIRECT', true),

    'company' => [
        'name' => env('COMPANY_NAME', 'DynoPOS Technologies'),
        'registration' => env('COMPANY_REGISTRATION'),
        'email' => env('COMPANY_EMAIL'),
        'address' => env('COMPANY_ADDRESS', 'Pasir Mas, Kelantan'),
        // Public WhatsApp for prospects (sales page, terms, privacy).
        'whatsapp' => env('COMPANY_WHATSAPP', '0182889932'),
    ],

    'ai' => [
        'model_score' => env('CLAUDE_MODEL_SCORE'),
        'model_write' => env('CLAUDE_MODEL_WRITE'),
        // Optional `output_config.effort` per purpose. Leave empty for models that
        // do not support effort (for example Haiku 4.5).
        'effort_score' => env('CLAUDE_EFFORT_SCORE'),
        'effort_write' => env('CLAUDE_EFFORT_WRITE', 'low'),
        'use_batch' => (bool) env('CLAUDE_USE_BATCH', false),
        'web_search' => (bool) env('CLAUDE_WEB_SEARCH', false),
        // Platform-wide AI cost cap per month, all customers together (central key).
        // Each customer is also capped by their plan (config/plans.php ai_budget_myr).
        'monthly_budget_myr' => (float) env('AI_MONTHLY_BUDGET_MYR', 100),
        'max_tokens_score' => 600,
        'max_tokens_write' => 2000,
        'max_tokens_followup' => 600,
        'fit_threshold' => 50,
        'review_limit' => 5,
        'review_chars' => 300,
    ],

    'places' => [
        'cache_hours' => (int) env('PLACES_CACHE_HOURS', 24),
        'language' => 'ms',
        'region' => 'MY',
    ],

    'price_table_path' => env('PRICE_TABLE_PATH', 'config/ai_prices.php'),

    'search' => [
        'default_max' => 20,
        'hard_max' => 60,
    ],

    'rules' => [
        'contact_window_days' => 30,
        'followup_after_days' => 3,
        'message_max_chars' => 900,
    ],

    // Fallback rates for the cost estimate when ai_usage has no 30-day data.
    'estimate_defaults' => [
        'pass_rate' => 0.5,
        'fit_rate' => 0.6,
        'score_input_tokens' => 1500,
        'score_output_tokens' => 300,
        'write_input_tokens' => 1500,
        'write_output_tokens' => 700,
    ],
];
