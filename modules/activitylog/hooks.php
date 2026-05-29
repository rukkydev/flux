<?php

// Auto-log auth events
event_listen('auth.login',   fn($p) => activity_log('auth.login',   $p, $p['id'] ?? null));
event_listen('auth.logout',  fn($p) => activity_log('auth.logout',  $p, $p['id'] ?? null));
event_listen('user.created', fn($p) => activity_log('user.created', $p, $p['id'] ?? null));
event_listen('user.deleted', fn($p) => activity_log('user.deleted', $p, $p['id'] ?? null));
event_listen('auth.password_reset', fn($p) => activity_log('auth.password_reset', $p, $p['user_id'] ?? null));
