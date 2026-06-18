<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OPay Checkout Integration
    |--------------------------------------------------------------------------
    | Register at https://merchant.opaycheckout.com to obtain your credentials.
    | Set OPAY_ENV=staging for testing or OPAY_ENV=production for live.
    |
    | Webhook:
    |   Set https://yourdomain.com/opay/webhook in the OPay merchant dashboard
    |   under Settings > Webhook URL.
    |
    | Signature:
    |   OPay signs requests with SHA-512 HMAC using your secret key.
    |   We verify all incoming webhooks the same way.
    */

    'merchant_id' => env('OPAY_MERCHANT_ID', ''),
    'secret_key'  => env('OPAY_SECRET_KEY', ''),
    'env'         => env('OPAY_ENV', 'staging'), // 'staging' or 'production'

    'base_url' => [
        'staging'    => 'https://testapi.opaycheckout.com',
        'production' => 'https://liveapi.opaycheckout.com',
    ],

    'endpoints' => [
        'create' => '/api/v1/international/payment/create',
        'query'  => '/api/v1/international/payment/query',
        'cancel' => '/api/v1/international/cashier/close',
    ],

    /*
     * Default expiry for a virtual account in minutes.
     * OPay defaults to 30; we use 40 to give customers a little more time.
     */
    'expire_minutes' => (int) env('OPAY_EXPIRE_MINUTES', 40),

    'country'  => 'NG',
    'currency' => 'NGN',
];
