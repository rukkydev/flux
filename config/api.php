<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — API Config
// ─────────────────────────────────────────

return [
    // Default API version
    'default_version' => 'v1',

    // CORS allowed origins
    // Use ['*'] for open APIs or list specific origins:
    // ['https://app.example.com', 'https://admin.example.com']
    'cors_origins' => ['*'],

    // Default rate limit (requests per minute per IP)
    'rate_limit' => 60,

    // API token expiry in minutes (0 = never expires)
    'token_expiry' => 0,
];
