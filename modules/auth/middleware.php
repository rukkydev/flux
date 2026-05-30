<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Middleware Registration
// ─────────────────────────────────────────

middleware_register('auth', function () {
    auth_remember_check();
    auth_validate_session();

    if (!auth_check()) {
        if (request_is_api()) {
            response_unauthorized('Authentication required.');
        }
        flash('error', 'Please log in to continue.');
        redirect(url('/login'));
    }
});

middleware_register('guest', function () {
    if (auth_check()) {
        $type = auth_type();
        redirect($type === 'admin' ? url('/admin') : url('/dashboard'));
    }
});

middleware_register('admin', function () {
    auth_validate_session();

    if (!auth_check() || auth_type() !== 'admin') {
        if (request_is_api()) {
            response_forbidden('Admin access required.');
        }
        flash('error', 'Admin access required.');
        redirect(url('/admin/login'));
    }
});

middleware_register('admin.guest', function () {
    if (auth_check() && auth_type() === 'admin') {
        redirect(url('/admin'));
    }
});

middleware_register('verified', function () {
    auth_validate_session();
    $user = auth_user();
    if ($user && empty($user['email_verified_at'])) {
        flash('error', 'Please verify your email first.');
        redirect(url('/email/verify'));
    }
});
