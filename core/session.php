<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Session System (v1.1)
//  File: core/session.php
//
//  Supports three drivers (set SESSION_DRIVER in .env):
//    file      — default, PHP file-based sessions
//    database  — DB-backed via SessionHandlerInterface
//    cookie    — encrypted signed cookie (no server storage)
//
//  Public API (unchanged from v1.0):
//    session_start_flux()
//    session_get(key, default)
//    session_set(key, value)
//    session_forget(key)
//    session_flush()
//    session_destroy_flux()
//    session_regenerate()
//    session_id_flux()
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────
//  Boot — call once in bootstrap/app.php
// ─────────────────────────────────────────────────────

function session_start_flux(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Already started — don't double-start
    }

    $config = config('session');
    $driver = $config['driver'] ?? 'file';

    // ── Select and register the session handler ───────
    switch ($driver) {
        case 'database':
            $handler = new DatabaseSessionHandler($config);
            session_set_save_handler($handler, true);
            break;

        case 'cookie':
            $handler = new CookieSessionHandler($config);
            session_set_save_handler($handler, true);
            break;

        case 'file':
        default:
            $savePath = $config['path'] ?? storage_path('sessions');
            if (!is_dir($savePath)) {
                mkdir($savePath, 0755, true);
            }
            session_save_path($savePath);
            break;
    }

    // ── Cookie parameters ─────────────────────────────
    session_set_cookie_params([
        'lifetime' => 0,   // Expire on browser close; lifetime handled server-side
        'path'     => '/',
        'domain'   => $config['domain']   ?? '',
        'secure'   => $config['secure']   ?? false,
        'httponly' => $config['httponly']  ?? true,
        'samesite' => $config['samesite']  ?? 'Lax',
    ]);

    session_name($config['name'] ?? 'FLUX_SESSION');
    session_start();
}

// ─────────────────────────────────────────────────────
//  Public session helpers (API unchanged from v1.0)
// ─────────────────────────────────────────────────────

/**
 * Get a session value using dot notation.
 * session_get('auth.id')
 * session_get('auth.name', 'Guest')
 */
function session_get(string $key, mixed $default = null): mixed
{
    $parts  = explode('.', $key);
    $value  = $_SESSION ?? [];

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Set a session value using dot notation.
 * session_set('auth.id', 42)
 */
function session_set(string $key, mixed $value): void
{
    $parts = explode('.', $key);
    $ref   = &$_SESSION;

    foreach ($parts as $part) {
        if (!isset($ref[$part]) || !is_array($ref[$part])) {
            $ref[$part] = [];
        }
        $ref = &$ref[$part];
    }

    $ref = $value;
}

/**
 * Remove a session key using dot notation.
 */
function session_forget(string $key): void
{
    $parts  = explode('.', $key);
    $last   = array_pop($parts);
    $ref    = &$_SESSION;

    foreach ($parts as $part) {
        if (!isset($ref[$part])) return;
        $ref = &$ref[$part];
    }

    unset($ref[$last]);
}

/**
 * Clear all session data (but keep session alive).
 */
function session_flush(): void
{
    $_SESSION = [];
}

/**
 * Fully destroy the session (logout).
 * Clears data, deletes cookie, destroys server-side record.
 */
function session_destroy_flux(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

/**
 * Regenerate the session ID.
 * Call on login and privilege escalation — NOT on every request.
 */
function session_regenerate(): void
{
    session_regenerate_id(true);
}

/**
 * Get the current session ID.
 */
function session_id_flux(): string
{
    return session_id();
}

// ─────────────────────────────────────────────────────
//  DATABASE SESSION HANDLER
// ─────────────────────────────────────────────────────

/**
 * Stores sessions in the `sessions` database table.
 *
 * Required table (run the migration in database/migrations/):
 *
 *   CREATE TABLE sessions (
 *     id          VARCHAR(128)     PRIMARY KEY,
 *     user_id     INT UNSIGNED     NULL,
 *     ip_address  VARCHAR(45)      NULL,
 *     user_agent  VARCHAR(255)     NULL,
 *     payload     LONGBLOB         NOT NULL,
 *     last_active INT UNSIGNED     NOT NULL,
 *     INDEX idx_user_id    (user_id),
 *     INDEX idx_last_active (last_active)
 *   );
 *
 * Benefits over file sessions:
 *   - Force-logout any user instantly (DELETE FROM sessions WHERE user_id = ?)
 *   - See all active sessions per user
 *   - Survives server restarts and works across multiple servers
 *   - GC happens in-database (efficient, no cron needed)
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private array  $config;
    private string $table = 'sessions';
    private mixed  $db    = null;   // PDO connection

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->table  = $config['table'] ?? 'sessions';
    }

    /** Get PDO from FluxPHP's DB layer */
    private function pdo(): \PDO
    {
        if ($this->db === null) {
            // Use FluxPHP's internal PDO accessor
            // Adjust this if your DB layer exposes PDO differently
            $this->db = db_pdo();
        }
        return $this->db;
    }

    public function open(string $path, string $name): bool
    {
        return true; // Connection managed by FluxPHP DB layer
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $lifetime = (int) ($this->config['lifetime'] ?? 120) * 60;
            $expiry   = time() - $lifetime;

            $stmt = $this->pdo()->prepare(
                "SELECT payload FROM {$this->table} WHERE id = ? AND last_active >= ?"
            );
            $stmt->execute([$id, $expiry]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ? base64_decode($row['payload']) : '';
        } catch (\Throwable $e) {
            log_error('DatabaseSessionHandler::read() failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $userId    = $_SESSION['auth.id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
                : null;

            $stmt = $this->pdo()->prepare("
                INSERT INTO {$this->table} (id, user_id, ip_address, user_agent, payload, last_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    user_id     = VALUES(user_id),
                    ip_address  = VALUES(ip_address),
                    user_agent  = VALUES(user_agent),
                    payload     = VALUES(payload),
                    last_active = VALUES(last_active)
            ");

            $stmt->execute([
                $id,
                $userId,
                $ipAddress,
                $userAgent,
                base64_encode($data),
                time(),
            ]);

            return true;
        } catch (\Throwable $e) {
            log_error('DatabaseSessionHandler::write() failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo()->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (\Throwable $e) {
            log_error('DatabaseSessionHandler::destroy() failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function gc(int $maxlifetime): int|false
    {
        try {
            $expiry = time() - $maxlifetime;
            $stmt   = $this->pdo()->prepare(
                "DELETE FROM {$this->table} WHERE last_active < ?"
            );
            $stmt->execute([$expiry]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            log_error('DatabaseSessionHandler::gc() failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

// ─────────────────────────────────────────────────────
//  COOKIE SESSION HANDLER
//  (stateless — no server-side storage)
//  Good for: API-only apps, read-only sessions
//  Bad for:  force-logout, large session data
// ─────────────────────────────────────────────────────

/**
 * Stores session data entirely in an encrypted, signed cookie.
 *
 * Pros: Zero server-side storage, scales instantly
 * Cons: Cannot invalidate remotely (force-logout impossible),
 *       data exposed if APP_KEY is compromised,
 *       limited to ~4KB cookie size
 *
 * Configure in .env:
 *   SESSION_DRIVER=cookie
 *   APP_KEY=your-32-char-secret-key
 */
class CookieSessionHandler implements SessionHandlerInterface
{
    private string $cookieName;
    private string $key;
    private int    $lifetime;
    private array  $config;

    public function __construct(array $config)
    {
        $this->config     = $config;
        $this->cookieName = ($config['name'] ?? 'FLUX_SESSION') . '_DATA';
        $this->lifetime   = (int) ($config['lifetime'] ?? 120) * 60;

        $appKey = config('app.key', '');
        if (strlen($appKey) < 16) {
            log_warning('CookieSessionHandler: APP_KEY is too short. Use at least 32 characters.');
        }
        $this->key = $appKey;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $cookie = $_COOKIE[$this->cookieName] ?? null;
        if (!$cookie) return '';

        $decoded = $this->decrypt($cookie);
        if ($decoded === false) return '';

        $parsed = json_decode($decoded, true);
        if (!is_array($parsed)) return '';

        // Check expiry
        if (($parsed['expires'] ?? 0) < time()) return '';

        return base64_decode($parsed['data'] ?? '');
    }

    public function write(string $id, string $data): bool
    {
        $payload = json_encode([
            'data'    => base64_encode($data),
            'expires' => time() + $this->lifetime,
        ]);

        $encrypted = $this->encrypt($payload);

        // Size guard — cookies max ~4KB
        if (strlen($encrypted) > 3800) {
            log_warning('CookieSessionHandler: Session data exceeds safe cookie size. Switch to database driver.');
            return false;
        }

        $params = session_get_cookie_params();
        setcookie(
            $this->cookieName,
            $encrypted,
            [
                'expires'  => time() + $this->lifetime,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        setcookie($this->cookieName, '', time() - 42000, '/');
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        return 0; // No server-side storage to clean
    }

    private function encrypt(string $data): string
    {
        $iv  = random_bytes(16);
        $key = hash('sha256', $this->key, true);

        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        $hmac      = hash_hmac('sha256', $encrypted, $this->key);

        return base64_encode($iv . '::' . $hmac . '::' . $encrypted);
    }

    private function decrypt(string $payload): string|false
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) return false;

        $parts = explode('::', $decoded, 3);
        if (count($parts) !== 3) return false;

        [$iv, $hmac, $encrypted] = $parts;

        $expectedHmac = hash_hmac('sha256', $encrypted, $this->key);
        if (!hash_equals($expectedHmac, $hmac)) {
            log_warning('CookieSessionHandler: HMAC mismatch — possible tampering detected.');
            return false;
        }

        $key = hash('sha256', $this->key, true);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
