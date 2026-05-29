<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Login Throttling
// ─────────────────────────────────────────

define('AUTH_MAX_ATTEMPTS', 5);
define('AUTH_DECAY_SECONDS', 60);

function auth_throttle_key(string $email): string
{
    return 'throttle_login_' . md5(strtolower($email) . request_ip());
}

function auth_throttle_record(string $email): void
{
    $key      = auth_throttle_key($email);
    $attempts = (int) cache_get($key, 0);
    cache_set($key, $attempts + 1, AUTH_DECAY_SECONDS);
}

function auth_throttle_clear(string $email): void
{
    cache_forget(auth_throttle_key($email));
}

function auth_throttle_exceeded(string $email): bool
{
    return (int) cache_get(auth_throttle_key($email), 0) >= AUTH_MAX_ATTEMPTS;
}

function auth_throttle_remaining(string $email): int
{
    $attempts = (int) cache_get(auth_throttle_key($email), 0);
    return max(0, AUTH_MAX_ATTEMPTS - $attempts);
}
