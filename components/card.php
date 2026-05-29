<?php
// Component: card
// Props: $title, $footer (optional), $class (optional)
$title = $title ?? '';
$class = $class ?? '';
?>
<div class="card <?= e($class) ?>">
    <?php if ($title): ?>
    <div class="card-header fw-semibold"><?= e($title) ?></div>
    <?php endif ?>
    <div class="card-body">
        <?php yield_slot('default', '') ?>
    </div>
    <?php if (has_slot('footer')): ?>
    <div class="card-footer text-muted small"><?php yield_slot('footer') ?></div>
    <?php endif ?>
</div>
