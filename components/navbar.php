<?php
$brand = $brand ?? config('app.name', 'FluxPHP');
?>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="<?= url('/') ?>"><?= e($brand) ?></a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <?php if (has_slot('left')): ?>
                <ul class="navbar-nav me-auto"><?php yield_slot('left') ?></ul>
            <?php endif ?>

            <ul class="navbar-nav ms-auto">
                <?php if (has_slot('nav')): ?>
                    <?php yield_slot('nav') ?>
                <?php elseif (session_get('auth.id')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <?= e(session_get('auth.name', 'Account')) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= url('/profile') ?>">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('/logout') ?>">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('/login') ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= url('/register') ?>">Register</a></li>
                <?php endif ?>
            </ul>
        </div>
    </div>
</nav>
