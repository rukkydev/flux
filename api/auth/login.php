<?php

// ─────────────────────────────────────────
//  API: POST /api/auth/login
// ─────────────────────────────────────────

api_only('POST');
api_rate_limit(10, 60);

$data = api_validate([
    'email'    => 'required|email',
    'password' => 'required',
]);

if (auth_throttle_exceeded($data['email'])) {
    response_too_many_requests('Too many login attempts. Please wait.');
}

$user = user_find_by_email($data['email']);

if (!$user || !password_check($data['password'], $user['password'])) {
    auth_throttle_record($data['email']);
    response_error('Invalid credentials.', 401);
}

if (!empty($user['deleted_at'])) {
    response_error('This account has been deactivated.', 403);
}

auth_throttle_clear($data['email']);
user_record_login($user['id']);

$token = auth_token_create($user['id'], 'api');
event('auth.login', ['id' => $user['id'], 'email' => $user['email']]);

response_success([
    'token' => $token,
    'user'  => arr_except($user, ['password', 'remember_token']),
], 'Login successful.');
