<?php

// ─────────────────────────────────────────
//  Page: /reset-password/[token]
//  Middleware: guest
// ─────────────────────────────────────────

middleware('guest');
title('Set New Password');
layout('auth');

$token  = route_param('token');
$record = db_first(
    "SELECT * FROM `password_resets` WHERE token = ? AND expires_at > NOW() LIMIT 1",
    [hash('sha256', $token)]
);

if (!$record) {
    flash('error', 'This reset link is invalid or has expired.');
    redirect('/forgot-password');
}

if (request_is('POST')) {
    $data   = request_only(['password', 'password_confirmation']);
    $errors = validation_run($data, [
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        back();
    }

    $user = user_find_by_email($record['email']);

    if ($user) {
        db_update('users', $user['id'], [
            'password'       => password_make($data['password']),
            'remember_token' => null,
        ]);

        db_delete_where('password_resets', ['email' => $record['email']]);

        event('auth.password_reset', ['user_id' => $user['id']]);
    }

    flash('success', 'Password updated. Please log in.');
    redirect('/login');
}

$errors = validation_errors();
?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Set new password</h4>
        <p class="text-muted small mb-4">Choose a strong password for <strong><?= e($record['email']) ?></strong>.</p>

        <?php partial('alerts', ['errors' => $errors]) ?>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label fw-medium">New password</label>
                <input type="password" name="password"
                       class="form-control <?= error_class('password') ?>"
                       autocomplete="new-password" autofocus>
                <?php if (has_error('password')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('password')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Confirm password</label>
                <input type="password" name="password_confirmation"
                       class="form-control <?= error_class('password_confirmation') ?>"
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">Update password</button>
        </form>
    </div>
</div>
