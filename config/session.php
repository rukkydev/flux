<?php

// ═══════════════════════════════════════════════════════
//  FluxPHP — Session Configuration
//  File: config/session.php
//
//  Set SESSION_DRIVER in .env to switch drivers:
//    file      — default, files in storage/sessions/
//    database  — requires sessions table (see migration)
//    cookie    — encrypted cookie, no server storage
// ═══════════════════════════════════════════════════════

return [

    // ── Driver ────────────────────────────────────────
    // file | database | cookie
    'driver' => env('SESSION_DRIVER', 'file'),

    // ── Session name (cookie name) ────────────────────
    'name' => env('SESSION_NAME', 'FLUX_SESSION'),

    // ── Lifetime in minutes ───────────────────────────
    // Session expires after this many minutes of inactivity
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // ── File driver: storage path ─────────────────────
    'path' => FLUX_ROOT . '/storage/sessions',

    // ── Database driver: table name ───────────────────
    'table' => env('SESSION_TABLE', 'sessions'),

    // ── Cookie security settings ──────────────────────
    // secure:   HTTPS only (set true in production)
    // httponly: JS cannot read the cookie (always true)
    // samesite: Lax = safe default, Strict = max security
    'secure'   => env('SESSION_SECURE',   env('APP_ENV') === 'production'),
    'httponly' => true,
    'samesite' => env('SESSION_SAMESITE', 'Lax'),
    'domain'   => env('SESSION_DOMAIN',   ''),

];
