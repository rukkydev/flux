<?php

// ─────────────────────────────────────────
//  FluxPHP — Web Routes
// ─────────────────────────────────────────

route('/login',           'pages/auth/login.php',           ['guest']);
route('/register',        'pages/auth/register.php',        ['guest']);
route('/logout',          'pages/auth/logout.php',          ['auth']);
route('/forgot-password', 'pages/auth/forgot-password.php', ['guest']);
route('/dashboard',       'pages/dashboard/index.php',      ['auth']);
route('/profile',         'pages/profile/index.php',        ['auth']);

// Admin (also handled by module routes, listed here for clarity)
// route('/admin',           'pages/admin/index.php',            ['auth', 'admin']);
// route('/admin/users',     'pages/admin/users/index.php',      ['auth', 'admin']);
// route('/admin/settings',  'pages/admin/settings/index.php',   ['auth', 'admin']);
