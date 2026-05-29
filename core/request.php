<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Request
//  Procedural wrapper around HTTP request
// ─────────────────────────────────────────

function request(string $key = null, mixed $default = null): mixed
{
    $data = array_merge($_GET, $_POST);

    if ($key === null) {
        return $data;
    }

    return $data[$key] ?? $default;
}

function request_method(): string
{
    // Support method spoofing via _method field
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    return strtoupper($method);
}

function request_is(string $method): bool
{
    return request_method() === strtoupper($method);
}

function request_path(): string
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);

    // Strip subfolder prefix so /fluxphp/user/profile -> /user/profile
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php'; // e.g. /fluxphp/index.php
    $base   = rtrim(dirname($script), '/\\');          // e.g. /fluxphp

    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }

    return '/' . trim($path, '/');
}

function request_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
}

function request_is_ajax(): bool
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
}

function request_is_api(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json') || request_is_ajax();
}

function request_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

function request_header(string $key, string $default = ''): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    return $_SERVER[$key] ?? $default;
}

function request_bearer(): ?string
{
    $auth = request_header('Authorization');
    if (str_starts_with($auth, 'Bearer ')) {
        return substr($auth, 7);
    }
    return null;
}

function request_json(): array
{
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return [];
    }
    return json_decode($body, true) ?? [];
}

function request_all(): array
{
    if (request_is('GET')) {
        return $_GET;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        return request_json();
    }

    return array_merge($_GET, $_POST);
}

function request_only(array $keys): array
{
    return arr_only(request_all(), $keys);
}

function request_except(array $keys): array
{
    return arr_except(request_all(), $keys);
}

function request_has(string $key): bool
{
    return isset(request_all()[$key]);
}

function request_filled(string $key): bool
{
    $val = request_all()[$key] ?? null;
    return $val !== null && $val !== '';
}

function request_file(string $key): ?array
{
    return $_FILES[$key] ?? null;
}
