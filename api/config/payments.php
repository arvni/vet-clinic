<?php

return [
    /*
    | fake: approves everything (local/testing, refused in production).
    | zarinpal: live gateway. Add more drivers behind PaymentGateway.
    */
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'base_url' => env('ZARINPAL_BASE_URL', 'https://payment.zarinpal.com'),
    ],
];
