<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Session Manager
// ─────────────────────────────────────────

function session_start_flux(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = config('session');

    $savePath = $config['path'] ?? storage_path('sessions');
    if (!is_dir($savePath)) {
        mkdir($savePath, 0755, true);
    }

    session_save_path($savePath);

    session_set_cookie_params([
        'lifetime' => (int) ($config['lifetime'] ?? 120) * 60,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $config['secure'] ?? false,
        'httponly' => $config['httponly'] ?? true,
        'samesite' => $config['samesite'] ?? 'Lax',
    ]);

    session_name('FLUX_SESSION');
    session_start();
}

function session_get(string $key, mixed $default = null): mixed
{
    if (is_cli() || session_status() === PHP_SESSION_NONE) {
        return $default;
    }

    $parts = explode('.', $key);
    $value = $_SESSION;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function session_set(string $key, mixed $value): void
{
    if (is_cli() || session_status() === PHP_SESSION_NONE) {
        return;
    }

    $parts = explode('.', $key);
    $ref   = &$_SESSION;

    foreach ($parts as $i => $part) {
        if ($i === count($parts) - 1) {
            $ref[$part] = $value;
        } else {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }
}

function session_has(string $key): bool
{
    if (is_cli() || session_status() === PHP_SESSION_NONE) {
        return false;
    }
    return session_get($key) !== null;
}

function session_forget(string $key): void
{
    $parts = explode('.', $key);
    $ref   = &$_SESSION;

    foreach ($parts as $i => $part) {
        if ($i === count($parts) - 1) {
            unset($ref[$part]);
        } else {
            if (!isset($ref[$part])) {
                return;
            }
            $ref = &$ref[$part];
        }
    }
}

function session_flush(): void
{
    $_SESSION = [];
}

function session_regenerate(): void
{
    session_regenerate_id(true);
}

function session_destroy_flux(): void
{
    if (is_cli() || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $params = session_get_cookie_params();

    session_flush();
    session_destroy();

    if (!headers_sent()) {
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_write_close();
    $_SESSION = [];
    session_name('FLUX_SESSION');
}

function session_reset_flux(): void
{
    if (is_cli()) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy_flux();
    }

    session_start_flux();
}
