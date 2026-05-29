<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Security Layer
//  CSRF, XSS, headers, sanitization
// ─────────────────────────────────────────

/**
 * Send recommended security HTTP headers.
 */
function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (!is_debug()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Verify CSRF token on state-changing requests.
 */
function csrf_verify(): void
{
    $method = request_method();

    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token  = $_POST['_csrf_token'] ?? request_header('X-CSRF-Token');
    $stored = $_SESSION['_csrf_token'] ?? null;

    if (!$stored || !hash_equals($stored, (string) $token)) {
        log_warning('CSRF token mismatch', [
            'ip'   => request_ip(),
            'path' => request_path(),
        ]);
        abort(419, 'CSRF token mismatch.');
    }
}

/**
 * Sanitize a string value against XSS.
 */
function sanitize(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize an array of values recursively.
 */
function sanitize_array(array $data): array
{
    array_walk_recursive($data, function (&$value) {
        if (is_string($value)) {
            $value = sanitize($value);
        }
    });
    return $data;
}

/**
 * Hash a password securely.
 */
function password_make(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 1,
    ]);
}

/**
 * Verify a password against a hash.
 */
function password_check(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Generate a cryptographically secure random token.
 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
