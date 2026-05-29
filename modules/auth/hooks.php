<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Hooks
// ─────────────────────────────────────────

// Skip view sharing in CLI context
if (!is_cli()) {
    view_share('auth_check', auth_check());
    view_share('auth_id',    auth_id());
}

event_listen('auth.login', function(array $payload) {
    log_info('User logged in', $payload);
});

event_listen('auth.logout', function(array $payload) {
    log_info('User logged out', $payload);
});
