<?php

// ─────────────────────────────────────────
//  Module: admin — Routes
// ─────────────────────────────────────────

route('/admin',                    'pages/admin/index.php',                ['auth', 'admin']);
route('/admin/users',              'pages/admin/users/index.php',          ['auth', 'admin']);
route('/admin/settings',           'pages/admin/settings/index.php',       ['auth', 'admin']);
route('/admin/settings/cache-clear', 'pages/admin/settings/cache-clear.php', ['auth', 'admin']);
