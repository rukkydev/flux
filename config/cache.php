<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Cache Config
// ─────────────────────────────────────────

return [
    // Drivers: 'file', 'array'
    'driver' => env('CACHE_DRIVER', 'file'),

    // File cache storage path
    'path'   => FLUX_ROOT . '/storage/cache',

    // Default TTL in seconds (3600 = 1 hour)
    'ttl'    => 3600,
];
