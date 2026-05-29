<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: user — Domain Functions
// ─────────────────────────────────────────

function user_find(int|string $id): ?array
{
    return db_find('users', $id);
}

function user_find_by_email(string $email): ?array
{
    return db_find_where('users', ['email' => $email]);
}

function user_find_or_fail(int|string $id): array
{
    return db_find_or_fail('users', $id);
}

function user_all(int $page = 1, int $perPage = 15): array
{
    return db_table('users')
        ->whereNull('deleted_at')
        ->order('created_at', 'DESC')
        ->paginate($page, $perPage);
}

function user_create(array $data): string
{
    $data['password'] = password_make($data['password']);

    $id = db_insert('users', arr_except($data, ['password_confirmation']));

    event('user.created', ['id' => $id, 'email' => $data['email']]);

    return $id;
}

function user_update(int|string $id, array $data): int
{
    if (isset($data['password'])) {
        $data['password'] = password_make($data['password']);
    }

    $rows = db_update('users', $id, arr_except($data, ['password_confirmation']));

    event('user.updated', ['id' => $id]);

    return $rows;
}

function user_delete(int|string $id): int
{
    $rows = db_soft_delete('users', $id);
    event('user.deleted', ['id' => $id]);
    return $rows;
}

function user_exists(string $email): bool
{
    return db_exists('users', ['email' => $email]);
}

function user_has_role(array $user, string $role): bool
{
    return ($user['role'] ?? '') === $role;
}

function user_is_admin(array $user): bool
{
    return user_has_role($user, 'admin');
}

function user_record_login(int|string $id): void
{
    db_update('users', $id, ['last_login_at' => date('Y-m-d H:i:s')]);
}
