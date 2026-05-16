<?php

return [
    'provider' => 'anthropic',

    'anthropic' => [
        'api_key'        => env('ANTHROPIC_API_KEY'),
        'base_url'       => 'https://api.anthropic.com',
        'version'        => '2023-06-01',
        // Prompt caching is now GA — no beta header required. Set the
        // ANTHROPIC_BETA env var if a specific beta feature is needed.
        'beta'           => env('ANTHROPIC_BETA'),
        'timeout'        => 60,
    ],

    'models' => [
        // Heavy reasoning, sales conversations, tool use.
        'primary' => env('ANTHROPIC_MODEL_PRIMARY', 'claude-opus-4-7'),
        // Cheap classification, sentiment, memory extraction.
        'light'   => env('ANTHROPIC_MODEL_LIGHT', 'claude-haiku-4-5'),
    ],

    'limits' => [
        'max_tokens'    => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
        'history_chars' => 16000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Brand voice
    |--------------------------------------------------------------------------
    | These bullet points are baked into the system prompt and cached.
    */
    'brand_voice' => [
        'company_name' => 'Gadget',
        'language'     => 'Georgian (ka)',
        'voice' => [
            'Friendly, warm, never robotic.',
            'Speaks like a real Gadget store consultant — knowledgeable, casual, respectful.',
            'Short sentences. No corporate jargon.',
            'Uses emoji sparingly (1 per 2-3 messages), never spammy.',
            'Never argues with the customer. Always solves or escalates.',
        ],
        'forbidden' => [
            'Inventing stock, prices, discounts, warranty terms, delivery dates.',
            'Pushing customers to the website unnecessarily.',
            'Dumping long catalogs or technical spec walls of text.',
            'Replying when not actually sure — escalate instead.',
            'Discussing politics, religion, adult content, gambling, crypto schemes.',
        ],
    ],
];
