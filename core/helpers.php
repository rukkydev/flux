<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Global Helpers
// ─────────────────────────────────────────


// ── String Helpers ────────────────────────

function str_slug(string $value, string $separator = '-'): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\w\s-]/u', '', $value);
    $value = preg_replace('/[\s_]+/', $separator, $value);
    return trim($value, $separator);
}

function str_limit(string $value, int $limit = 100, string $end = '...'): string
{
    if (mb_strlen($value) <= $limit) {
        return $value;
    }
    return rtrim(mb_substr($value, 0, $limit)) . $end;
}

function str_contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

function str_camel(string $value): string
{
    return lcfirst(str_pascal($value));
}

function str_pascal(string $value): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
}

function str_snake(string $value): string
{
    $value = preg_replace('/[A-Z]/', '_$0', lcfirst($value));
    return strtolower($value ?? '');
}


// ── Array Helpers ─────────────────────────

function arr_get(array $array, string $key, mixed $default = null): mixed
{
    $parts = explode('.', $key);
    $value = $array;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function arr_only(array $array, array $keys): array
{
    return array_intersect_key($array, array_flip($keys));
}

function arr_except(array $array, array $keys): array
{
    return array_diff_key($array, array_flip($keys));
}

function arr_flatten(array $array, int $depth = INF): array
{
    $result = [];
    foreach ($array as $item) {
        if (is_array($item) && $depth > 0) {
            $result = array_merge($result, arr_flatten($item, $depth - 1));
        } else {
            $result[] = $item;
        }
    }
    return $result;
}


// ── Path Helpers ──────────────────────────

function base_path(string $path = ''): string
{
    return FLUX_ROOT . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path ? '/' . ltrim($path, '/') : ''));
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path ? '/' . ltrim($path, '/') : ''));
}

function config_path(string $path = ''): string
{
    return base_path('config' . ($path ? '/' . ltrim($path, '/') : ''));
}


// ── URL Helpers ───────────────────────────

/**
 * Detect the subfolder base path from SCRIPT_NAME at runtime.
 * e.g. /fluxphp/index.php  →  /fluxphp
 *      /index.php           →  ''
 * Works for XAMPP subfolders, domain roots, and php -S serve.
 */
function base_url_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base   = rtrim(dirname($script), '/\\');

    // dirname('/index.php') returns '/' on Unix — normalize to ''
    if ($base === '/' || $base === '\\') {
        $base = '';
    }

    return $base;
}

/**
 * Build an internal URL.
 * Uses runtime-detected base path — works in any subfolder or port.
 * APP_URL is only used for absolute URLs (emails, webhooks).
 *
 * url('/login')        →  /fluxphp/login   (XAMPP subfolder)
 * url('/login')        →  /login           (domain root or php -S)
 */
function url(string $path = ''): string
{
    $base = base_url_path();
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

/**
 * Build an absolute URL (for emails, API responses, webhooks).
 * Uses APP_URL from .env.
 */
function absolute_url(string $path = ''): string
{
    $base = rtrim(config('app.url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Build an asset URL.
 * Assets live in /public/ — auto-prepended.
 */
function asset(string $path): string
{
    $base = base_url_path();
    $path = ltrim($path, '/');

    if (!str_starts_with($path, 'public/')) {
        $path = 'public/' . $path;
    }

    return $base . '/' . $path;
}


// ── Output / Debug Helpers ────────────────

function dd(mixed ...$vars): never
{
    foreach ($vars as $var) {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:8px;font-size:13px;overflow:auto;margin:8px 0">';
        echo htmlspecialchars(print_r($var, true));
        echo '</pre>';
    }
    exit;
}

function dump(mixed ...$vars): void
{
    foreach ($vars as $var) {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:8px;font-size:13px;overflow:auto;margin:8px 0">';
        echo htmlspecialchars(print_r($var, true));
        echo '</pre>';
    }
}

function abort(int $code = 404, string $message = ''): never
{
    http_response_code($code);

    $errorPage = base_path("pages/errors/{$code}.php");

    if (file_exists($errorPage)) {
        require $errorPage;
    } else {
        echo "<h1>Error {$code}</h1>";
        if ($message) echo "<p>" . htmlspecialchars($message) . "</p>";
    }

    exit;
}


// ── Security Helpers ──────────────────────

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}


// ── Flash / Session Helpers ───────────────

function flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old_input'][$key] ?? $default;
}


// ── Misc Helpers ──────────────────────────

function now(): \DateTimeImmutable
{
    return new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'UTC')));
}

function uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function redirect(string $path, int $status = 302): never
{
    // Full URL — use as-is
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header("Location: {$path}", true, $status);
        exit;
    }
    // Internal path — always go through url() so subfolder is included
    $location = url($path);
    header("Location: {$location}", true, $status);
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    redirect($ref);
}
