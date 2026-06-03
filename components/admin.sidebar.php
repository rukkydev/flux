<?php
$current = request_path();
$nav = [
    ['label' => 'Dashboard', 'href' => '/admin',          'icon' => '⊞'],
    ['label' => 'Users',     'href' => '/admin/users',    'icon' => '👥'],
    ['label' => 'Settings',  'href' => '/admin/settings', 'icon' => '⚙'],
];
?>
<a class="navbar-brand" href="<?= url('/admin') ?>">
    <?= e(config('app.name')) ?>
    <span class="badge bg-primary ms-1" style="font-size:.6rem">Admin</span>
</a>

<div class="flux-sidebar-section">Main</div>
<ul class="nav flex-column gap-1">
    <?php foreach ($nav as $item): ?>
    <li class="nav-item">
        <a class="nav-link <?= str_starts_with($current, $item['href']) ? 'active' : '' ?>"
           href="<?= url($item['href']) ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <?= e($item['label']) ?>
        </a>
    </li>
    <?php endforeach ?>
</ul>

<div class="mt-auto pt-3 border-top border-white border-opacity-10 px-2">
    <div class="small text-white-50 mb-2"><?= e(session_get('auth.name', '')) ?></div>
    <a href="<?= url('/admin/logout') ?>" class="nav-link text-white-50 small">Sign out</a>
</div>
