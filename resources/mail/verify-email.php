<?php /** @var array $user, string $link */ ?>
<h1>Verify your email</h1>
<p>Hi <?= e($user['name']) ?>,</p>
<p>Please verify your email address to complete your account setup.</p>
<a href="<?= e($link) ?>" class="btn">Verify Email Address</a>
<hr class="divider">
<p class="muted">If you didn't create an account, no further action is required.</p>
