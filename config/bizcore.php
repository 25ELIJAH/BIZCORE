<?php

return [
    // BizCore is a Kenyan-shilling system: every company transacts in KES, there is no per-company currency.
    'currency' => 'KES',

    /*
     | Payment methods offered when a company has not configured its own
     | (company setting `payment_methods`, a slug => label map).
     */
    'payment_methods' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card',
        'mobile_money' => 'Mobile money',
        'other' => 'Other',
    ],

    // Default invoice payment terms (days) when a company has not set `payment_terms_days`.
    'default_payment_terms_days' => 30,

    // Demo seeders only run locally. A hosted demo must opt in explicitly and should set its own password.
    'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', true),

    // Lets a new business create its own company from the sign-in page. Set SELF_SIGNUP=false to onboard companies by hand only.
    'self_signup' => (bool) env('SELF_SIGNUP', true),

    /*
     | M-Pesa (Safaricom Daraja) STK push. Each company can save its own paybill/till details on the Company page.
     | These values are only the fallback for a company that has not, so one shared account can serve a small setup.
     | environment: sandbox (testing, no real money) or production.
     */
    'mpesa' => [
        'environment' => env('MPESA_ENV', 'sandbox'),
        'type' => env('MPESA_TYPE', 'paybill'),            // paybill | till
        'shortcode' => env('MPESA_SHORTCODE'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'passkey' => env('MPESA_PASSKEY'),
    ],

    'allow_demo_seed' => (bool) env('ALLOW_DEMO_SEED', false),
    'demo_password' => env('DEMO_PASSWORD', 'password'),
];
