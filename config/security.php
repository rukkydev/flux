<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Security Config
// ─────────────────────────────────────────

return [

    // ── Trusted Proxies ───────────────────
    // Only trust X-Forwarded-For from these IPs
    // Add your load balancer / CDN IP here
    'trusted_proxies' => array_filter(
        explode(',', env('TRUSTED_PROXIES', '127.0.0.1'))
    ),

    // ── Content Security Policy ───────────
    // Add CDN domains here — no code changes needed
    'csp_enabled' => env('CSP_ENABLED', true),

    'csp' => [
        'default' => ["'self'"],
        'scripts' => [
            "'self'",
            'https://cdn.jsdelivr.net',
            'https://unpkg.com',
        ],
        'styles' => [
            "'self'",
            'https://cdn.jsdelivr.net',
            'https://fonts.googleapis.com',
        ],
        'fonts' => [
            "'self'",
            'https://fonts.gstatic.com',
            'https://cdn.jsdelivr.net',
        ],
        'images' => [
            "'self'",
            'data:',
            'https:',
        ],
        'connect' => [
            "'self'",
        ],
        'frames'  => ["'none'"],
        'objects' => ["'none'"],
        // Add unsafe-inline automatically in debug mode
        'unsafe_inline_in_debug' => true,
    ],

    // ── Upload Security ───────────────────
    'upload' => [
        'max_size'   => 5 * 1024 * 1024, // 5MB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
        ],
        // Never allow these regardless of config
        'blocked_extensions' => [
            'php', 'php3', 'php4', 'php5', 'phtml',
            'exe', 'sh', 'bat', 'cmd', 'js', 'jsp',
            'asp', 'aspx', 'htaccess', 'htpasswd',
        ],
    ],

    // ── Auth Throttle ─────────────────────
    'throttle' => [
        'max_attempts'  => (int) env('AUTH_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('AUTH_DECAY_SECONDS', 60),
    ],

    // ── Session ───────────────────────────
    'session' => [
        'validate_user_agent' => true,
        'validate_ip'         => false, // set true if no NAT/mobile users
    ],

];
