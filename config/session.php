<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Session Config
// ─────────────────────────────────────────

return [
    // Drivers: 'file' (db driver future-ready)
    'driver'   => env('SESSION_DRIVER', 'file'),

    // Lifetime in minutes
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // Storage path for file-based sessions
    'path'     => FLUX_ROOT . '/storage/sessions',

    // Cookie security settings
    'secure'   => env('APP_ENV') === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
];
