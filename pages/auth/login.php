<?php

// ─────────────────────────────────────────
//  Page: /login
//  Middleware: guest
// ─────────────────────────────────────────

middleware('guest');
title('Login');
layout('auth');

if (request_is('POST')) {
    $data = request_only(['email', 'password', 'remember']);

    $errors = validation_run($data, [
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (!empty($errors)) {
        $_SESSION['_errors']    = $errors;
        $_SESSION['_old_input'] = $data;
        back();
    }

    if (auth_throttle_exceeded($data['email'])) {
        flash('error', 'Too many login attempts. Please wait a moment.');
        back();
    }

    if (!auth_attempt($data['email'], $data['password'], !empty($data['remember']))) {
        $remaining = auth_throttle_remaining($data['email']);
        flash('error', "Invalid credentials. {$remaining} attempt(s) remaining.");
        $_SESSION['_old_input'] = $data;
        back();
    }

    flash('success', 'Logged in successfully!');
    auth_throttle_clear($data['email']);
    redirect('/dashboard');
}

$errors = validation_errors();

?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Sign in</h4>
        <p class="text-muted small mb-4">Welcome back — enter your details below.</p>

        <?php partial('alerts', ['errors' => $errors]) ?>

        <form method="POST" action="<?php url('/login') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label fw-medium">Email address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control <?= error_class('email') ?>"
                    value="<?= e(old('email')) ?>"
                    autocomplete="email"
                    autofocus
                >
                <?php if (has_error('email')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('email')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label class="form-label fw-medium">Password</label>
                    <a href="/forgot-password" class="small text-muted">Forgot password?</a>
                </div>
                <input
                    type="password"
                    name="password"
                    class="form-control <?= error_class('password') ?>"
                    autocomplete="current-password"
                >
                <?php if (has_error('password')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('password')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember" value="1">
                <label class="form-check-label text-muted small" for="remember">Remember me for 30 days</label>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">Sign in</button>
        </form>
    </div>
</div>

<p class="text-center text-muted small mt-3">
    Don't have an account? <a href="<?= url('/register') ?>" class="text-dark fw-medium">Create one</a>
</p>
