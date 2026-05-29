<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Routing Engine (Phase 2)
//
//  Priority order:
//    1. Manual route overrides  (routes/web.php, routes/api.php)
//    2. File-based static pages (/pages/...)
//    3. File-based dynamic pages with [param] segments
//    4. 404
//
//  Dynamic segment syntax: [param]
//  Example: pages/user/[id]/edit.php  →  /user/42/edit
//           $route_params['id'] === '42'
// ═══════════════════════════════════════════════════════

// ── Global state ──────────────────────────────────────
$_FLUX_ROUTES       = [];          // manual overrides
$_FLUX_ROUTE_PARAMS = [];          // resolved [param] values
$_FLUX_ROUTE_GROUPS = [];          // group middleware stack
$_FLUX_CURRENT_ROUTE = null;       // matched route info

// ─────────────────────────────────────────────────────
//  PUBLIC API
// ─────────────────────────────────────────────────────

/**
 * Register a manual route override.
 *
 * route('/login',        'pages/auth/login.php', ['guest']);
 * route('/user/[id]',    'pages/user/show.php',  ['auth']);
 */
function route(string $uri, string $file, array $middleware = []): void
{
    global $_FLUX_ROUTES, $_FLUX_ROUTE_GROUPS;

    // Merge any active group middleware
    $groupMiddleware = array_merge(...array_column($_FLUX_ROUTE_GROUPS, 'middleware'));

    $_FLUX_ROUTES[] = [
        'uri'        => '/' . trim($uri, '/'),
        'file'       => $file,
        'middleware' => array_merge($groupMiddleware, $middleware),
        'pattern'    => router_uri_to_pattern($uri),
        'params'     => router_extract_param_names($uri),
    ];
}

/**
 * Open a route group with shared middleware and/or prefix.
 *
 * route_group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function() {
 *     route('/dashboard', 'pages/admin/dashboard.php');
 * });
 */
function route_group(array $options, callable $callback): void
{
    global $_FLUX_ROUTE_GROUPS;

    $_FLUX_ROUTE_GROUPS[] = [
        'prefix'     => $options['prefix'] ?? '',
        'middleware' => (array) ($options['middleware'] ?? []),
    ];

    $callback();

    array_pop($_FLUX_ROUTE_GROUPS);
}

/**
 * Get a resolved route parameter by name.
 * Available inside any page file after dispatch.
 */
function route_param(string $key, mixed $default = null): mixed
{
    global $_FLUX_ROUTE_PARAMS;
    return $_FLUX_ROUTE_PARAMS[$key] ?? $default;
}

/**
 * Get all resolved route parameters.
 */
function route_params(): array
{
    global $_FLUX_ROUTE_PARAMS;
    return $_FLUX_ROUTE_PARAMS;
}

/**
 * Get info about the currently matched route.
 */
function current_route(): ?array
{
    global $_FLUX_CURRENT_ROUTE;
    return $_FLUX_CURRENT_ROUTE;
}

// ─────────────────────────────────────────────────────
//  DISPATCH
// ─────────────────────────────────────────────────────

/**
 * Main dispatch entry point — called from index.php.
 */
function router_dispatch(): void
{
    $path = '/' . trim(request_path(), '/');

    // Strip /api prefix for API routing
    $isApi = str_starts_with($path, '/api');

    // Try cached route table first
    if (router_dispatch_from_cache($path)) {
        return;
    }

    // 1. Manual overrides
    if (router_dispatch_manual($path)) {
        return;
    }

    // 2. Static file-based
    if (router_dispatch_static($path, $isApi)) {
        return;
    }

    // 3. Dynamic file-based
    if (router_dispatch_dynamic($path, $isApi)) {
        return;
    }

    // 4. Not found
    abort(404);
}

// ─────────────────────────────────────────────────────
//  DISPATCH STRATEGIES
// ─────────────────────────────────────────────────────

function router_dispatch_manual(string $path): bool
{
    global $_FLUX_ROUTES;

    foreach ($_FLUX_ROUTES as $route) {
        if (!preg_match($route['pattern'], $path, $matches)) {
            continue;
        }

        // Extract named params
        $params = [];
        foreach ($route['params'] as $name) {
            $params[$name] = $matches[$name] ?? null;
        }

        router_execute($route['file'], $route['middleware'], $params, $route);
        return true;
    }

    return false;
}

function router_dispatch_static(string $path, bool $isApi): bool
{
    $base       = $isApi ? 'api' : 'pages';
    $candidates = router_static_candidates($path, $isApi);

    foreach ($candidates as $candidate) {
        $file = base_path($base . '/' . $candidate . '.php');

        if (file_exists($file)) {
            // Load middleware file adjacent to page if exists
            $middleware = router_load_route_middleware($file);
            router_execute($base . '/' . $candidate . '.php', $middleware, []);
            return true;
        }
    }

    return false;
}

function router_dispatch_dynamic(string $path, bool $isApi): bool
{
    $base      = $isApi ? 'api' : 'pages';
    $baseDir   = base_path($base);
    $pathParts = array_filter(explode('/', trim($path, '/')));

    $result = router_scan_dynamic($baseDir, array_values($pathParts), '', $base);

    if ($result === null) {
        return false;
    }

    [$file, $params] = $result;
    $middleware = router_load_route_middleware(base_path($file));
    router_execute($file, $middleware, $params);
    return true;
}

function router_dispatch_from_cache(string $path): bool
{
    if (!router_cache_enabled()) {
        return false;
    }

    $cache = router_cache_load();

    if (!isset($cache[$path])) {
        return false;
    }

    $entry = $cache[$path];

    if (!file_exists(base_path($entry['file']))) {
        return false;
    }

    router_execute($entry['file'], $entry['middleware'] ?? [], $entry['params'] ?? []);
    return true;
}

// ─────────────────────────────────────────────────────
//  EXECUTION
// ─────────────────────────────────────────────────────

function router_execute(string $file, array $middleware, array $params, ?array $routeInfo = null): void
{
    global $_FLUX_ROUTE_PARAMS, $_FLUX_CURRENT_ROUTE;

    $_FLUX_ROUTE_PARAMS  = $params;
    $_FLUX_CURRENT_ROUTE = $routeInfo ?? ['file' => $file, 'middleware' => $middleware, 'params' => $params];

    // Run middleware
    if (!empty($middleware)) {
        middleware($middleware);
    }

    if (!file_exists(base_path($file))) {
        abort(500, "Page file not found: {$file}");
    }

    // Hand off to renderer pipeline (handles layout, sections, stacks)
    renderer_render($file, $params);
}

// ─────────────────────────────────────────────────────
//  DYNAMIC ROUTE SCANNER
// ─────────────────────────────────────────────────────

/**
 * Recursively scan the pages/api directory tree to match
 * dynamic [param] segments against the current path parts.
 *
 * Returns [relativeFilePath, paramsArray] or null.
 */
function router_scan_dynamic(string $dir, array $parts, string $relPath, string $base): ?array
{
    if (empty($parts)) {
        // Look for index.php at this level
        $index = $dir . '/index.php';
        if (file_exists($index)) {
            return [$base . $relPath . '/index.php', []];
        }
        return null;
    }

    $segment    = $parts[0];
    $remaining  = array_slice($parts, 1);

    // 1. Exact match directory
    $exactDir = $dir . '/' . $segment;
    if (is_dir($exactDir)) {
        $result = router_scan_dynamic($exactDir, $remaining, $relPath . '/' . $segment, $base);
        if ($result !== null) {
            return $result;
        }
    }

    // 2. Exact match file (leaf)
    if (empty($remaining)) {
        $exactFile = $dir . '/' . $segment . '.php';
        if (file_exists($exactFile)) {
            return [$base . $relPath . '/' . $segment . '.php', []];
        }
    }

    // 3. Dynamic [param] directory
    foreach (glob($dir . '/[[]*/') ?: [] as $dynDir) {
        $paramName = basename(rtrim($dynDir, '/'));
        // Must be [something]
        if (!preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $paramName, $m)) {
            continue;
        }

        $key    = $m[1];
        $result = router_scan_dynamic(rtrim($dynDir, '/'), $remaining, $relPath . '/' . $paramName, $base);

        if ($result !== null) {
            [$file, $params] = $result;
            return [$file, array_merge([$key => $segment], $params)];
        }
    }

    // 4. Dynamic [param].php file (leaf)
    if (empty($remaining)) {
        foreach (glob($dir . '/[[]*.php') ?: [] as $dynFile) {
            $fname = basename($dynFile, '.php');
            if (!preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $fname, $m)) {
                continue;
            }
            return [$base . $relPath . '/' . $fname . '.php', [$m[1] => $segment]];
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────

/**
 * Build static candidate paths for a URI (no dynamic segments).
 */
function router_static_candidates(string $path, bool $isApi): array
{
    $clean = trim(str_replace('/api', '', $path), '/');

    if ($clean === '') {
        return ['index'];
    }

    return [
        $clean,
        $clean . '/index',
    ];
}

/**
 * Convert a URI with [param] segments into a named-capture regex.
 * /user/[id]/edit  →  #^/user/(?P<id>[^/]+)/edit$#
 */
function router_uri_to_pattern(string $uri): string
{
    $escaped = preg_quote('/' . trim($uri, '/'), '#');
    $pattern = preg_replace('/\\\\\[([a-zA-Z_][a-zA-Z0-9_]*)\\\\\]/', '(?P<$1>[^/]+)', $escaped);
    return '#^' . $pattern . '$#';
}

/**
 * Extract param names from a URI string.
 * /user/[id]/post/[slug]  →  ['id', 'slug']
 */
function router_extract_param_names(string $uri): array
{
    preg_match_all('/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/', $uri, $matches);
    return $matches[1];
}

/**
 * Check for a _middleware.php file next to a page and return its value.
 * Allows per-directory middleware assignment without manual route() calls.
 */
function router_load_route_middleware(string $filePath): array
{
    $dir        = dirname($filePath);
    $candidates = [
        $dir . '/_middleware.php',
        dirname($dir) . '/_middleware.php',
    ];

    foreach ($candidates as $mFile) {
        if (file_exists($mFile)) {
            $result = require $mFile;
            if (is_array($result)) {
                return $result;
            }
        }
    }

    return [];
}

// ─────────────────────────────────────────────────────
//  ROUTE CACHE
// ─────────────────────────────────────────────────────

function router_cache_enabled(): bool
{
    return config('app.env') === 'production' && file_exists(router_cache_path());
}

function router_cache_path(): string
{
    return storage_path('cache/routes.cache.php');
}

function router_cache_load(): array
{
    $path = router_cache_path();
    if (!file_exists($path)) {
        return [];
    }
    return require $path;
}

/**
 * Walk the pages/ and api/ trees and pre-build a flat route map.
 * Called by:  flux route:cache
 */
function router_cache_build(): array
{
    $map   = [];
    $bases = ['pages', 'api'];

    foreach ($bases as $base) {
        $dir = base_path($base);
        if (!is_dir($dir)) {
            continue;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $rel  = str_replace($dir, '', $file->getPathname());
            $rel  = str_replace('\\', '/', $rel);
            $uri  = router_file_to_uri($rel, $base === 'api');

            if ($uri === null) {
                continue;
            }

            $map[$uri] = [
                'file'       => $base . $rel,
                'middleware' => [],
                'params'     => router_extract_param_names($uri),
            ];
        }
    }

    return $map;
}

function router_cache_write(array $map): void
{
    $path    = router_cache_path();
    $dir     = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $export  = var_export($map, true);
    $content = "<?php\n// FluxPHP Route Cache — generated " . date('Y-m-d H:i:s') . "\nreturn {$export};\n";

    file_put_contents($path, $content, LOCK_EX);
}

function router_cache_clear(): void
{
    $path = router_cache_path();
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Convert a relative file path to a URI.
 * /user/[id]/edit.php  →  /user/[id]/edit
 * /index.php           →  /
 */
function router_file_to_uri(string $rel, bool $isApi): ?string
{
    // Skip private/error files
    if (str_contains($rel, '_middleware') || str_contains($rel, 'errors/')) {
        return null;
    }

    $uri = rtrim($rel, '.php');
    $uri = preg_replace('/\.php$/', '', $uri);

    // index  →  parent path
    if (str_ends_with($uri, '/index')) {
        $uri = substr($uri, 0, -6) ?: '/';
    }

    if ($isApi) {
        $uri = '/api' . $uri;
    }

    return $uri ?: '/';
}

// ─────────────────────────────────────────────────────
//  ROUTE LISTING  (for flux route:list)
// ─────────────────────────────────────────────────────

/**
 * Collect all discoverable routes for CLI inspection.
 */
function router_list(): array
{
    global $_FLUX_ROUTES;

    $routes = [];

    // Manual overrides
    foreach ($_FLUX_ROUTES as $route) {
        $routes[] = [
            'uri'        => $route['uri'],
            'file'       => $route['file'],
            'middleware' => implode(', ', $route['middleware']),
            'type'       => 'manual',
        ];
    }

    // File-based discovery
    $bases = ['pages' => '', 'api' => '/api'];

    foreach ($bases as $base => $prefix) {
        $dir = base_path($base);

        if (!is_dir($dir)) {
            continue;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace($dir, '', $file->getPathname());
            $rel = str_replace('\\', '/', $rel);

            if (str_contains($rel, '_middleware') || str_contains($rel, 'errors/')) {
                continue;
            }

            $uri = router_file_to_uri($rel, $base === 'api');

            if ($uri === null) {
                continue;
            }

            // Skip if already in manual overrides
            $already = array_filter($routes, fn($r) => $r['uri'] === $uri);
            if (!empty($already)) {
                continue;
            }

            $mware = router_load_route_middleware($file->getPathname());

            $routes[] = [
                'uri'        => $uri,
                'file'       => $base . $rel,
                'middleware' => implode(', ', $mware),
                'type'       => 'auto',
            ];
        }
    }

    usort($routes, fn($a, $b) => strcmp($a['uri'], $b['uri']));

    return $routes;
}
