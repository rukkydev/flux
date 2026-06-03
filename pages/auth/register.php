<?php

// ─────────────────────────────────────────
//  Page: /register
//  Middleware: guest
// ─────────────────────────────────────────

middleware('guest');
title('Create Account');
layout('auth');

if (request_is('POST')) {
    $data = request_only(['name', 'email', 'password', 'password_confirmation']);

    $errors = validation_run($data, [
        'name'                  => 'required|min:2|max:255',
        'email'                 => 'required|email|max:255',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);

    if (empty($errors) && user_exists($data['email'])) {
        $errors['email'][] = 'This email address is already registered.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors']    = $errors;
        $_SESSION['_old_input'] = $data;
        back();
    }

    $id   = user_create(arr_except($data, ['password_confirmation']));
    $user = user_find($id);

    auth_login($user);
    event('user.registered', ['id' => $id, 'email' => $data['email']]);

    flash('success', 'Welcome to ' . config('app.name') . '!');
    redirect('/dashboard');
}

$errors = validation_errors();

?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Create account</h4>
        <p class="text-muted small mb-4">Get started — it's free.</p>

        <?php partial('alerts', ['errors' => $errors]) ?>

        <form method="POST" action="<?= url('/register') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label fw-medium">Full name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control <?= error_class('name') ?>"
                    value="<?= e(old('name')) ?>"
                    autocomplete="name"
                    autofocus
                >
                <?php if (has_error('name')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('name')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">Email address</label>
                <input
                    type="email"
                    name="email"
                    class="form-control <?= error_class('email') ?>"
                    value="<?= e(old('email')) ?>"
                    autocomplete="email"
                >
                <?php if (has_error('email')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('email')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">Password <span class="text-muted small">(min 8 characters)</span></label>
                <input
                    type="password"
                    name="password"
                    class="form-control <?= error_class('password') ?>"
                    autocomplete="new-password"
                >
                <?php if (has_error('password')): ?>
                    <div class="invalid-feedback"><?= e(validation_error('password')) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Confirm password</label>
                <input
                    type="password"
                    name="password_confirmation"
                    class="form-control <?= error_class('password_confirmation') ?>"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">Create account</button>
        </form>
    </div>
</div>

<p class="text-center text-muted small mt-3">
    Already have an account? <a href="<?= url('/login') ?>" class="text-dark fw-medium">Sign in</a>
</p>
