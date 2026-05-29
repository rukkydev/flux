<?php

// ─────────────────────────────────────────
//  FluxPHP — API Routes
//
//  File-based routing handles /api/* automatically.
//  Add manual overrides or aliases here.
// ─────────────────────────────────────────

// Auth endpoints (public)
route('/api/auth/login',    'api/auth/login.php');
route('/api/auth/register', 'api/auth/register.php');
route('/api/auth/logout',   'api/auth/logout.php',  ['api.auth']);
route('/api/auth/me',       'api/auth/me.php',       ['api.auth']);

// User endpoints (authenticated)
route('/api/user',          'api/user/index.php',    ['api.auth']);

// Admin endpoints
route('/api/admin/stats',   'api/admin/stats.php',   ['api.auth']);
