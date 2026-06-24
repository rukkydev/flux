<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Middleware Engine
//  File: core/middleware.php
//
//  Changes from v1.0:
//    - Added per-request deduplication (middleware runs once)
//    - Added middleware_reset() for testing
//    - Built-in csrf, api.auth, throttle unchanged
// ═══════════════════════════════════════════════════════

$_FLUX_MIDDLEWARE         = [];
$_FLUX_MIDDLEWARE_APPLIED = [];   // tracks what has run this request

// ─────────────────────────────────────────────────────
//  Registration
// ─────────────────────────────────────────────────────

/**
 * Register a named middleware handler.
 *
 * middleware_register('auth', function() { ... });
 */
function middleware_register(string $name, callable $handler): void
{
    global $_FLUX_MIDDLEWARE;
    $_FLUX_MIDDLEWARE[$name] = $handler;
}

// ─────────────────────────────────────────────────────
//  Execution — with deduplication
// ─────────────────────────────────────────────────────

/**
 * Run one or more middleware by name.
 * Each middleware runs AT MOST ONCE per request, regardless of
 * how many times middleware() is called with the same name.
 *
 * middleware('auth');
 * middleware(['auth', 'verified']);
 */
function middleware(string|array $names): void
{
    global $_FLUX_MIDDLEWARE, $_FLUX_MIDDLEWARE_APPLIED;

    $names = is_array($names) ? $names : [$names];

    foreach ($names as $name) {
        // ── Deduplication: skip if already applied this request ──
        if (isset($_FLUX_MIDDLEWARE_APPLIED[$name])) {
            // Uncomment for debug logging:
            // log_debug("Middleware [{$name}] already applied — skipping duplicate.");
            continue;
        }

        if (!isset($_FLUX_MIDDLEWARE[$name])) {
            log_warning("Middleware not registered: [{$name}]");
            continue;
        }

        // Mark as applied BEFORE running so recursive calls are safe
        $_FLUX_MIDDLEWARE_APPLIED[$name] = true;

        ($_FLUX_MIDDLEWARE[$name])();
    }
}

// ─────────────────────────────────────────────────────
//  Introspection
// ─────────────────────────────────────────────────────

/**
 * Check if a middleware is registered.
 */
function middleware_exists(string $name): bool
{
    global $_FLUX_MIDDLEWARE;
    return isset($_FLUX_MIDDLEWARE[$name]);
}

/**
 * Get all registered middleware names.
 */
function middleware_list(): array
{
    global $_FLUX_MIDDLEWARE;
    return array_keys($_FLUX_MIDDLEWARE);
}

/**
 * Get all middleware that have already been applied this request.
 */
function middleware_applied(): array
{
    global $_FLUX_MIDDLEWARE_APPLIED;
    return array_keys($_FLUX_MIDDLEWARE_APPLIED);
}

/**
 * Check if a specific middleware has already run this request.
 */
function middleware_has_run(string $name): bool
{
    global $_FLUX_MIDDLEWARE_APPLIED;
    return isset($_FLUX_MIDDLEWARE_APPLIED[$name]);
}

/**
 * Reset applied middleware tracking.
 * Useful for testing only — do not call in production request flow.
 */
function middleware_reset(): void
{
    global $_FLUX_MIDDLEWARE_APPLIED;
    $_FLUX_MIDDLEWARE_APPLIED = [];
}

// ─────────────────────────────────────────────────────
//  Built-in framework middleware
// ─────────────────────────────────────────────────────

middleware_register('csrf', function () {
    csrf_verify();
});

middleware_register('api.auth', function () {
    $token = request_bearer();

    if (!$token) {
        response_unauthorized('API token required.');
    }

    $hashedToken = hash('sha256', $token);
    $record      = db_find_where('api_tokens', ['token' => $hashedToken]);

    if (!$record || !hash_equals($record['token'], $hashedToken)) {
        response_unauthorized('Invalid API token.');
    }

    if (!empty($record['revoked_at'])) {
        response_unauthorized('API token has been revoked.');
    }

    if (!empty($record['expires_at']) && strtotime($record['expires_at']) < time()) {
        response_unauthorized('API token has expired.');
    }

    // Update last_used at most once per minute
    $lastUsed = strtotime($record['last_used'] ?? '2000-01-01');
    if (time() - $lastUsed > 60) {
        db_update('api_tokens', $record['id'], ['last_used' => date('Y-m-d H:i:s')]);
    }

    $_SERVER['_api_user_id'] = $record['user_id'];
});

middleware_register('throttle', function () {
    $key   = 'throttle_' . md5(request_ip() . request_path());
    $hits  = (int) cache_get($key, 0);
    $limit = (int) config('app.throttle_limit', 60);

    if ($hits >= $limit) {
        if (request_is_api()) {
            response_error('Too many requests.', 429);
        }
        abort(429, 'Too many requests.');
    }

    cache_set($key, $hits + 1, 60);
});
