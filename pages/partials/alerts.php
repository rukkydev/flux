<?php
// Partial: alerts — validation error bag display
if (empty($errors)) return;
?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ((array) $errors as $field => $messages): ?>
            <?php foreach ((array) $messages as $msg): ?>
                <li><?= e($msg) ?></li>
            <?php endforeach ?>
        <?php endforeach ?>
    </ul>
</div>
