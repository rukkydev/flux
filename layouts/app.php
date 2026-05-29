<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title()) ?></title>
    <meta name="description" content="<?= e(get_meta('description')) ?>">

    <!-- Google Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Google+Sans+Mono&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <!-- Per-page styles pushed via push('styles') -->
    <?php yield_push('styles') ?>

    <!-- Named head section -->
    <?php yield_section('head') ?>
</head>
<body>

<?php component('navbar') ?>

<main class="container py-4">

    <?php component('flash') ?>

    <?php yield_content() ?>

</main>

<?php component('footer') ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- HTMX -->
<script src="https://unpkg.com/htmx.org@2.0.4"></script>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- App JS -->
<script src="<?= asset('js/app.js') ?>"></script>

<!-- Per-page scripts pushed via push('scripts') -->
<?php yield_push('scripts') ?>

<!-- Named scripts section -->
<?php yield_section('scripts') ?>

</body>
</html>
