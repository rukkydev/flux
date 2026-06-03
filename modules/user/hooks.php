<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: user — Event Hooks
// ─────────────────────────────────────────

// Share auth user with all views (web only)
// IMPORTANT: Only query users table for user sessions,
// NOT for admin sessions — admins are in a separate table
event_listen('flux.booted', function() {
    if (is_cli()) return;

    $id   = session_get('auth.id');
    $type = session_get('auth.type', 'guest');

    if (!$id) return;

    // Only look up users table for user sessions
    // Admin sessions use the admins table — handled by admin module
    if ($type === 'user') {
        $user = user_find($id);
        if ($user) {
            view_share('auth_user', $user);
        }
    } elseif ($type === 'admin') {
        // Share admin record for admin sessions
        $admin = admin_find($id);
        if ($admin) {
            view_share('auth_user', $admin);
            view_share('auth_admin', $admin);
        }
    }
});

event_listen('user.created', function(array $payload) {
    log_info('User created', ['id' => $payload['id'], 'email' => $payload['email']]);
});

event_listen('user.deleted', function(array $payload) {
    log_info('User deleted', ['id' => $payload['id']]);
});
