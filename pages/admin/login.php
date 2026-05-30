<?php

// ─────────────────────────────────────────
//  Page: /admin/login
//  Separate admin login — no user sessions
// ─────────────────────────────────────────

middleware('admin.guest');
title('Admin Login');
layout('auth');

if (request_is('POST')) {
    $data   = request_only(['email', 'password']);
    $errors = validation_run($data, [
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (!empty($errors)) {
        $_SESSION['_errors']    = $errors;
        $_SESSION['_old_input'] = $data;
        back();
    }

    if (auth_throttle_exceeded('admin:' . $data['email'])) {
        flash('error', 'Too many attempts. Please wait.');
        back();
    }

    if (!admin_attempt($data['email'], $data['password'])) {
        flash('error', 'Invalid admin credentials.');
        $_SESSION['_old_input'] = $data;
        back();
    }

    event('admin.login', ['email' => $data['email'], 'ip' => request_ip()]);
    redirect(url('/admin'));
}

$errors = validation_errors();
?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <div style="width:44px;height:44px;background:var(--biro);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                <i class="bi bi-shield-lock text-white fs-5"></i>
            </div>
            <h4 class="fw-bold mb-1">Admin Access</h4>
            <p class="text-muted small">Restricted area — authorised personnel only</p>
        </div>

        <?php partial('alerts', ['errors' => $errors]) ?>

        <form method="POST" action="<?= url('/admin/login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email"
                       class="form-control <?= error_class('email') ?>"
                       value="<?= e(old('email')) ?>" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password"
                       class="form-control <?= error_class('password') ?>">
            </div>
            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-shield-lock me-2"></i>Sign in as Admin
            </button>
        </form>
    </div>
</div>

<p class="text-center text-muted small mt-3">
    <a href="<?= url('/') ?>" class="text-dark">← Back to site</a>
</p>
