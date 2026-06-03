<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Google+Sans+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <?php yield_push('styles') ?>
</head>
<body>

<div class="d-flex" id="flux-dashboard">

    <!-- Sidebar — injected via section('sidebar') in page file -->
    <nav class="flux-sidebar d-flex flex-column p-0">
        <?php yield_section('sidebar') ?>
    </nav>

    <!-- Main content -->
    <div class="flex-grow-1 d-flex flex-column overflow-hidden">

        <?php component('topbar') ?>

        <main class="p-4 flex-grow-1 overflow-auto">
            <?php component('flash') ?>
            <?php yield_content() ?>
        </main>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/htmx.org@2.0.4"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php yield_push('scripts') ?>
<?php yield_section('scripts') ?>
</body>
</html>
