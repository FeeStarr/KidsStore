<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paystack Integration
    |--------------------------------------------------------------------------
    | Set your keys in .env:
    |   PAYSTACK_PUBLIC_KEY=pk_test_xxx
    |   PAYSTACK_SECRET_KEY=sk_test_xxx
    |   PAYSTACK_WEBHOOK_SECRET=whsec_xxx
    |
    | Webhook:
    |   Set https://yourdomain.com/paystack/webhook in the Paystack dashboard
    |   under Settings > Webhook URL.
    */

    'secret_key'  => env('PAYSTACK_SECRET_KEY', ''),
    'public_key'  => env('PAYSTACK_PUBLIC_KEY', ''),
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET', ''),

    'base_url' => 'https://api.paystack.co',

    /*
     * Default expiry for a dedicated virtual account in minutes.
     */
    'expire_minutes' => (int) env('PAYSTACK_EXPIRE_MINUTES', 45),

    'currency' => 'NGN',
];
