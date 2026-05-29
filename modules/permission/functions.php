<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: permission — RBAC
// ─────────────────────────────────────────

/**
 * Check if the authenticated user can perform an action.
 *
 * can('edit_posts')
 * can('delete', $post)     — ownership check
 */
function can(string $ability, ?array $resource = null): bool
{
    if (!auth_check()) return false;

    $role = auth_role();

    // Admins can do everything
    if ($role === 'admin') return true;

    // Ownership check
    if ($resource && isset($resource['user_id'])) {
        if ((int) $resource['user_id'] === auth_id()) return true;
    }

    // Check permissions table
    return permission_role_has($role, $ability);
}

/**
 * Abort with 403 if user cannot perform ability.
 */
function authorize(string $ability, ?array $resource = null): void
{
    if (!can($ability, $resource)) {
        if (request_is_api()) response_forbidden();
        abort(403, 'You are not authorized to perform this action.');
    }
}

function permission_role_has(string $role, string $ability): bool
{
    return (bool) db_value(
        "SELECT COUNT(*) FROM `role_permissions`
         WHERE `role` = ? AND `ability` = ?",
        [$role, $ability]
    );
}

function permission_grant(string $role, string $ability): void
{
    if (!permission_role_has($role, $ability)) {
        db_insert('role_permissions', ['role' => $role, 'ability' => $ability]);
    }
}

function permission_revoke(string $role, string $ability): void
{
    db_delete_where('role_permissions', ['role' => $role, 'ability' => $ability]);
}

function permission_role_abilities(string $role): array
{
    return array_column(
        db_where('role_permissions', ['role' => $role], 'ability'),
        'ability'
    );
}
