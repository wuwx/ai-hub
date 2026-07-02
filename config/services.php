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
        'max_concurrent_per_team' => (int) env(
            'LLM_GATEWAY_MAX_CONCURRENT_PER_TEAM',
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
        'plans' => [
            'free' => [
                'name' => 'Free',
                'description' => 'For personal projects and evaluation',
                'monthly_price_cents' => 0,
                'stripe_price_id' => env('STRIPE_FREE_PRICE_ID', 'price_free'),
                'daily_token_limit' => 20_000,
                'weekly_token_limit' => 120_000,
                'monthly_token_limit' => 500_000,
                'features' => [
                    '20K daily tokens',
                    '500K monthly tokens',
                    'All LLM providers',
                    'Community support',
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'For growing teams and production workloads',
                'monthly_price_cents' => 4900,
                'stripe_price_id' => env('STRIPE_PRO_PRICE_ID', 'price_pro'),
                'daily_token_limit' => 300_000,
                'weekly_token_limit' => 2_000_000,
                'monthly_token_limit' => 8_000_000,
                'features' => [
                    '300K daily tokens',
                    '8M monthly tokens',
                    'Priority routing',
                    'Email support',
                    'Team collaboration',
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'Unlimited scale for large organizations',
                'monthly_price_cents' => 19900,
                'stripe_price_id' => env(
                    'STRIPE_ENTERPRISE_PRICE_ID',
                    'price_enterprise',
                ),
                'daily_token_limit' => null,
                'weekly_token_limit' => null,
                'monthly_token_limit' => null,
                'features' => [
                    'Unlimited tokens',
                    'Dedicated infrastructure',
                    'Custom SLA',
                    'Priority support',
                    'SSO & audit logs',
                    'Custom models',
                ],
            ],
        ],
    ],
];
