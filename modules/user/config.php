<?php

// ─────────────────────────────────────────
//  Module: user — Config
// ─────────────────────────────────────────

config_set('modules.user', [
    'table'            => 'users',
    'per_page'         => 15,
    'default_role'     => 'user',
    'roles'            => ['user', 'admin', 'moderator'],
    'soft_deletes'     => true,
]);
