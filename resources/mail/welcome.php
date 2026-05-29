<?php /** @var array $user */ ?>
<h1>Welcome to <?= e(config('app.name')) ?>!</h1>
<p>Hi <?= e($user['name']) ?>,</p>
<p>Your account has been created successfully. We're excited to have you on board.</p>
<a href="<?= absolute_url('/dashboard') ?>" class="btn">Go to Dashboard</a>
<hr class="divider">
<p class="muted">If you didn't create this account, you can safely ignore this email.</p>
