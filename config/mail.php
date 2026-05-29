<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Mail Config
//  Default: Mailhog (local testing)
//  Mailhog UI: http://localhost:8025
// ─────────────────────────────────────────

return [
    'driver'     => env('MAIL_DRIVER', 'smtp'),
    'host'       => env('MAIL_HOST', '127.0.0.1'),
    'port'       => (int) env('MAIL_PORT', 1025),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', ''),  // Mailhog = no encryption
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@fluxphp.dev'),
        'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'FluxPHP')),
    ],
];
