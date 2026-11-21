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
];
