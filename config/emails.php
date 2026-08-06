<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Addresses
    |--------------------------------------------------------------------------
    |
    | KidsFlairr mailbox routing:
    |
    |   noreply  — Automated transactional emails (OTPs, password resets, confirmations)
    |   orders   — Customer-facing order updates (status, ready for pickup, delivery)
    |   stations — Internal station operations (payment verification, returns, alerts)
    |   support  — Customer enquiries, complaints, returns (Reply-To on customer emails)
    |   accounts — Financial matters (settlements, refunds, payouts, invoices)
    |
    */

    'noreply'  => env('MAIL_NOREPLY_ADDRESS', 'noreply@kidsflairr.com.ng'),
    'orders'   => env('MAIL_ORDERS_ADDRESS', 'orders@kidsflairr.com.ng'),
    'stations' => env('MAIL_STATIONS_ADDRESS', 'stations@kidsflairr.com.ng'),
    'support'  => env('MAIL_SUPPORT_ADDRESS', 'support@kidsflairr.com.ng'),
    'accounts' => env('MAIL_ACCOUNTS_ADDRESS', 'accounts@kidsflairr.com.ng'),
];
