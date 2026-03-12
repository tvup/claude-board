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
];
