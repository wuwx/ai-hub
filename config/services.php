<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'llm_gateway' => [
        'timeout_seconds' => (int) env('LLM_GATEWAY_TIMEOUT_SECONDS', 120),
        'anthropic_version' => env(
            'LLM_GATEWAY_ANTHROPIC_VERSION',
            '2023-06-01',
        ),
        'retry_attempts' => (int) env('LLM_GATEWAY_RETRY_ATTEMPTS', 2),
        'retry_backoff_ms' => (int) env('LLM_GATEWAY_RETRY_BACKOFF_MS', 150),
        'circuit_failure_threshold' => (int) env(
            'LLM_GATEWAY_CIRCUIT_FAILURE_THRESHOLD',
            5,
        ),
        'circuit_cooldown_seconds' => (int) env(
            'LLM_GATEWAY_CIRCUIT_COOLDOWN_SECONDS',
            60,
        ),
        'idempotency_ttl_seconds' => (int) env(
            'LLM_GATEWAY_IDEMPOTENCY_TTL_SECONDS',
            300,
        ),
        'api_key_rate_limit_per_minute' => (int) env(
            'LLM_GATEWAY_API_KEY_RATE_LIMIT_PER_MINUTE',
            120,
        ),
        'api_key_rate_limit_decay_seconds' => (int) env(
            'LLM_GATEWAY_API_KEY_RATE_LIMIT_DECAY_SECONDS',
            60,
        ),
        'max_concurrent_per_user' => (int) env(
            'LLM_GATEWAY_MAX_CONCURRENT_PER_USER',
            50,
        ),
        // Provider upstream secrets. Each entry is loaded from env() here (so it
        // survives `config:cache`) and referenced from llm_providers.secret_ref
        // as `secret://KEY` (recommended) or legacy `env://KEY`.
        'secrets' => array_filter([
            'OPENAI_API_KEY' => env('OPENAI_API_KEY'),
            'ANTHROPIC_API_KEY' => env('ANTHROPIC_API_KEY'),
            'GROQ_API_KEY' => env('GROQ_API_KEY'),
            'DEEPSEEK_API_KEY' => env('DEEPSEEK_API_KEY'),
            'MISTRAL_API_KEY' => env('MISTRAL_API_KEY'),
        ]),
    ],

    'billing' => [
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'free_plan_code' => env('BILLING_FREE_PLAN_CODE', 'free'),
    ],
];
