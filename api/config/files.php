<?php

return [
    // Disk for private uploads: `local` (storage/app/private) in dev, `s3` (MinIO/ArvanCloud) in prod.
    'disk' => env('FILES_DISK', 'local'),

    // Lifetime of signed download URLs handed to clients.
    'url_ttl_minutes' => (int) env('FILES_URL_TTL_MINUTES', 10),

    'max_document_kb' => 10240,
    'max_photo_kb' => 5120,
];
