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
];
