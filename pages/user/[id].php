<?php

// ─────────────────────────────────────────
//  Page: /user/[id]
//  Dynamic route — $id resolved automatically
// ─────────────────────────────────────────

layout('app');

$id   = (int) route_param('id');
$user = db_find('users', $id);

if (!$user) {
    abort(404, 'User not found.');
}

?>

<div class="py-4">
    <h1><?= e($user['name'] ?? 'User') ?></h1>
    <p class="text-muted">User ID: <?= e($id) ?></p>
</div>
