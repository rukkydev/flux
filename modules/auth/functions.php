<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Core Auth Functions
// ─────────────────────────────────────────

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
    session_regenerate();

    session_set('auth.id',    $user['id']);
    session_set('auth.name',  $user['name']);
    session_set('auth.email', $user['email']);
    session_set('auth.role',  $user['role']);

    if ($remember) {
        $token = generate_token(40);
        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
        db_update('users', $user['id'], ['remember_token' => hash('sha256', $token)]);
    }

    user_record_login($user['id']);

    event('auth.login', ['id' => $user['id'], 'email' => $user['email']]);
}

function auth_logout(): void
{
    $id = auth_id();

    // Clear remember token
    if ($id) {
        db_update('users', $id, ['remember_token' => null]);
    }

    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    session_destroy_flux();

    event('auth.logout', ['id' => $id]);
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
    return (string) session_get('auth.role', '');
}

function auth_is(string $role): bool
{
    return auth_role() === $role;
}

function auth_is_admin(): bool
{
    return auth_is('admin');
}

function auth_token_create(int $userId, string $name = 'default'): string
{
    $token = generate_token(32);

    db_insert('api_tokens', [
        'user_id' => $userId,
        'name'    => $name,
        'token'   => hash('sha256', $token),
    ]);

    return $token;
}

function auth_token_revoke(int $userId, string $name = 'default'): int
{
    return db_delete_where('api_tokens', [
        'user_id' => $userId,
        'name'    => $name,
    ]);
}

function auth_remember_check(): void
{
    if (auth_check()) return;

    $cookie = $_COOKIE['remember_token'] ?? null;
    if (!$cookie) return;

    $user = db_find_where('users', ['remember_token' => hash('sha256', $cookie)]);
    if ($user) {
        auth_login($user, remember: true);
    }
}
