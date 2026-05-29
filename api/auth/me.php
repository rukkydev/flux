<?php

// ─────────────────────────────────────────
//  API: GET /api/auth/me
// ─────────────────────────────────────────

api_only('GET');
api_auth_required();

$user = api_user();
if (!$user) response_not_found('User not found.');

response_success(arr_except($user, ['password', 'remember_token']));
