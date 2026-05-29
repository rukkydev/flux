<?php

// ─────────────────────────────────────────
//  API: GET /api/user
// ─────────────────────────────────────────

api_only('GET');
api_auth_required();
api_rate_limit(60);

$page    = (int) (request('page') ?? 1);
$perPage = (int) (request('per_page') ?? 15);
$search  = request('search', '');

if ($search) {
    $results = user_search($search, $page, $perPage);
} else {
    $results = user_all($page, $perPage);
}

response_paginated($results);
