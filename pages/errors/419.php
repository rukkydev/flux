<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - Page Expired</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="error-page">
<?php $errorMessage = $message ?? 'Your session token expired. Refresh and try again.'; ?>
<div class="error-shell">
    <div class="error-panel">
        <div class="error-band">
            <div class="error-brand"><?= e(config('app.name', 'FluxPHP')) ?></div>
            <div class="error-badge">Session</div>
        </div>
        <div class="error-body text-center">
            <div class="error-code">419</div>
            <h1 class="error-title">Page expired</h1>
            <p class="error-copy"><?= e($errorMessage) ?></p>
            <div class="error-actions">
                <a href="<?= url('/') ?>" class="btn btn-dark"><i class="bi bi-house me-1"></i>Home</a>
                <a href="<?= url('/login') ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Retry</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
