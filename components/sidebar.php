<?php
// Component: sidebar (default dashboard sidebar)
$nav = [
    ['label' => 'Dashboard', 'href' => url('/dashboard'),      'icon' => '⊞'],
    ['label' => 'Users',     'href' => url('/admin/users'),    'icon' => '👤'],
    ['label' => 'Settings',  'href' => url('/admin/settings'), 'icon' => '⚙'],
];
$current = request_path();
?>
<ul class="nav flex-column gap-1">
    <?php foreach ($nav as $item): ?>
    <li class="nav-item">
        <a class="nav-link <?= $current === $item['href'] ? 'active' : '' ?>"
           href="<?= e($item['href']) ?>">
            <span class="me-2"><?= $item['icon'] ?></span>
            <?= e($item['label']) ?>
        </a>
    </li>
    <?php endforeach ?>
</ul>
