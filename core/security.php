<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Security Layer (Audit Fixes Applied)
//  Fixes: #1 IP spoofing, #3 CSP, #6 CSRF init,
//         #7 HSTS, #10 method whitelist
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────
//  SECURITY HEADERS
// ─────────────────────────────────────────────────────

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // HSTS — always send if HTTPS, shorter max-age in debug
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($isHttps) {
        $maxAge = is_debug() ? 3600 : 31536000;
        header("Strict-Transport-Security: max-age={$maxAge}; includeSubDomains");
    }

    // CSP — config-driven
    if (config('security.csp_enabled', true)) {
        header('Content-Security-Policy: ' . security_build_csp());
    }
}

/**
 * Build CSP header string from config/security.php
 * Developers add CDN sources to config — no code changes needed.
 */
function security_build_csp(): string
{
    $csp     = config('security.csp', []);
    $debug   = is_debug() && ($csp['unsafe_inline_in_debug'] ?? true);
    $inline  = $debug ? " 'unsafe-inline'" : '';
    $nonce   = $debug ? '' : '';

    $directives = [
        'default-src' => implode(' ', $csp['default'] ?? ["'self'"]),
        'script-src'  => implode(' ', $csp['scripts'] ?? ["'self'"]) . $inline,
        'style-src'   => implode(' ', $csp['styles']  ?? ["'self'"]) . $inline,
        'font-src'    => implode(' ', $csp['fonts']   ?? ["'self'"]),
        'img-src'     => implode(' ', $csp['images']  ?? ["'self'", 'data:']),
        'connect-src' => implode(' ', $csp['connect'] ?? ["'self'"]),
        'frame-src'   => implode(' ', $csp['frames']  ?? ["'none'"]),
        'object-src'  => implode(' ', $csp['objects'] ?? ["'none'"]),
        'base-uri'    => "'self'",
        'form-action' => "'self'",
    ];

    if (!is_debug()) {
        $directives['upgrade-insecure-requests'] = '';
    }

    $parts = [];
    foreach ($directives as $directive => $value) {
        $parts[] = trim("{$directive} {$value}");
    }

    return implode('; ', $parts);
}

// ─────────────────────────────────────────────────────
//  CSRF
// ─────────────────────────────────────────────────────

/**
 * Get or create the CSRF token.
 * Fix #6: token is always initialized if missing.
 */
function csrf_token(): string
{
    if (is_cli() || session_status() === PHP_SESSION_NONE) {
        return '';
    }

    if (!session_has('_csrf_token')) {
        session_set('_csrf_token', generate_token(32));
    }

    return session_get('_csrf_token');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token on state-changing requests.
 * Fix #6: uses csrf_token() which ensures token exists.
 */
function csrf_verify(): void
{
    $method = request_method();

    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token  = $_POST['_csrf_token'] ?? request_header('X-CSRF-Token');
    $stored = csrf_token();

    if (!$stored || !hash_equals($stored, (string) $token)) {
        log_warning('CSRF token mismatch', [
            'ip'   => request_ip(),
            'path' => request_path(),
        ]);
        abort(419, 'CSRF token mismatch.');
    }
}

// ─────────────────────────────────────────────────────
//  IP RESOLUTION  (Fix #1)
// ─────────────────────────────────────────────────────

/**
 * Get the real client IP address.
 * Only trusts X-Forwarded-For from IPs listed in security.trusted_proxies.
 * Fix #1: prevents IP spoofing via X-Forwarded-For header.
 */
function request_ip(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $trustedProxies = config('security.trusted_proxies', ['127.0.0.1']);

    // Only trust forwarded headers if request comes from a trusted proxy
    if (!in_array($remoteAddr, $trustedProxies, true)) {
        return $remoteAddr;
    }

    // We trust the proxy — read the forwarded IP
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR']
              ?? $_SERVER['HTTP_X_REAL_IP']
              ?? '';

    if (!empty($forwarded)) {
        // X-Forwarded-For can be comma-separated list — take first
        $ips = array_map('trim', explode(',', $forwarded));
        $clientIp = $ips[0];

        // Validate it's a real IP
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }
    }

    return $remoteAddr;
}

// ─────────────────────────────────────────────────────
//  XSS / SANITIZATION
// ─────────────────────────────────────────────────────

function sanitize(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_array(array $data): array
{
    array_walk_recursive($data, function (&$value) {
        if (is_string($value)) {
            $value = sanitize($value);
        }
    });
    return $data;
}

// ─────────────────────────────────────────────────────
//  PASSWORD
// ─────────────────────────────────────────────────────

function password_make(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 1,
    ]);
}

function password_check(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// ─────────────────────────────────────────────────────
//  TOKEN GENERATION
// ─────────────────────────────────────────────────────

function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
