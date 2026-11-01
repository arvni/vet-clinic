<?php

return [
    /*
    | fake: canned results (local/testing). neshan: live provider.
    | Add more drivers behind App\Modules\Locations\Maps\MapProvider.
    */
    'driver' => env('MAP_DRIVER', 'fake'),

    'neshan' => [
        'api_key' => env('NESHAN_API_KEY'),
        'base_url' => env('NESHAN_BASE_URL', 'https://api.neshan.org'),
    ],

    // Search results are biased towards this point (Bandar Abbas).
    'center' => ['lat' => 27.1832, 'lng' => 56.2666],
];
