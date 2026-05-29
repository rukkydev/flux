<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: user — Complex Queries
// ─────────────────────────────────────────

function user_search(string $term, int $page = 1, int $perPage = 15): array
{
    return db_table('users')
        ->whereNull('deleted_at')
        ->whereLike('name', "%{$term}%")
        ->order('name')
        ->paginate($page, $perPage);
}

function user_by_role(string $role): array
{
    return db_table('users')
        ->where('role', $role)
        ->whereNull('deleted_at')
        ->order('name')
        ->get();
}

function user_recently_active(int $days = 30): array
{
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    return db_table('users')
        ->where('last_login_at', '>=', $since)
        ->whereNull('deleted_at')
        ->order('last_login_at', 'DESC')
        ->get();
}

function user_count_by_role(): array
{
    return db_select(
        "SELECT role, COUNT(*) as total FROM `users`
         WHERE deleted_at IS NULL GROUP BY role"
    );
}
