<?php

declare(strict_types=1);

return [
    'provider' => env('AI_PROVIDER', 'deepseek'),

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 300),

    'temperature_generation' => (float) env('AI_TEMPERATURE_GENERATION', 0.8),

    'temperature_regeneration' => (float) env('AI_TEMPERATURE_REGENERATION', 0.7),

    'models' => [
        'generation' => env('DEEPSEEK_GENERATION_MODEL', 'deepseek-v4-pro'),
        'regeneration' => env('DEEPSEEK_REGENERATION_MODEL', 'deepseek-v4-pro'),
        'section_regeneration' => env('DEEPSEEK_SECTION_MODEL', 'deepseek-v4-pro'),
        'title_regeneration' => env('DEEPSEEK_TITLE_MODEL', 'deepseek-v4-pro'),
    ],

    'pricing' => [
        'input_per_million' => (float) env('AI_INPUT_PRICE_PER_M', 0.28),
        'output_per_million' => (float) env('AI_OUTPUT_PRICE_PER_M', 0.42),
    ],

    'limits' => [
        'max_variations' => (int) env('AI_MAX_VARIATIONS', 10),
        'max_word_count' => (int) env('AI_MAX_WORD_COUNT', 5000),
        'max_monthly_generations' => (int) env('AI_MAX_MONTHLY_GENERATIONS', 100),
    ],
];
