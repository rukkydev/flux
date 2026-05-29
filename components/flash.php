<?php
// Component: flash — displays session flash messages
$flashError   = flash('error');
$flashSuccess = flash('success');
$flashInfo    = flash('info');
$flashWarning = flash('warning');
?>
<?php if ($flashError): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= e($flashError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif ?>
<?php if ($flashSuccess): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= e($flashSuccess) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif ?>
<?php if ($flashInfo): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <?= e($flashInfo) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif ?>
<?php if ($flashWarning): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <?= e($flashWarning) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif ?>
