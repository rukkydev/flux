<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title()) ?></title>
    <?php yield_push('styles') ?>
    <?php yield_section('head') ?>
</head>
<body>
<?php yield_content() ?>
<?php yield_push('scripts') ?>
<?php yield_section('scripts') ?>
</body>
</html>
