<?php
// Component: pagination
// Props: $paginator (array from db_paginate())
$p = $paginator ?? [];
if (empty($p) || ($p['last_page'] ?? 1) <= 1) return;
$current  = (int) ($p['current_page'] ?? 1);
$last     = (int) ($p['last_page']    ?? 1);
$baseUrl  = rtrim(request_path(), '/');
?>
<nav aria-label="Pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($baseUrl) ?>?page=<?= $current - 1 ?>">‹ Prev</a>
        </li>
        <?php for ($i = max(1, $current - 2); $i <= min($last, $current + 2); $i++): ?>
        <li class="page-item <?= $i === $current ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($baseUrl) ?>?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor ?>
        <li class="page-item <?= $current >= $last ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($baseUrl) ?>?page=<?= $current + 1 ?>">Next ›</a>
        </li>
    </ul>
    <p class="text-center text-muted small">
        Showing <?= e($p['from'] ?? 0) ?>–<?= e($p['to'] ?? 0) ?> of <?= e($p['total'] ?? 0) ?> results
    </p>
</nav>
