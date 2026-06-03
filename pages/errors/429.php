<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>429 - Too Many Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="container text-center py-5 px-4">
    <h1 class="display-1 fw-bold text-muted">429</h1>
    <h2 class="mb-3">Too Many Requests</h2>
    <p class="text-muted"><?= e($message ?: 'Please slow down and try again shortly.') ?></p>
    <a href="<?= url('/') ?>" class="btn btn-dark">Go Home</a>
</div>
</body>
</html>
