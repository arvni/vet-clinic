<?php

return [
    /*
    | fake: log only (local/testing). kavenegar: live provider.
    | Add more drivers behind App\Modules\Identity\Sms\SmsSender.
    */
    'driver' => env('SMS_DRIVER', 'fake'),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
    ],

    /*
    | Provider template names, one per language (FR-1.8: SMS follow the user's locale).
    */
    'templates' => [
        'otp' => [
            'fa' => env('SMS_TEMPLATE_OTP_FA', 'vet-otp-fa'),
            'en' => env('SMS_TEMPLATE_OTP_EN', 'vet-otp-en'),
        ],
        'emergency' => [
            'fa' => env('SMS_TEMPLATE_EMERGENCY_FA', 'vet-emergency-fa'),
            'en' => env('SMS_TEMPLATE_EMERGENCY_EN', 'vet-emergency-en'),
        ],
    ],

    /*
    | Staging safety: when set, only these E.164 numbers receive real SMS.
    */
    'allowlist' => array_filter(explode(',', (string) env('SMS_ALLOWLIST', ''))),
];
