<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: user — Event Hooks
// ─────────────────────────────────────────

// Share auth user with all views (web only)
event_listen('flux.booted', function() {
    if (is_cli()) return;

    if ($id = session_get('auth.id')) {
        $user = user_find($id);
        if ($user) {
            view_share('auth_user', $user);
        }
    }
});

event_listen('user.created', function(array $payload) {
    log_info('User created', ['id' => $payload['id'], 'email' => $payload['email']]);
});

event_listen('user.deleted', function(array $payload) {
    log_info('User deleted', ['id' => $payload['id']]);
});
