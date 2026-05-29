<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — API Engine (Phase 8)
//
//  Features:
//    - Method routing (GET/POST/PUT/PATCH/DELETE)
//    - Rate limiting per IP + endpoint
//    - API versioning
//    - Request validation helpers
//    - Consistent envelope responses
//    - CORS support
//    - API token authentication
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────
//  METHOD ROUTING
// ─────────────────────────────────────────────────────

/**
 * Register handlers per HTTP method and dispatch.
 *
 * api_dispatch([
 *     'GET'    => fn() => response_success(user_all()),
 *     'POST'   => fn() => response_created(user_create(request_all())),
 *     'DELETE' => fn() => response_no_content(),
 * ]);
 */
function api_dispatch(array $handlers): never
{
    $method = request_method();

    // Support OPTIONS preflight
    if ($method === 'OPTIONS') {
        api_cors_headers();
        http_response_code(204);
        exit;
    }

    api_cors_headers();

    if (!isset($handlers[$method])) {
        $allowed = implode(', ', array_keys($handlers));
        header("Allow: {$allowed}");
        response_error("Method {$method} not allowed.", 405);
    }

    ($handlers[$method])();
    exit;
}

/**
 * Only allow specific methods, abort otherwise.
 *
 * api_only('GET', 'POST');
 */
function api_only(string ...$methods): void
{
    $method = request_method();

    if (!in_array($method, $methods, true)) {
        $allowed = implode(', ', $methods);
        header("Allow: {$allowed}");
        response_error("Method {$method} not allowed.", 405);
    }
}

// ─────────────────────────────────────────────────────
//  CORS
// ─────────────────────────────────────────────────────

function api_cors_headers(): void
{
    $origins = config('api.cors_origins', ['*']);
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '*';

    if (in_array('*', $origins, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif (in_array($origin, $origins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// ─────────────────────────────────────────────────────
//  RATE LIMITING
// ─────────────────────────────────────────────────────

/**
 * Apply rate limiting to the current API endpoint.
 *
 * api_rate_limit(60);           // 60 requests per minute
 * api_rate_limit(10, 30);       // 10 requests per 30 seconds
 */
function api_rate_limit(int $maxRequests = 60, int $decaySeconds = 60): void
{
    $key     = 'rate_limit_' . md5(request_ip() . request_path());
    $hits    = (int) cache_get($key, 0);
    $remaining = max(0, $maxRequests - $hits - 1);

    header("X-RateLimit-Limit: {$maxRequests}");
    header("X-RateLimit-Remaining: {$remaining}");

    if ($hits >= $maxRequests) {
        header("Retry-After: {$decaySeconds}");
        response_error('Too many requests. Please slow down.', 429);
    }

    cache_set($key, $hits + 1, $decaySeconds);
}

// ─────────────────────────────────────────────────────
//  VERSIONING
// ─────────────────────────────────────────────────────

/**
 * Get the API version from the request path.
 * /api/v1/users → 'v1'
 * /api/users    → 'v1' (default)
 */
function api_version(): string
{
    $path = request_path();
    if (preg_match('#/api/(v\d+)/#', $path, $m)) {
        return $m[1];
    }
    return config('api.default_version', 'v1');
}

/**
 * Abort if the API version doesn't match.
 *
 * api_require_version('v2');
 */
function api_require_version(string $version): void
{
    if (api_version() !== $version) {
        response_error("This endpoint requires API version {$version}.", 400);
    }
}

// ─────────────────────────────────────────────────────
//  REQUEST HELPERS
// ─────────────────────────────────────────────────────

/**
 * Validate API request data. Returns validated array or sends 422.
 */
function api_validate(array $rules): array
{
    $data   = request_all();
    $errors = validation_run($data, $rules);

    if (!empty($errors)) {
        response_validation_error($errors);
    }

    return arr_only($data, array_keys($rules));
}

/**
 * Get authenticated API user ID from token.
 */
function api_user_id(): ?int
{
    $id = $_SERVER['_api_user_id'] ?? null;
    return $id ? (int) $id : null;
}

/**
 * Get authenticated API user record.
 */
function api_user(): ?array
{
    $id = api_user_id();
    return $id ? user_find($id) : null;
}

/**
 * Require API authentication — abort 401 if missing.
 */
function api_auth_required(): void
{
    middleware('api.auth');
}

// ─────────────────────────────────────────────────────
//  RESPONSE HELPERS (API-specific)
// ─────────────────────────────────────────────────────

/**
 * Paginated response envelope.
 */
function response_paginated(array $paginator, string $message = 'OK'): never
{
    response_json([
        'success' => true,
        'message' => $message,
        'data'    => $paginator['data'],
        'meta'    => [
            'total'        => $paginator['total'],
            'per_page'     => $paginator['per_page'],
            'current_page' => $paginator['current_page'],
            'last_page'    => $paginator['last_page'],
            'from'         => $paginator['from'],
            'to'           => $paginator['to'],
            'has_more'     => $paginator['has_more'] ?? false,
        ],
    ]);
}

/**
 * Send a method not allowed response.
 */
function response_method_not_allowed(array $allowed = []): never
{
    if (!empty($allowed)) {
        header('Allow: ' . implode(', ', $allowed));
    }
    response_error('Method not allowed.', 405);
}

/**
 * Send a rate limit exceeded response.
 */
function response_too_many_requests(string $message = 'Too many requests.'): never
{
    response_error($message, 429);
}
