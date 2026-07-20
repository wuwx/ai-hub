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
        'idempotency_ttl_seconds' => (int) env(
            'LLM_GATEWAY_IDEMPOTENCY_TTL_SECONDS',
            300,
        ),
    ],

    'billing' => [
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'free_plan_code' => env('BILLING_FREE_PLAN_CODE', 'free'),
    ],
];
