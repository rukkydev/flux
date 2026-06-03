<?php

// ─────────────────────────────────────────
//  Page: /profile
//  Middleware: auth
// ─────────────────────────────────────────

middleware('auth');
title('My Profile');
layout('app');

$user = auth_user();

// Guard: if user not found despite being logged in, clear session
if (!$user) {
    auth_logout();
    flash('error', 'Session expired. Please log in again.');
    redirect(url('/login'));
}

// Handle profile update
if (request_is('POST') && request('_action') === 'profile') {
    $data   = request_only(['name', 'email']);
    $errors = validation_run($data, [
        'name'  => 'required|min:2|max:255',
        'email' => 'required|email|max:255',
    ]);

    if (empty($errors) && $data['email'] !== $user['email'] && user_exists($data['email'])) {
        $errors['email'][] = 'That email address is already taken.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors']    = $errors;
        $_SESSION['_old_input'] = $data;
        back();
    }

    user_update($user['id'], $data);

    // Update session
    session_set('auth.name',  $data['name']);
    session_set('auth.email', $data['email']);

    flash('success', 'Profile updated.');
    redirect(url('/profile'));
}

// Handle password change
if (request_is('POST') && request('_action') === 'password') {
    $data   = request_only(['current_password', 'password', 'password_confirmation']);
    $errors = validation_run($data, [
        'current_password'      => 'required',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);

    if (empty($errors) && !password_check($data['current_password'], $user['password'])) {
        $errors['current_password'][] = 'Current password is incorrect.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        back();
    }

    user_update($user['id'], ['password' => $data['password']]);
    flash('success', 'Password changed successfully.');
    redirect(url('/profile'));
}

$errors = validation_errors();
?>

<div class="row g-4">
    <!-- Profile info -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Profile Information</div>
            <div class="card-body">
                <?php partial('alerts', ['errors' => $errors]) ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="profile">

                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name"
                               class="form-control <?= error_class('name') ?>"
                               value="<?= e(old('name', $user['name'])) ?>">
                        <?php if (has_error('name')): ?>
                            <div class="invalid-feedback"><?= e(validation_error('name')) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" name="email"
                               class="form-control <?= error_class('email') ?>"
                               value="<?= e(old('email', $user['email'])) ?>">
                        <?php if (has_error('email')): ?>
                            <div class="invalid-feedback"><?= e(validation_error('email')) ?></div>
                        <?php endif ?>
                    </div>

                    <button class="btn btn-dark">Save changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Change password -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Change Password</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="password">

                    <div class="mb-3">
                        <label class="form-label">Current password</label>
                        <input type="password" name="current_password"
                               class="form-control <?= error_class('current_password') ?>">
                        <?php if (has_error('current_password')): ?>
                            <div class="invalid-feedback"><?= e(validation_error('current_password')) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New password</label>
                        <input type="password" name="password"
                               class="form-control <?= error_class('password') ?>">
                        <?php if (has_error('password')): ?>
                            <div class="invalid-feedback"><?= e(validation_error('password')) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm new password</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>

                    <button class="btn btn-dark">Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
