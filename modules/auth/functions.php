<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Core Auth Functions
//  Audit fixes: #2 session destroy on login,
//  session UA binding, separate admin auth
// ─────────────────────────────────────────

// ── USER AUTH ────────────────────────────

function auth_attempt(string $email, string $password, bool $remember = false): bool
{
    $user = user_find_by_email($email);

    if (!$user || !password_check($password, $user['password'])) {
        auth_throttle_record($email);
        return false;
    }

    if (!empty($user['deleted_at'])) {
        return false;
    }

    auth_login($user, $remember);
    auth_throttle_clear($email);

    return true;
}

function auth_login(array $user, bool $remember = false): void
{
    session_reset_flux();

    // Regenerate ID to prevent session fixation
    session_regenerate_id(true);

    session_set('auth.id',    $user['id']);
    session_set('auth.name',  $user['name']);
    session_set('auth.email', $user['email']);
    session_set('auth.type',  'user');

    // Bind session to UA for hijack detection (fix #2)
    if (config('security.session.validate_user_agent', true)) {
        session_set('_ua_hash', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    if ($remember) {
        $token = generate_token(40);
        setcookie('remember_token', $token, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'secure'   => !is_debug(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        db_update('users', $user['id'], ['remember_token' => hash('sha256', $token)]);
    }

    user_record_login($user['id']);
    event('auth.login', ['id' => $user['id'], 'email' => $user['email'], 'type' => 'user']);
}

function auth_logout(): void
{
    $id = auth_id();

    if ($id && auth_type() === 'user' && !is_cli()) {
        db_update('users', $id, ['remember_token' => null]);
    }

    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    session_destroy_flux();
    event('auth.logout', ['id' => $id, 'type' => 'user']);
}

function auth_check(): bool
{
    return (bool) session_get('auth.id');
}

function auth_guest(): bool
{
    return !auth_check();
}

function auth_id(): ?int
{
    $id = session_get('auth.id');
    return $id ? (int) $id : null;
}

function auth_user(): ?array
{
    $id = auth_id();
    return $id ? user_find($id) : null;
}

function auth_role(): string
{
    return (string) session_get('auth.role', 'user');
}

function auth_is(string $role): bool
{
    return auth_role() === $role;
}

function auth_is_admin(): bool
{
    return session_get('auth.type') === 'admin';
}

function auth_type(): string
{
    return (string) session_get('auth.type', 'guest');
}

/**
 * Validate session hasn't been hijacked (fix #2).
 */
function auth_validate_session(): void
{
    if (!auth_check()) return;

    if (!config('security.session.validate_user_agent', true)) return;

    $stored  = session_get('_ua_hash');

    // No UA hash stored — old session format, just bind it now
    if (!$stored) {
        session_set('_ua_hash', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        return;
    }

    $current = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    if (!hash_equals($stored, $current)) {
        $isAdmin = (auth_type() === 'admin');

        log_warning('Possible session hijack detected', [
            'user_id' => auth_id(),
            'ip'      => request_ip(),
        ]);
        session_destroy_flux();

        if (request_is_api()) {
            response_unauthorized('Session invalid. Please login again.');
        }
        flash('error', 'Your session has expired. Please login again.');
        redirect($isAdmin ? url('/admin/login') : url('/login'));
    }
}

function auth_remember_check(): void
{
    if (auth_check()) return;

    $cookie = $_COOKIE['remember_token'] ?? null;
    if (!$cookie) return;

    $user = db_find_where('users', ['remember_token' => hash('sha256', $cookie)]);
    if ($user && empty($user['deleted_at'])) {
        auth_login($user, remember: true);
    }
}

// ── ADMIN AUTH ────────────────────────────

function admin_attempt(string $email, string $password): bool
{
    $admin = admin_find_by_email($email);

    if (!$admin || !password_check($password, $admin['password'])) {
        auth_throttle_record('admin:' . $email);
        return false;
    }

    if (empty($admin['is_active'])) {
        return false;
    }

    admin_login($admin);
    auth_throttle_clear('admin:' . $email);

    return true;
}

function admin_login(array $admin): void
{
    session_reset_flux();

    session_regenerate_id(true);

    session_set('auth.id',    $admin['id']);
    session_set('auth.name',  $admin['name']);
    session_set('auth.email', $admin['email']);
    session_set('auth.type',  'admin');

    if (config('security.session.validate_user_agent', true)) {
        session_set('_ua_hash', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    db_update('admins', $admin['id'], [
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => request_ip(),
    ]);

    event('admin.login', ['id' => $admin['id'], 'email' => $admin['email']]);
}

function admin_logout(): void
{
    $id = auth_id();
    session_destroy_flux();
    event('admin.logout', ['id' => $id]);
}

function admin_find_by_email(string $email): ?array
{
    return db_find_where('admins', ['email' => $email, 'is_active' => 1]);
}

function admin_find(int $id): ?array
{
    return db_find('admins', $id);
}

function admin_create(string $name, string $email, string $password, ?int $createdBy = null): string
{
    if (db_exists('admins', ['email' => $email])) {
        throw new \RuntimeException("Admin with email {$email} already exists.");
    }

    return db_insert('admins', [
        'name'       => $name,
        'email'      => $email,
        'password'   => password_make($password),
        'is_active'  => 1,
        'created_by' => $createdBy,
    ]);
}

// ── API TOKENS ────────────────────────────

function auth_token_create(int $userId, string $name = 'default', ?int $expiresMinutes = null): string
{
    $token = generate_token(32);

    $data = [
        'user_id' => $userId,
        'name'    => $name,
        'token'   => hash('sha256', $token),
    ];

    if ($expiresMinutes) {
        $data['expires_at'] = date('Y-m-d H:i:s', time() + ($expiresMinutes * 60));
    }

    db_insert('api_tokens', $data);

    return $token;
}

function auth_token_revoke(int $userId, string $name = 'default'): int
{
    return db_update_where('api_tokens', ['user_id' => $userId, 'name' => $name], [
        'revoked_at' => date('Y-m-d H:i:s'),
    ]);
}
