<?php

return [
    // First admin account, created by `php artisan db:seed` only when both are set.
    'admin_mobile' => env('SEED_ADMIN_MOBILE'),
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
];
