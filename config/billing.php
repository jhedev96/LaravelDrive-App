<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing (D18) — storage plan subscriptions
    |--------------------------------------------------------------------------
    |
    | The billing layer starts DISABLED so the upgrade-in-place audience
    | keeps the historical unlimited storage until the owner flips the
    | switch. Every user is assigned the default plan at registration.
    |
    */

    'enabled' => env('BILLING_ENABLED', false),

    'default_plan_slug' => 'free',

    // seeded once by PlanTableSeeder; editable values live in the database
    'free' => [
        'name' => 'Free',
        'max_storage' => env('FREE_PLAN_MAX_STORAGE', 2 * 1024 * 1024 * 1024), // 2 GiB
    ],

    'vip' => [
        'name' => 'VIP',
        'max_storage' => env('VIP_PLAN_MAX_STORAGE', 50 * 1024 * 1024 * 1024), // 50 GiB
        'price' => env('VIP_PLAN_PRICE', 0),
        'currency' => env('VIP_PLAN_CURRENCY', 'IDR'),
        'duration_days' => env('VIP_PLAN_DURATION_DAYS', 30),
    ],

    // payment rail (D18): midtrans Snap QRIS primary, xendit fallback
    'gateway' => env('BILLING_GATEWAY', 'midtrans'),

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

];
