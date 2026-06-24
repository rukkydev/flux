<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 - Too Many Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="error-page">
<?php $errorMessage = $message ?? 'Please slow down and try again shortly.'; ?>
<div class="error-shell">
    <div class="error-panel">
        <div class="error-band">
            <div class="error-brand"><?= e(config('app.name', 'FluxPHP')) ?></div>
            <div class="error-badge">Rate Limit</div>
        </div>
        <div class="error-body text-center">
            <div class="error-code">429</div>
            <h1 class="error-title">Too many requests</h1>
            <p class="error-copy"><?= e($errorMessage) ?></p>
            <div class="error-actions">
                <a href="<?= url('/') ?>" class="btn btn-dark"><i class="bi bi-house me-1"></i>Home</a>
                <a href="<?= url('/health') ?>" class="btn btn-outline-primary"><i class="bi bi-heart-pulse me-1"></i>Health</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
