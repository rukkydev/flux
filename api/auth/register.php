<?php

// ─────────────────────────────────────────
//  API: POST /api/auth/register
// ─────────────────────────────────────────

api_only('POST');
api_rate_limit(5, 60);

$data = api_validate([
    'name'                  => 'required|min:2|max:255',
    'email'                 => 'required|email|max:255',
    'password'              => 'required|min:8|confirmed',
    'password_confirmation' => 'required',
]);

if (user_exists($data['email'])) {
    response_error('This email is already registered.', 422);
}

$id    = user_create(arr_except($data, ['password_confirmation']));
$user  = user_find($id);
$token = auth_token_create($id, 'api');

event('user.registered', ['id' => $id, 'email' => $data['email']]);

response_created([
    'token' => $token,
    'user'  => arr_except($user, ['password', 'remember_token']),
], 'Registration successful.');
