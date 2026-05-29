<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Config Loader
//  Loads and caches config files from /config
// ─────────────────────────────────────────

$_FLUX_CONFIG = [];

function config_load(string $directory): void
{
    global $_FLUX_CONFIG;

    if (!is_dir($directory)) {
        return;
    }

    foreach (glob($directory . '/*.php') as $file) {
        $key = basename($file, '.php');
        $_FLUX_CONFIG[$key] = require $file;
    }
}

/**
 * Get a config value using dot notation.
 * Example: config('database.connections.mysql.host')
 */
function config(string $key, mixed $default = null): mixed
{
    global $_FLUX_CONFIG;

    $parts  = explode('.', $key);
    $value  = $_FLUX_CONFIG;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Set a config value at runtime using dot notation.
 */
function config_set(string $key, mixed $value): void
{
    global $_FLUX_CONFIG;

    $parts = explode('.', $key);
    $ref   = &$_FLUX_CONFIG;

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

/**
 * Check whether we are in debug mode.
 */
function is_debug(): bool
{
    return (bool) config('app.debug', false);
}

/**
 * Check the current application environment.
 */
function app_env(string $env = ''): bool|string
{
    $current = config('app.env', 'production');
    return $env === '' ? $current : $current === $env;
}
