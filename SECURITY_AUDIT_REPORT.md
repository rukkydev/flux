# FluxPHP Security & Architecture Audit Report
**Date:** May 30, 2026  
**Auditor:** Senior PHP Security Architect  
**Framework Version:** 1.0.0  
**PHP Version:** 8.3+  
**Deployment:** XAMPP / Apache with mod_rewrite

---

## EXECUTIVE SUMMARY

FluxPHP is a procedural PHP framework with **strong foundational security practices** but contains several **critical vulnerabilities and architectural gaps** that must be addressed before production launch. The framework demonstrates good intent with prepared statements, session management, and CSRF protection, but exhibits dangerous security holes in rate limiting, RBAC enforcement, and input validation that could be exploited by determined attackers.

**Key Findings:**
- ✅ **Good:** Prepared statements throughout, Argon2ID password hashing, secure session regeneration
- ✅ **Good:** CSRF token validation, security headers sent, .htaccess protections in place
- ⚠️ **Concerning:** Rate limiting is easily bypassed via X-Forwarded-For spoofing
- ⚠️ **Concerning:** RBAC has potential authorization bypass issues
- ❌ **Critical:** Missing Content Security Policy (CSP) headers
- ❌ **Critical:** Remember token implementation is vulnerable to precomputation attacks
- ❌ **Critical:** Missing input sanitization on file uploads
- ❌ **Critical:** Session fixation risk on concurrent logins

**Overall Security Score: 6.2/10**
- Strong: Database security, password handling, CSRF protection
- Weak: Rate limiting, RBAC edge cases, API authentication validation
- Missing: CSP, input sanitization, request correlation, health checks

---

## CRITICAL & HIGH-SEVERITY FINDINGS

### 1. CRITICAL: IP Spoofing in Rate Limiting & Throttling
**SEVERITY:** CRITICAL  
**AREA:** Security / Rate Limiting  
**ISSUE:** Rate limiting uses `request_ip()` which trusts `X-Forwarded-For` header without validation. An attacker can spoof their IP to bypass all rate limiting.

**LOCATION:** 
- `core/request.php` line 55 — `request_ip()` function
- `core/middleware.php` line 64 — throttle middleware
- `core/api.php` line 76 — api_rate_limit()
- `modules/auth/throttle.php` line 13 — auth_throttle_key()

**CODE EVIDENCE:**
```php
// core/request.php - VULNERABLE
function request_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}
```

**IMPACT:**
- An attacker can bypass login attempt throttling by changing `X-Forwarded-For` on each request
- Brute-force attacks on login become feasible with 5 attempts per IP → unlimited with header spoofing
- API rate limiting is completely ineffective if not behind a trusted proxy
- Denial-of-service attacks possible by spoofing legitimate user IPs

**FIX:**
```php
// SECURE VERSION
function request_ip(): string
{
    // Only trust X-Forwarded-For if behind a trusted proxy (list IPs of your proxy servers)
    $trustedProxies = config('app.trusted_proxies', ['127.0.0.1']);
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (!in_array($clientIp, $trustedProxies, true)) {
        return $clientIp; // Don't trust forwarded headers
    }
    
    // If we trust the proxy, parse X-Forwarded-For safely
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!empty($forwardedFor)) {
        $ips = array_map('trim', explode(',', $forwardedFor));
        $clientIp = $ips[0];
        
        // Validate IP format to prevent header injection
        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
    
    return $clientIp;
}
```

**REMEDIATION STEPS:**
1. Add `app.trusted_proxies` config array (default: only 127.0.0.1)
2. Update all places that call `request_ip()` to accept this config
3. For public deployments, either:
   - Use the real client IP from your reverse proxy/CDN (which sets a custom header)
   - OR implement per-session rate limiting instead of per-IP

---

### 2. CRITICAL: Session Fixation Risk & Remember Token Vulnerability
**SEVERITY:** CRITICAL  
**AREA:** Security / Authentication  
**ISSUE:** Session is regenerated on login, but the "remember me" token uses a simple hash. An attacker with database access can precompute SHA256 hashes and gain access to remember tokens.

**LOCATION:**
- `modules/auth/functions.php` line 38 — auth_login() doesn't fully regenerate session state
- `modules/auth/functions.php` line 36 — remember token is stored as `hash('sha256', $token)` only

**CODE EVIDENCE:**
```php
// modules/auth/functions.php - VULNERABLE PATTERN
function auth_login(array $user, bool $remember = false): void
{
    session_regenerate(); // Good - regenerates session ID
    
    session_set('auth.id', $user['id']);
    session_set('auth.name', $user['name']);
    // ... problem: old session data not cleared before new login
    
    if ($remember) {
        $token = generate_token(40);
        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
        db_update('users', $user['id'], ['remember_token' => hash('sha256', $token)]);
        // Problem: No additional binding to user agent / IP
    }
}
```

**IMPACT:**
- An attacker can maintain login access even if password is changed (via stolen remember token)
- Token is only 40 bytes (320 bits) but hashed once with SHA256 (not suitable for password hashing)
- If database is breached, attacker can precompute remember tokens for all users
- Session doesn't use "secure" flag properly on all cookies
- Concurrent login from different devices creates security risks

**FIX:**
```php
function auth_login(array $user, bool $remember = false): void
{
    // Destroy old session completely
    session_destroy();
    session_start();
    session_regenerate(true); // true = delete old session
    
    // Clear ALL previous session data
    $_SESSION = [];
    
    // Set only essential data
    $_SESSION['auth.id'] = $user['id'];
    $_SESSION['auth.email'] = $user['email'];
    $_SESSION['auth.role'] = $user['role'];
    $_SESSION['_user_agent_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['_ip_hash'] = hash('sha256', request_ip());
    
    if ($remember) {
        $token = generate_token(64); // 64 bytes = 512 bits
        $selector = generate_token(16); // Random selector
        $hash = password_hash($token . $selector, PASSWORD_ARGON2ID);
        
        setcookie('remember_token_selector', $selector, time() + (86400 * 30), '/', '', true, true);
        setcookie('remember_token_validator', $token, time() + (86400 * 30), '/', '', true, true);
        
        db_update('users', $user['id'], [
            'remember_token' => $hash,
            'remember_token_expires' => date('Y-m-d H:i:s', time() + (86400 * 30)),
        ]);
    }
    
    event('auth.login', ['id' => $user['id']]);
}

// Add session validation on each request
function auth_validate_session(): void
{
    if (!session_get('auth.id')) return;
    
    // Detect session hijacking
    $storedUA = session_get('_user_agent_hash');
    $currentUA = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    if ($storedUA && $storedUA !== $currentUA) {
        session_destroy_flux();
        log_warning('Potential session hijacking detected', [
            'user_id' => session_get('auth.id'),
            'ip' => request_ip(),
        ]);
        abort(401, 'Session invalid. Please login again.');
    }
}
```

---

### 3. CRITICAL: Missing Content Security Policy (CSP) Headers
**SEVERITY:** CRITICAL  
**AREA:** Security / HTTP Headers  
**ISSUE:** No CSP header set, allowing XSS attacks to execute injected scripts freely.

**LOCATION:** `core/security.php` line 6 — security_headers()

**CODE EVIDENCE:**
```php
function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    // ... missing CSP entirely
}
```

**IMPACT:**
- Inline scripts in view templates are vulnerable to injection
- Third-party scripts can be injected if not validated
- XSS attacks bypass browser protections
- Could lead to credential theft, malware injection, etc.

**FIX:**
```php
function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    
    // CSP Header - strict by default
    $csp = "default-src 'self'; "
         . "script-src 'self'; "
         . "style-src 'self' https://cdn.jsdelivr.net; "
         . "img-src 'self' data: https:; "
         . "font-src 'self'; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'; "
         . "form-action 'self'; "
         . "base-uri 'self'; "
         . "upgrade-insecure-requests";
    
    // Allow unsafe-inline in development only
    if (is_debug()) {
        $csp = str_replace("'self'", "'self' 'unsafe-inline'", $csp);
    }
    
    header('Content-Security-Policy: ' . $csp);
    
    if (!is_debug()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}
```

---

### 4. HIGH: RBAC Authorization Bypass - Admins Can't Be Restricted
**SEVERITY:** HIGH  
**AREA:** Security / Authorization  
**ISSUE:** The `can()` function returns `true` for all admins without checking the resource. This prevents admin impersonation restrictions.

**LOCATION:** `modules/permission/functions.php` line 9

**CODE EVIDENCE:**
```php
function can(string $ability, ?array $resource = null): bool
{
    if (!auth_check()) return false;

    $role = auth_role();

    // PROBLEM: Admins bypass ALL checks
    if ($role === 'admin') return true;

    // Ownership check only happens for non-admins
    if ($resource && isset($resource['user_id'])) {
        if ((int) $resource['user_id'] === auth_id()) return true;
    }

    return permission_role_has($role, $ability);
}
```

**IMPACT:**
- An admin account cannot have restrictions placed (e.g., a "restricted admin" role)
- If an admin account is compromised, the attacker has unrestricted access
- No way to implement admin approval workflows (e.g., super-admin must approve deletions)
- Conflicts with principle of least privilege

**FIX:**
```php
function can(string $ability, ?array $resource = null): bool
{
    if (!auth_check()) return false;

    $role = auth_role();
    $userId = auth_id();

    // Check explicit restrictions first (even for admins)
    if (permission_is_restricted($userId, $ability)) {
        return false;
    }

    // Ownership check
    if ($resource && isset($resource['user_id'])) {
        if ((int) $resource['user_id'] === $userId) {
            return true;
        }
    }

    // Admin bypass (but still respects restrictions above)
    if ($role === 'admin') {
        return true;
    }

    // Check role-based permissions
    return permission_role_has($role, $ability);
}

function permission_is_restricted(int $userId, string $ability): bool
{
    return (bool) db_value(
        "SELECT COUNT(*) FROM `user_restrictions` 
         WHERE `user_id` = ? AND `ability` = ?",
        [$userId, $ability]
    );
}
```

---

### 5. HIGH: API Token Hashing is One-Way Only
**SEVERITY:** HIGH  
**AREA:** Security / API Authentication  
**ISSUE:** API tokens are hashed in the database, but the middleware doesn't validate the hash timing-safely. A user can be identified from the database lookup itself.

**LOCATION:** `core/middleware.php` line 71

**CODE EVIDENCE:**
```php
middleware_register('api.auth', function () {
    $token = request_bearer();

    if (!$token) {
        response_unauthorized('API token required.');
    }

    // PROBLEM: Direct equality check on hashed value
    $record = db_find_where('api_tokens', ['token' => hash('sha256', $token)]);

    if (!$record) {
        response_unauthorized('Invalid API token.');
    }
    // ...
});
```

**IMPACT:**
- Timing attacks could reveal valid token prefixes
- If database is breached, the hashed tokens are useful (since the DB stores them in hashed form)
- No rate limiting on token validation itself
- Token revocation takes a full `available_at` cycle to become effective

**FIX:**
```php
middleware_register('api.auth', function () {
    $token = request_bearer();

    if (!$token) {
        response_unauthorized('API token required.');
    }

    // Rate limit token validation per IP
    $throttleKey = 'api_token_attempts_' . md5(request_ip());
    $attempts = (int) cache_get($throttleKey, 0);
    if ($attempts > 20) {
        log_warning('API token brute force attempt', ['ip' => request_ip()]);
        response_error('Too many authentication attempts.', 429);
    }
    cache_set($throttleKey, $attempts + 1, 300);

    $hashedToken = hash('sha256', $token);
    $record = db_find_where('api_tokens', ['token' => $hashedToken]);

    // Constant-time comparison
    if (!$record || !hash_equals($record['token'], $hashedToken)) {
        response_unauthorized('Invalid API token.');
    }

    if (!empty($record['expires_at']) && strtotime($record['expires_at']) < time()) {
        response_unauthorized('API token expired.');
    }

    // Check if token is revoked
    if ($record['revoked_at'] ?? null) {
        response_unauthorized('API token revoked.');
    }

    // Update last used (prevents accidental double-updates)
    if (time() - strtotime($record['last_used'] ?? '2000-01-01') > 60) {
        db_update('api_tokens', $record['id'], ['last_used' => date('Y-m-d H:i:s')]);
    }

    $_SERVER['_api_user_id'] = $record['user_id'];
});
```

---

### 6. HIGH: Missing CSRF Token Initialization
**SEVERITY:** HIGH  
**AREA:** Security / CSRF Protection  
**ISSUE:** CSRF tokens are validated but never initialized in session if not present.

**LOCATION:** `core/security.php` line 30 — csrf_verify()

**CODE EVIDENCE:**
```php
function csrf_verify(): void
{
    $method = request_method();
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = $_POST['_csrf_token'] ?? request_header('X-CSRF-Token');
    $stored = $_SESSION['_csrf_token'] ?? null;

    if (!$stored || !hash_equals($stored, (string) $token)) {
        log_warning('CSRF token mismatch', [...]);
        abort(419, 'CSRF token mismatch.');
    }
    // PROBLEM: Token never created if missing
}
```

**IMPACT:**
- First POST request will always fail because `$_SESSION['_csrf_token']` doesn't exist
- Users can't submit forms on first page load
- Token should be created during session initialization

**FIX:**
```php
function csrf_token(): string
{
    if (!session_has('_csrf_token')) {
        session_set('_csrf_token', generate_token(32));
    }
    return session_get('_csrf_token');
}

function csrf_verify(): void
{
    $method = request_method();
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = $_POST['_csrf_token'] ?? request_header('X-CSRF-Token');
    $stored = csrf_token(); // Ensures token exists

    if (!hash_equals($stored, (string) $token)) {
        log_warning('CSRF token mismatch', [
            'ip' => request_ip(),
            'path' => request_path(),
        ]);
        abort(419, 'CSRF token mismatch.');
    }
}

// Call csrf_token() initialization in bootstrap or middleware
```

---

### 7. HIGH: Missing HSTS & Weak HTTPS Enforcement
**SEVERITY:** HIGH  
**AREA:** Security / HTTPS  
**ISSUE:** HSTS header only sent in production mode. This leaves development/staging vulnerable to SSL stripping attacks if accidentally exposed.

**LOCATION:** `core/security.php` line 17

**CODE EVIDENCE:**
```php
if (!is_debug()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
```

**IMPACT:**
- No HSTS in debug mode means downgrade attacks possible
- If debug flag is accidentally left on in production, no HSTS protection
- Users can be forced to HTTP even on HTTPS-only sites

**FIX:**
```php
function security_headers(): void
{
    // ... other headers ...
    
    if (!is_debug()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    } else {
        // Still send HSTS in debug if HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=3600; includeSubDomains');
        }
    }
}
```

---

## MEDIUM-SEVERITY FINDINGS

### 8. MEDIUM: File Upload Security - No Validation
**SEVERITY:** MEDIUM  
**AREA:** Security / File Handling  
**ISSUE:** Framework has no built-in file upload validation. Users could upload arbitrary file types including PHP scripts.

**LOCATION:** No file upload handling in `core/`

**IMPACT:**
- If a file upload endpoint isn't properly validated, attackers can upload PHP shells
- File type validation relies entirely on developer implementation
- MIME type spoofing possible if only `$_FILES['file']['type']` is checked

**FIX:**
Create `core/filesystem.php`:
```php
function upload_validate(array $file, array $rules = []): bool
{
    $maxSize = $rules['max_size'] ?? 5 * 1024 * 1024; // 5MB default
    $allowedMimes = $rules['mimes'] ?? ['image/jpeg', 'image/png', 'application/pdf'];
    $allowedExts = $rules['extensions'] ?? ['jpg', 'png', 'pdf'];

    if ($file['size'] > $maxSize) {
        return false;
    }

    // Use finfo to detect actual MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($actualMime, $allowedMimes, true)) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return false;
    }

    return true;
}

function upload_store(array $file, string $directory): string
{
    $filename = generate_token(16) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $path = storage_path('uploads/' . $directory . '/' . $filename);
    
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new \RuntimeException('Failed to store uploaded file');
    }

    return $path;
}
```

---

### 9. MEDIUM: Environment Variable Inline Comment Parsing Issue
**SEVERITY:** MEDIUM  
**AREA:** Code Quality / Configuration  
**ISSUE:** `.env` parser strips inline comments with ` #` which could break values containing `#`.

**LOCATION:** `core/env.php` line 26

**CODE EVIDENCE:**
```php
// Strip inline comments
if (str_contains($value, ' #')) {
    $value = trim(explode(' #', $value, 2)[0]);
}
```

**IMPACT:**
- A value like `PASSWORD="test#1234"` would be truncated to `PASSWORD="test`
- Database URLs with fragments/anchors would break: `mysql://host/#database`
- Users might not notice config values being silently truncated

**FIX:**
```php
function env_load(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if (str_starts_with($line, '#') || $line === '') {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Only strip inline comments if value is not quoted
        if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
            if (str_contains($value, ' #')) {
                $value = trim(explode(' #', $value, 2)[0]);
            }
        }

        // Strip surrounding quotes (without breaking # inside them)
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
```

---

### 10. MEDIUM: Request Method Spoofing Not Validated
**SEVERITY:** MEDIUM  
**AREA:** Security / Routing  
**ISSUE:** `_method` POST field can be set to any value without validation.

**LOCATION:** `core/request.php` line 28

**CODE EVIDENCE:**
```php
function request_method(): string
{
    // Support method spoofing via _method field
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    return strtoupper($method);
}
```

**IMPACT:**
- An attacker could send `_method=TRACE` or other dangerous methods
- Could bypass method-based access controls
- Middleware might expect GET but receive DELETE via _method

**FIX:**
```php
function request_method(): string
{
    $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $method = strtoupper(trim($method));

    if (!in_array($method, $allowedMethods, true)) {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    return $method;
}
```

---

### 11. MEDIUM: Soft Delete Queries Don't Exclude Deleted Records by Default
**SEVERITY:** MEDIUM  
**AREA:** Database / Data Integrity  
**ISSUE:** When using soft deletes with `deleted_at`, regular queries don't filter them out automatically.

**LOCATION:** `core/database.php` line 500+ (query builder)

**IMPACT:**
- Developers must remember to add `whereNull('deleted_at')` on every query
- Easy to accidentally show deleted records to users
- Inconsistent state between `db_find()` and `db_table()->first()`

**FIX:**
```php
// Modify db_find_where and other helpers:
function db_find_where(string $table, array $conditions, string $columns = '*'): ?array
{
    // Auto-exclude soft-deleted records
    $conditions['deleted_at'] = null;
    
    [$where, $bindings] = db_build_where($conditions);
    return db_first("SELECT {$columns} FROM `{$table}` WHERE {$where} LIMIT 1", $bindings);
}

// OR use query builder:
$user = db_table('users')
    ->where('email', 'user@example.com')
    ->withoutTrashed() // Default behavior
    ->first();

$allIncludingDeleted = db_table('users')
    ->where('email', 'user@example.com')
    ->withTrashed() // Explicit opt-in
    ->first();
```

---

### 12. MEDIUM: Queue Worker Has No Graceful Shutdown
**SEVERITY:** MEDIUM  
**AREA:** Architecture / Queue System  
**ISSUE:** Queue worker (if implemented via CLI) would have no way to gracefully stop mid-job.

**LOCATION:** `core/queue.php` (no CLI implementation provided)

**IMPACT:**
- Sending SIGTERM to worker might interrupt mid-job
- Jobs could be lost or duplicated
- No cleanup of database connections

**FIX:**
Provide a CLI worker command:
```php
// flux queue:work --queue=emails
function queue_work(string $queueName = 'default', int $timeout = 60): void
{
    $stopFile = storage_path('queue/.stop');
    
    $handlers = [
        SIGTERM => function() use ($stopFile) { 
            touch($stopFile); 
        },
        SIGINT => function() use ($stopFile) { 
            touch($stopFile); 
        },
    ];
    
    foreach ($handlers as $signal => $handler) {
        pcntl_signal($signal, $handler);
    }

    while (!file_exists($stopFile)) {
        $job = queue_next($queueName);
        
        if (!$job) {
            sleep(1);
            pcntl_signal_dispatch();
            continue;
        }

        try {
            queue_process($job);
        } catch (\Throwable $e) {
            queue_fail($job, $e->getMessage());
        }
        
        pcntl_signal_dispatch();
    }
    
    @unlink($stopFile);
    log_info("Queue worker stopped gracefully");
}
```

---

## MEDIUM-SEVERITY DATABASE FINDINGS

### 13. MEDIUM: Query Builder Column Names Not Fully Escaped
**SEVERITY:** MEDIUM  
**AREA:** Database / SQL Injection  
**ISSUE:** While values are parameterized, column names in query builder use backticks but could still be vulnerable to edge cases.

**LOCATION:** `core/database.php` line 650+

**IMPACT:**
- Limited SQL injection via column names (backticks help)
- Better than nothing but should validate allowed columns

**FIX:**
```php
// Add to QueryBuilder class:
private array $allowedColumns = [];

public function setAllowedColumns(array $columns): static
{
    $this->allowedColumns = array_flip($columns);
    return $this;
}

public function select(string $columns): static
{
    if (!empty($this->allowedColumns)) {
        $cols = array_map('trim', explode(',', $columns));
        foreach ($cols as $col) {
            if (!isset($this->allowedColumns[$col])) {
                throw new \InvalidArgumentException("Column not allowed: {$col}");
            }
        }
    }
    $this->columns = $columns;
    return $this;
}
```

---

### 14. MEDIUM: Pagination "From" Value Incorrect When Total is 0
**SEVERITY:** MEDIUM  
**AREA:** Database / Pagination  
**ISSUE:** Pagination returns `'from' => 0` when empty, but should be `null` or handled specially.

**LOCATION:** `core/database.php` line 498

**CODE EVIDENCE:**
```php
'from' => $total > 0 ? $offset + 1 : 0,
```

**IMPACT:**
- API clients might interpret `from: 0` as a valid record number
- Off-by-one errors in UI pagination displays
- Inconsistent with Laravel's pagination behavior

**FIX:**
```php
'from' => $total > 0 ? $offset + 1 : null,
'to'   => $total > 0 ? min($offset + $perPage, $total) : null,
```

---

### 15. MEDIUM: No Timeout on Remote Database Connections
**SEVERITY:** MEDIUM  
**AREA:** Database / Reliability  
**ISSUE:** PDO connections have no timeout configured for network failures.

**LOCATION:** `core/database.php` line 60 (PDO options)

**IMPACT:**
- Hung database connections cause page hangs
- No recovery from network failures
- Users see timeouts rather than errors

**FIX:**
```php
$cfg['options'] ?? [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 10,  // Add this
];
```

---

## ARCHITECTURE & DESIGN FINDINGS

### 16. MEDIUM: Module Load Order Not Enforced - Circular Dependency Risk
**SEVERITY:** MEDIUM  
**AREA:** Architecture / Module System  
**ISSUE:** Modules declare `requires` but it's not validated during boot.

**LOCATION:** `core/modules.php` line 40

**CODE EVIDENCE:**
```php
$manifest = [
    'name' => $name,
    'requires' => [], // Declared but never checked!
];
```

**IMPACT:**
- Auth module requires `user` module, but if user loads after auth, functions fail
- No validation that dependencies are available
- Silent failures if a required module is disabled

**FIX:**
```php
function modules_validate_dependencies(): void
{
    global $_FLUX_MODULES, $_FLUX_MODULE_DISABLED;

    foreach ($_FLUX_MODULES as $name => $manifest) {
        if (in_array($name, $_FLUX_MODULE_DISABLED, true)) {
            continue;
        }

        $requires = $manifest['requires'] ?? [];
        foreach ($requires as $dependency) {
            if (!isset($_FLUX_MODULES[$dependency])) {
                throw new \RuntimeException(
                    "Module [{$name}] requires [{$dependency}] which is not installed."
                );
            }

            if (in_array($dependency, $_FLUX_MODULE_DISABLED, true)) {
                throw new \RuntimeException(
                    "Module [{$name}] requires [{$dependency}] which is disabled."
                );
            }
        }
    }
}

// Call in bootstrap after modules_boot()
modules_validate_dependencies();
```

---

### 17. MEDIUM: Global State Pollution via $_FLUX_* Globals
**SEVERITY:** MEDIUM  
**AREA:** Architecture / Code Quality  
**ISSUE:** Excessive use of global state makes testing and concurrency problematic.

**LOCATION:** Throughout `core/` files

**IMPACT:**
- Difficult to unit test components in isolation
- Potential state leakage between requests in async PHP (Swoole/ReactPHP)
- Race conditions in multi-threaded environments
- Makes refactoring difficult

**RECOMMENDATION:**
- Keep procedural approach but encapsulate in a singleton class
- Provide DI container for testing

---

### 18. MEDIUM: Middleware Execution Can't Be Halted
**SEVERITY:** MEDIUM  
**AREA:** Architecture / Middleware  
**ISSUE:** Middleware can call `redirect()` but execution continues in router_execute().

**LOCATION:** `core/router.php` line 210

**CODE EVIDENCE:**
```php
function router_execute(string $file, array $middleware, array $params): void
{
    // Run middleware
    if (!empty($middleware)) {
        middleware($middleware);  // redirect() calls exit(), so this is okay
    }
    // ... but if middleware doesn't exit, execution continues
}
```

**IMPACT:**
- Middleware that returns early (without exit) continues to execute the page
- Auth middleware that denies access might still render the page

**FIX:**
```php
function middleware_register('auth', function () {
    if (!auth_check()) {
        if (request_is_api()) {
            response_unauthorized('Authentication required.');
            // exit is called by response_unauthorized()
        }
        session_set('_redirect_to', request_url());
        redirect(url('/login'));
        exit; // Explicit exit needed
    }
});
```

---

### 19. MEDIUM: No Request Correlation/Tracing ID
**SEVERITY:** MEDIUM  
**AREA:** Architecture / Observability  
**ISSUE:** No built-in request ID generation for tracing across logs.

**IMPACT:**
- Hard to trace a single request through multiple log entries
- Makes debugging production issues difficult
- No correlation between API requests and worker jobs

**FIX:**
```php
// In bootstrap/app.php after session:
$_SERVER['_request_id'] = generate_token(16);

// In logging:
function log_info(string $message, array $context = []): void
{
    $context['request_id'] = $_SERVER['_request_id'] ?? 'N/A';
    // ... log normally
}

// In API responses:
function response_json(mixed $data, int $status = 200, array $headers = []): never
{
    header('X-Request-ID: ' . ($_SERVER['_request_id'] ?? 'N/A'));
    // ... continue
}
```

---

## LOW-SEVERITY & INFO-LEVEL FINDINGS

### 20. LOW: Response Headers Not Properly Validated
**SEVERITY:** LOW  
**AREA:** Code Quality  
**ISSUE:** Header values are not validated for injection.

**LOCATION:** `core/api.php` (CORS headers)

**FIX:**
```php
function safe_header(string $key, string $value): void
{
    // Remove newlines to prevent header injection
    $key = str_replace(["\r", "\n"], '', $key);
    $value = str_replace(["\r", "\n"], '', $value);
    
    header("{$key}: {$value}");
}
```

---

### 21. LOW: Event Listeners Catch All Exceptions
**SEVERITY:** LOW  
**AREA:** Code Quality  
**ISSUE:** Event dispatcher swallows exceptions in listeners.

**LOCATION:** `core/modules.php` (event system)

**RECOMMENDATION:** Log exceptions but continue, or expose them in debug mode.

---

### 22. INFO: No Health Check Endpoint
**SEVERITY:** INFO  
**AREA:** Missing Features / DevOps  
**ISSUE:** No `/health` or `/ping` endpoint for load balancers/monitoring.

**RECOMMENDATION:**
```php
// routes/web.php
route('/health', 'api/health.php', []);

// api/health.php
response_json([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'uptime' => time() - FLUX_START,
    'database' => db_value('SELECT 1') ? 'ok' : 'down',
]);
```

---

### 23. INFO: Missing GDPR/Privacy Helpers
**SEVERITY:** INFO  
**AREA:** Missing Features / Compliance  
**ISSUE:** No built-in support for data export or erasure.

---

### 24. INFO: Queue System Not Implemented
**SEVERITY:** INFO  
**AREA:** Missing Features / Deployment  
**ISSUE:** Queue structure exists but no CLI worker, no async processing.

---

### 25. INFO: No Database Connection Pooling
**SEVERITY:** INFO  
**AREA:** Missing Features / Scalability  
**ISSUE:** Each request creates a new PDO connection.

---

---

## TOP 5 CRITICAL FIXES REQUIRED BEFORE LAUNCH

### Fix #1: Secure request_ip() and Rate Limiting (CRITICAL)
**Time:** 2 hours  
**Risk:** Without this, brute-force attacks are trivial  
**Steps:**
1. Add `app.trusted_proxies` config
2. Rewrite `request_ip()` with proxy validation
3. Update auth throttle to use per-session fallback
4. Add tests for IP spoofing prevention

### Fix #2: Session Fixation & Remember Token (CRITICAL)
**Time:** 3 hours  
**Risk:** Session hijacking and unauthorized persistence  
**Steps:**
1. Completely regenerate session on login
2. Implement Argon2ID remember tokens (not SHA256)
3. Add user-agent/IP validation on each request
4. Add `auth_validate_session()` call in bootstrap

### Fix #3: Content Security Policy Header (CRITICAL)
**Time:** 1 hour  
**Risk:** XSS attacks can steal credentials  
**Steps:**
1. Add strict CSP header in `security_headers()`
2. Remove inline scripts from templates
3. Use external script files only
4. Test with browser CSP violation reports

### Fix #4: CSRF Token Initialization (HIGH)
**Time:** 30 minutes  
**Risk:** First POST requests fail, poor UX  
**Steps:**
1. Add `csrf_token()` function that ensures token exists
2. Call `csrf_token()` in session initialization
3. Make sure token is available in forms
4. Test form submissions on first page load

### Fix #5: Validate Request Methods & Add Rate Limiting to Auth (HIGH)
**Time:** 1.5 hours  
**Risk:** Method spoofing and credential stuffing  
**Steps:**
1. Validate `_method` against allowed list
2. Add per-IP rate limiting to login endpoint
3. Add per-email rate limiting to password reset
4. Implement exponential backoff for auth failures

---

## RECOMMENDED ADDITIONS FOR v1.1

### Phase 1 (Security - Implement Soon)
- [ ] OWASP Top 10 compliance checklist
- [ ] Input sanitization helpers for file uploads
- [ ] Database query logging with performance warnings
- [ ] Request ID / correlation ID tracking
- [ ] Audit log middleware for admin actions
- [ ] Two-factor authentication framework
- [ ] Password breach detection (via API)
- [ ] Lockout policy (IP + account level)

### Phase 2 (Observability)
- [ ] Health check endpoint (`/health`)
- [ ] Metrics collection (query count, response time)
- [ ] Request tracing with parent/child correlation
- [ ] Structured logging (JSON output)
- [ ] Error tracking integration (Sentry, etc.)
- [ ] Performance monitoring (slow queries, slow requests)

### Phase 3 (Compliance)
- [ ] GDPR helpers (data export, erasure)
- [ ] Encryption at rest (for sensitive fields)
- [ ] Right to be forgotten implementation
- [ ] Data retention policies
- [ ] Consent management

### Phase 4 (Advanced Features)
- [ ] WebSocket support (foundation exists)
- [ ] GraphQL API layer
- [ ] Event sourcing / CQRS patterns
- [ ] Database connection pooling
- [ ] Circuit breaker for external APIs
- [ ] Multi-tenancy support
- [ ] i18n / localization system
- [ ] Background job scheduling (cron replacement)

### Phase 5 (Testing & Quality)
- [ ] Unit test helpers
- [ ] Integration test framework
- [ ] Security test suite
- [ ] Load testing utilities
- [ ] Chaos engineering helpers
- [ ] Code coverage reporting

---

## DEPLOYMENT READINESS CHECKLIST

### Pre-Launch Security Checklist
- [ ] IP spoofing fix applied and tested
- [ ] Session fixation mitigation implemented
- [ ] CSP headers enabled
- [ ] CSRF token initialization working
- [ ] All passwords using Argon2ID (audit database)
- [ ] Remember tokens are revokable
- [ ] API tokens have expiration
- [ ] File upload validation in place
- [ ] SQL injection tests performed
- [ ] XSS tests in templates
- [ ] CORS properly configured (not `*`)
- [ ] Sensitive data not in logs
- [ ] Database backups configured
- [ ] Encryption keys rotated
- [ ] .env is in .gitignore
- [ ] Storage/ permissions are 755 (not world-writable)
- [ ] Error pages don't leak stack traces in production
- [ ] Rate limiting thresholds set appropriately

### Production Configuration Checklist
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Trusted proxies configured (if behind reverse proxy)
- [ ] HTTPS enabled and enforced
- [ ] HSTS header active
- [ ] Session storage is persistent (not in /tmp)
- [ ] Log retention policy set
- [ ] Cache backend is production-grade (not file-based for high traffic)
- [ ] Queue worker processes running
- [ ] Database connection pooling configured
- [ ] Monitoring/alerting set up
- [ ] Incident response plan documented
- [ ] Security update process defined

### Post-Launch Monitoring
- [ ] Failed login attempts tracked
- [ ] Unusual API token activity detected
- [ ] Large file uploads flagged
- [ ] Database query performance monitored
- [ ] External API failures handled gracefully
- [ ] Queue job failures logged and alerted

---

## OVERALL SECURITY SCORE: 6.2/10

### Breakdown by Component

| Component | Score | Notes |
|-----------|-------|-------|
| **Database Security** | 8/10 | Prepared statements excellent, query builder solid |
| **Password Handling** | 9/10 | Argon2ID is best practice |
| **Session Management** | 5/10 | Regeneration good, but fixation risk; no device binding |
| **CSRF Protection** | 7/10 | Tokens implemented but initialization issue |
| **Rate Limiting** | 2/10 | **CRITICAL:** IP spoofing trivializes all protections |
| **API Authentication** | 6/10 | Token hashing ok, but no timing-safe comparison |
| **Authorization (RBAC)** | 6/10 | Ownership checks good, but admin bypass issues |
| **Input Validation** | 5/10 | Partial validation, no file upload validation |
| **HTTP Headers** | 4/10 | Missing CSP entirely, HSTS only in production |
| **Error Handling** | 7/10 | Good exception catches, but logs could leak data |
| **Logging & Monitoring** | 5/10 | Basic logging, no request correlation |
| **Code Quality** | 6/10 | Procedural style is maintainable, but global state issues |

### Justification
- **Strengths (8-9):** Database layer uses prepared statements consistently; password hashing uses Argon2ID with sensible parameters
- **Solid (6-7):** CSRF tokens, error handling, basic logging
- **Weak (4-5):** Rate limiting is broken; HTTP headers incomplete; authorization has edge cases
- **Critical Issues (2-3):** IP-based rate limiting is easily bypassed making all throttling ineffective

**Recommendation:** Do NOT launch to production without fixing issues #1, #2, and #3. These are easily exploitable by attackers.

---

## FINAL RECOMMENDATIONS

1. **Before Launch:** Implement all 5 critical fixes listed above
2. **Within 30 Days:** Add GDPR helpers, OWASP compliance checklist, and request correlation
3. **Version 1.1:** Phase 2 observability features + advanced security options
4. **Ongoing:** Regular security audits, dependency updates, penetration testing

### Next Steps
1. Create GitHub issues for each finding
2. Assign priority (Critical > High > Medium > Low)
3. Implement fixes in order of severity
4. Add tests for each security fix
5. Perform security regression testing
6. Schedule penetration test before production
7. Document all security decisions in SECURITY.md

---

**Report Generated:** 2026-05-30  
**Auditor:** Senior PHP Security Architect  
**Confidence Level:** High (comprehensive code review, not penetration test)
