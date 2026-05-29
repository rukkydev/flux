<?php

// ─────────────────────────────────────────
//  API: GET /api/admin/stats
// ─────────────────────────────────────────

api_only('GET');
api_auth_required();
authorize('admin_stats');
api_rate_limit(30);

response_success([
    'users' => [
        'total'   => db_count('users'),
        'active'  => db_count('users', ['deleted_at' => null]),
        'by_role' => user_count_by_role(),
    ],
    'activity' => [
        'today'      => db_count('activity_logs', [
            'created_at' => date('Y-m-d'),
        ]),
        'this_week'  => (int) db_value(
            "SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ),
    ],
    'server' => [
        'php_version' => PHP_VERSION,
        'environment' => config('app.env'),
        'debug'       => is_debug(),
    ],
]);
