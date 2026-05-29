<?php /** @var array $user, string $ip, string $browser, string $os, string $time */ ?>
<h1>New login detected</h1>
<p>Hi <?= e($user['name']) ?>,</p>
<p>A new login to your account was detected:</p>
<div class="info-box">
    <strong>Time:</strong> <?= e($time) ?><br>
    <strong>IP Address:</strong> <?= e($ip) ?><br>
    <strong>Browser:</strong> <?= e($browser) ?><br>
    <strong>OS:</strong> <?= e($os) ?>
</div>
<p>If this was you, no action is needed.</p>
<div class="alert-box">
    If you don't recognize this login, <a href="<?= absolute_url('/forgot-password') ?>">reset your password immediately</a>.
</div>
