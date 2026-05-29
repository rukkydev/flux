<?php
// Component: topbar (dashboard top bar)
?>
<header class="flux-topbar d-flex align-items-center px-4 border-bottom bg-white">
    <div class="me-auto">
        <?php yield_slot('title', '<span class="fw-semibold text-muted">' . e(page_title(false)) . '</span>') ?>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php yield_slot('actions') ?>
        <span class="text-muted small"><?= e(session_get('auth.name', '')) ?></span>
    </div>
</header>
