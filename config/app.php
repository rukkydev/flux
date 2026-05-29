<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — App Config
// ─────────────────────────────────────────

return [
    'name'     => env('APP_NAME', 'FluxPHP'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'key'      => env('APP_KEY', ''),
    'version'  => '1.0.0',
    'timezone' => 'UTC',
    'locale'   => 'en',
    'theme'    => 'default',

    // Default rate limit for throttle middleware (requests per minute)
    'throttle_limit' => 60,
];
