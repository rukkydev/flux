<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 - Forbidden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="container text-center py-5 px-4">
    <h1 class="display-1 fw-bold text-muted">403</h1>
    <h2 class="mb-3">Forbidden</h2>
    <p class="text-muted"><?= e($message ?: 'You are not allowed to access this page.') ?></p>
    <a href="<?= url('/') ?>" class="btn btn-dark">Go Home</a>
</div>
</body>
</html>
