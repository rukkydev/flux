<?php

// ─────────────────────────────────────────
//  Module: admin — Routes
// ─────────────────────────────────────────

// Admin auth (no middleware — handled inside page)
route('/admin/login',  'pages/admin/login.php',  ['admin.guest']);
route('/admin/logout', 'pages/admin/logout.php', ['admin']);

// Admin pages (protected by admin middleware)
route('/admin',                      'pages/admin/index.php',                  ['admin']);
route('/admin/users',                'pages/admin/users/index.php',            ['admin']);
route('/admin/settings',             'pages/admin/settings/index.php',         ['admin']);
route('/admin/settings/cache-clear', 'pages/admin/settings/cache-clear.php',  ['admin']);
