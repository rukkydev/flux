<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Middleware Engine
//
//  Middleware is registered by name via middleware_register().
//  Modules register their own middleware in middleware.php.
//  Pages apply middleware via: middleware('auth');
//  Directories apply via: _middleware.php returning ['auth']
// ═══════════════════════════════════════════════════════

$_FLUX_MIDDLEWARE = [];

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

/**
 * Run one or more middleware by name.
 *
 * middleware('auth');
 * middleware(['auth', 'verified']);
 */
function middleware(string|array $names): void
{
    global $_FLUX_MIDDLEWARE;

    $names = is_array($names) ? $names : [$names];

    foreach ($names as $name) {
        if (!isset($_FLUX_MIDDLEWARE[$name])) {
            log_warning("Middleware not registered: [{$name}]");
            continue;
        }
        ($_FLUX_MIDDLEWARE[$name])();
    }
}

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

// ── Built-in framework middleware ─────────────────────
// (Auth guards are registered by the auth module)

middleware_register('csrf', function () {
    csrf_verify();
});

middleware_register('api.auth', function () {
    $token = request_bearer();

    if (!$token) {
        response_unauthorized('API token required.');
    }

    $record = db_find_where('api_tokens', ['token' => hash('sha256', $token)]);

    if (!$record) {
        response_unauthorized('Invalid API token.');
    }

    if (!empty($record['expires_at']) && strtotime($record['expires_at']) < time()) {
        response_unauthorized('API token expired.');
    }

    // Update last used
    db_update('api_tokens', $record['id'], ['last_used' => date('Y-m-d H:i:s')]);

    // Make user ID available
    $_SERVER['_api_user_id'] = $record['user_id'];
});

middleware_register('throttle', function () {
    // Basic IP-based rate limiting via cache
    $key      = 'throttle_' . md5(request_ip() . request_path());
    $hits     = (int) cache_get($key, 0);
    $limit    = (int) config('app.throttle_limit', 60);

    if ($hits >= $limit) {
        if (request_is_api()) {
            response_error('Too many requests.', 429);
        }
        abort(429, 'Too many requests.');
    }

    cache_set($key, $hits + 1, 60);
});
