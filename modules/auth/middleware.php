<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Middleware Registration
// ─────────────────────────────────────────

middleware_register('auth', function () {
    auth_remember_check();

    if (!auth_check()) {
        if (request_is_api()) {
            response_unauthorized('Authentication required.');
        }
        flash('error', 'Please log in to continue.');
        redirect('/login');
    }
});

middleware_register('guest', function () {
    if (auth_check()) {
        redirect('/dashboard');
    }
});

middleware_register('admin', function () {
    auth_remember_check();

    if (!auth_check()) {
        flash('error', 'Please log in to continue.');
        redirect('/login');
    }

    if (!auth_is_admin()) {
        if (request_is_api()) {
            response_forbidden('Admin access required.');
        }
        abort(403, 'Admin access required.');
    }
});

middleware_register('verified', function () {
    $user = auth_user();
    if ($user && empty($user['email_verified_at'])) {
        flash('error', 'Please verify your email first.');
        redirect('/email/verify');
    }
});
