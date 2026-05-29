<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Environment Loader
//  Parses .env file and populates $_ENV
// ─────────────────────────────────────────

function env_load(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if (str_starts_with($line, '#') || $line === '') {
            continue;
        }

        // Must contain =
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Strip inline comments
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        // Strip surrounding quotes
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Normalize booleans and null
        $value = match (strtolower($value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };

        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;

        if (!isset($_ENV[$key])) {
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Get an environment variable with optional default.
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}
