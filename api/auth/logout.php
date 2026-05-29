<?php

// ─────────────────────────────────────────
//  API: POST /api/auth/logout
// ─────────────────────────────────────────

api_only('POST');
api_auth_required();

$userId = api_user_id();
$token  = request_bearer();

if ($token) {
    db_delete_where('api_tokens', ['token' => hash('sha256', $token)]);
}

event('auth.logout', ['id' => $userId]);
response_success(null, 'Logged out successfully.');
