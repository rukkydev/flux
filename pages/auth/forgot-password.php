<?php

// ─────────────────────────────────────────
//  Page: /forgot-password
//  Middleware: guest
// ─────────────────────────────────────────

middleware('guest');
title('Reset Password');
layout('auth');

if (request_is('POST')) {
    $data   = request_only(['email']);
    $errors = validation_run($data, ['email' => 'required|email']);

    if (!empty($errors)) {
        $_SESSION['_errors']    = $errors;
        $_SESSION['_old_input'] = $data;
        back();
    }

    $user = user_find_by_email($data['email']);

    if ($user) {
        $token   = generate_token(32);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        // Store reset token
        db_delete_where('password_resets', ['email' => $user['email']]);
        db_insert('password_resets', [
            'email'      => $user['email'],
            'token'      => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        event('auth.password_reset_requested', [
            'user'  => $user,
            'token' => $token,
            'link'  => url("reset-password/{$token}"),
        ]);
    }

    // Always show success (prevent email enumeration)
    flash('success', 'If that email exists, a reset link has been sent.');
    redirect('/forgot-password');
}

$errors = validation_errors();
?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Forgot password?</h4>
        <p class="text-muted small mb-4">Enter your email and we'll send a reset link.</p>

        <?php partial('alerts', ['errors' => $errors]) ?>

        <form method="POST" action="<?php url('/forgot-password') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-medium">Email address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control <?= error_class('email') ?>"
                    value="<?= e(old('email')) ?>"
                    autofocus
                >
                <?php if (has_error('email')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('email')) ?></div>
                <?php endif ?>
            </div>
            <button type="submit" class="btn btn-dark w-100 py-2">Send reset link</button>
        </form>
    </div>
</div>

<p class="text-center text-muted small mt-3">
    <a href="/login" class="text-dark">← Back to login</a>
</p>
