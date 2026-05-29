<?php
// Component: modal
// Props: $id (required), $title (optional), $size (optional: sm|lg|xl)
$id    = $id    ?? 'flux-modal';
$title = $title ?? '';
$size  = $size  ?? '';
$sizeClass = $size ? "modal-{$size}" : '';
?>
<div class="modal fade" id="<?= e($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog <?= e($sizeClass) ?>">
        <div class="modal-content">
            <?php if ($title): ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= e($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?php endif ?>
            <div class="modal-body">
                <?php yield_slot('default', '') ?>
            </div>
            <?php if (has_slot('footer')): ?>
            <div class="modal-footer">
                <?php yield_slot('footer') ?>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>
