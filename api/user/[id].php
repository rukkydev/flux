<?php

// ─────────────────────────────────────────
//  API: GET/PUT/DELETE /api/user/[id]
// ─────────────────────────────────────────

api_auth_required();
api_rate_limit(60);

$id   = (int) route_param('id');
$user = user_find($id);

if (!$user) response_not_found('User not found.');

api_dispatch([
    'GET' => function() use ($user) {
        response_success(arr_except($user, ['password', 'remember_token']));
    },

    'PUT' => function() use ($user) {
        authorize('edit_users');
        $data = api_validate([
            'name'  => 'sometimes|min:2|max:255',
            'email' => 'sometimes|email|max:255',
            'role'  => 'sometimes|in:user,admin,moderator',
        ]);
        user_update($user['id'], $data);
        response_success(user_find($user['id']));
    },

    'DELETE' => function() use ($user) {
        authorize('delete_users');
        user_delete($user['id']);
        response_no_content();
    },
]);
