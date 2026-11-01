<?php

return [
    'length' => 5,

    // Code validity (FR-1.1: 2-minute expiry).
    'ttl_seconds' => 120,

    // Minimum wait before another code can be requested for the same number.
    'resend_after_seconds' => 60,

    // Wrong guesses allowed per code before it is burned.
    'max_attempts' => 5,

    // Request caps (per mobile and per IP) within the window.
    'max_requests_per_mobile' => 5,
    'max_requests_per_ip' => 30,
    'request_window_seconds' => 3600,

    // Staff 2FA challenge lifetime between password and OTP steps.
    'staff_challenge_ttl_seconds' => 300,
];
