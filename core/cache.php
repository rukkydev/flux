<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Cache Engine (Phase 9)
//
//  Drivers: file (default), array (per-request)
//  Sections:
//    1. Driver resolution
//    2. File cache
//    3. Array (in-memory) cache
//    4. Config cache
//    5. Query cache helpers
//    6. Cache tags (file-based)
//    7. General helpers
// ═══════════════════════════════════════════════════════

$_FLUX_ARRAY_CACHE = [];   // in-memory per-request store

// ─────────────────────────────────────────────────────
//  1. DRIVER RESOLUTION
// ─────────────────────────────────────────────────────

function cache_driver(): string
{
    return config('cache.driver', 'file');
}

// ─────────────────────────────────────────────────────
//  2. FILE CACHE
// ─────────────────────────────────────────────────────

function cache_file_path(string $key): string
{
    $hash = md5($key);
    $dir  = config('cache.path', storage_path('cache'));
    return $dir . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
}

function cache_file_set(string $key, mixed $value, int $ttl = 0): bool
{
    $ttl  = $ttl ?: (int) config('cache.ttl', 3600);
    $path = cache_file_path($key);
    $dir  = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload = serialize([
        'key'     => $key,
        'expires' => $ttl > 0 ? time() + $ttl : 0,
        'data'    => $value,
    ]);

    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cache_file_get(string $key, mixed $default = null): mixed
{
    $path = cache_file_path($key);

    if (!file_exists($path)) {
        return $default;
    }

    $payload = @unserialize(file_get_contents($path));

    if (!$payload || !array_key_exists('data', $payload)) {
        return $default;
    }

    // 0 = never expires
    if ($payload['expires'] > 0 && time() > $payload['expires']) {
        @unlink($path);
        return $default;
    }

    return $payload['data'];
}

function cache_file_has(string $key): bool
{
    return cache_file_get($key) !== null;
}

function cache_file_forget(string $key): bool
{
    $path = cache_file_path($key);
    return file_exists($path) && @unlink($path);
}

function cache_file_clear(): int
{
    $dir   = config('cache.path', storage_path('cache'));
    $count = 0;

    if (!is_dir($dir)) return 0;

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->getExtension() === 'cache') {
            @unlink($file->getPathname());
            $count++;
        }
    }

    return $count;
}

// ─────────────────────────────────────────────────────
//  3. ARRAY (IN-MEMORY) CACHE
// ─────────────────────────────────────────────────────

function cache_array_set(string $key, mixed $value, int $ttl = 0): bool
{
    global $_FLUX_ARRAY_CACHE;
    $_FLUX_ARRAY_CACHE[$key] = [
        'data'    => $value,
        'expires' => $ttl > 0 ? time() + $ttl : 0,
    ];
    return true;
}

function cache_array_get(string $key, mixed $default = null): mixed
{
    global $_FLUX_ARRAY_CACHE;

    if (!isset($_FLUX_ARRAY_CACHE[$key])) return $default;

    $entry = $_FLUX_ARRAY_CACHE[$key];

    if ($entry['expires'] > 0 && time() > $entry['expires']) {
        unset($_FLUX_ARRAY_CACHE[$key]);
        return $default;
    }

    return $entry['data'];
}

function cache_array_forget(string $key): bool
{
    global $_FLUX_ARRAY_CACHE;
    $had = isset($_FLUX_ARRAY_CACHE[$key]);
    unset($_FLUX_ARRAY_CACHE[$key]);
    return $had;
}

function cache_array_clear(): int
{
    global $_FLUX_ARRAY_CACHE;
    $count = count($_FLUX_ARRAY_CACHE);
    $_FLUX_ARRAY_CACHE = [];
    return $count;
}

// ─────────────────────────────────────────────────────
//  4. PUBLIC API (driver-aware)
// ─────────────────────────────────────────────────────

function cache_set(string $key, mixed $value, int $ttl = 0): bool
{
    return cache_driver() === 'array'
        ? cache_array_set($key, $value, $ttl)
        : cache_file_set($key, $value, $ttl);
}

function cache_get(string $key, mixed $default = null): mixed
{
    // Always check array cache first (fastest)
    $arrVal = cache_array_get($key);
    if ($arrVal !== null) return $arrVal;

    return cache_driver() === 'array'
        ? $default
        : cache_file_get($key, $default);
}

function cache_has(string $key): bool
{
    return cache_get($key) !== null;
}

function cache_forget(string $key): bool
{
    cache_array_forget($key);
    return cache_driver() === 'array' ? true : cache_file_forget($key);
}

function cache_remember(string $key, callable $callback, int $ttl = 0): mixed
{
    $value = cache_get($key);

    if ($value !== null) return $value;

    $value = $callback();
    cache_set($key, $value, $ttl);

    return $value;
}

/**
 * Remember forever (no expiry).
 */
function cache_remember_forever(string $key, callable $callback): mixed
{
    return cache_remember($key, $callback, 0);
}

function cache_clear_all(): int
{
    $count  = cache_array_clear();
    $count += cache_file_clear();
    config_cache_clear();
    return $count;
}

/**
 * Increment a numeric cache value.
 */
function cache_increment(string $key, int $amount = 1): int
{
    $value = (int) cache_get($key, 0) + $amount;
    cache_set($key, $value);
    return $value;
}

/**
 * Decrement a numeric cache value.
 */
function cache_decrement(string $key, int $amount = 1): int
{
    return cache_increment($key, -$amount);
}

// ─────────────────────────────────────────────────────
//  5. CONFIG CACHE
// ─────────────────────────────────────────────────────

function config_cache_path(): string
{
    return storage_path('cache/config.cache.php');
}

/**
 * Write all loaded config to a cache file.
 * Called by: flux cache:config
 */
function config_cache_write(): void
{
    global $_FLUX_CONFIG;

    $path    = config_cache_path();
    $dir     = dirname($path);

    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $export  = var_export($_FLUX_CONFIG, true);
    $content = "<?php\n// FluxPHP Config Cache — " . date('Y-m-d H:i:s') . "\nreturn {$export};\n";

    file_put_contents($path, $content, LOCK_EX);
}

/**
 * Load config from cache if available.
 * Returns true if cache was loaded.
 */
function config_cache_load(): bool
{
    global $_FLUX_CONFIG;

    $path = config_cache_path();

    if (!file_exists($path)) return false;

    $_FLUX_CONFIG = require $path;
    return true;
}

/**
 * Delete config cache file.
 */
function config_cache_clear(): void
{
    $path = config_cache_path();
    if (file_exists($path)) @unlink($path);
}

function config_cache_exists(): bool
{
    return file_exists(config_cache_path());
}

// ─────────────────────────────────────────────────────
//  6. QUERY CACHE HELPERS
// ─────────────────────────────────────────────────────

/**
 * Cache a DB query result.
 *
 * $users = cache_query('users.all', fn() => db_all('users'), 300);
 */
function cache_query(string $key, callable $query, int $ttl = 60): mixed
{
    return cache_remember('query.' . $key, $query, $ttl);
}

/**
 * Forget a cached query result.
 */
function cache_query_forget(string $key): bool
{
    return cache_forget('query.' . $key);
}

// ─────────────────────────────────────────────────────
//  7. CACHE TAGS (file-based, grouped invalidation)
// ─────────────────────────────────────────────────────

/**
 * Store a value with a tag for grouped invalidation.
 *
 * cache_tag_set('users', 'users.list', $users, 300);
 */
function cache_tag_set(string $tag, string $key, mixed $value, int $ttl = 0): bool
{
    // Track tagged keys in a meta entry
    $tagIndex = (array) cache_get("tag_index.{$tag}", []);
    if (!in_array($key, $tagIndex, true)) {
        $tagIndex[] = $key;
        cache_set("tag_index.{$tag}", $tagIndex, 0); // never expire the index
    }

    return cache_set($key, $value, $ttl);
}

/**
 * Flush all entries associated with a tag.
 *
 * cache_tag_flush('users');  // invalidates all user-tagged cache
 */
function cache_tag_flush(string $tag): int
{
    $tagIndex = (array) cache_get("tag_index.{$tag}", []);
    $count    = 0;

    foreach ($tagIndex as $key) {
        if (cache_forget($key)) $count++;
    }

    cache_forget("tag_index.{$tag}");
    return $count;
}
