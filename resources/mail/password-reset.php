<?php /** @var array $user, string $link, int $expiresMinutes */ ?>
<h1>Reset your password</h1>
<p>Hi <?= e($user['name']) ?>,</p>
<p>We received a request to reset your password. Click the button below to choose a new one.</p>
<a href="<?= e($link) ?>" class="btn">Reset Password</a>
<div class="info-box">
    This link expires in <?= $expiresMinutes ?? 60 ?> minutes.
</div>
<hr class="divider">
<p class="muted">If you didn't request a password reset, no action is needed — your password will remain unchanged.</p>
<p class="muted">If the button doesn't work, copy this link into your browser:<br>
<a href="<?= e($link) ?>"><?= e($link) ?></a></p>
