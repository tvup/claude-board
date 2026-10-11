<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Billing Model
    |--------------------------------------------------------------------------
    |
    | Controls how cost figures are labeled in the dashboard.
    |
    | 'subscription' — Fixed monthly plan (Pro/Max). Costs shown as "Usage Value"
    |                   since the user owes nothing extra.
    | 'api'          — Pay-per-use API billing. Costs shown as "Est. Cost"
    |                   representing estimated charges.
    |
    */
    'billing_model' => env('CLAUDE_BILLING_MODEL', 'subscription'),

    /*
    |--------------------------------------------------------------------------
    | Session Group Window
    |--------------------------------------------------------------------------
    |
    | When a new session arrives from the same user and project within this
    | many minutes of a previous session, they are automatically grouped.
    | This handles Claude Code restarts (e.g. plan approval with context clear).
    |
    */
    'session_group_window' => env('CLAUDE_BOARD_GROUP_WINDOW', 5),

    /*
    |--------------------------------------------------------------------------
    | Usage API URL
    |--------------------------------------------------------------------------
    |
    | When set, the dashboard fetches Claude usage stats (rate limits, balance)
    | from this URL and displays them on the homepage. Leave empty to disable.
    |
    */
    'usage_api_url' => env('CLAUDE_USAGE_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Usage API Cache TTL
    |--------------------------------------------------------------------------
    |
    | How many seconds to cache the response from the usage API. Avoids
    | blocking a PHP-FPM worker on every dashboard poll request.
    |
    */
    'usage_api_cache_ttl' => (int) env('CLAUDE_USAGE_CACHE_TTL', 20),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Cache TTL
    |--------------------------------------------------------------------------
    |
    | How many seconds to cache the full dashboard data response. The dashboard
    | queries are expensive on a Raspberry Pi — caching prevents them from
    | running on every 5s poll request.
    |
    */
    'dashboard_cache_ttl' => (int) env('CLAUDE_DASHBOARD_CACHE_TTL', 5),

    'home_assistant' => [
        'base_url' => env('HA_BASE_URL'),
        'token' => env('HA_TOKEN'),
        'sensor_definitions' => [
            'subscription_type' => ['entity_id' => env('HA_SENSOR_SUBSCRIPTION_TYPE')],
            'current_session_percent' => ['entity_id' => env('HA_SENSOR_CURRENT_SESSION_PERCENT')],
            'this_week_percent' => ['entity_id' => env('HA_SENSOR_THIS_WEEK_PERCENT')],
            'limit_resets_count' => ['entity_id' => env('HA_SENSOR_LIMIT_RESETS_COUNT')],
            'cloud_session_credits_left' => ['entity_id' => env('HA_SENSOR_CLOUD_SESSION_CREDITS_LEFT')],
            'cloud_session_credits_total' => ['entity_id' => env('HA_SENSOR_CLOUD_SESSION_CREDITS_TOTAL')],
            'cloud_session_credits_expires_at' => ['entity_id' => env('HA_SENSOR_CLOUD_SESSION_CREDITS_EXPIRES_AT')],
            'usage_credits_amount' => ['entity_id' => env('HA_SENSOR_USAGE_CREDITS_AMOUNT')],
            'usage_credits_enabled' => ['entity_id' => env('HA_SENSOR_USAGE_CREDITS_ENABLED')],
            'usage_credits_info' => ['entity_id' => env('HA_SENSOR_USAGE_CREDITS_INFO')],
        ],
    ],
];
