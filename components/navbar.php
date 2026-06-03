<?php

// ─────────────────────────────────────────
//  Component: navbar
//  Respects auth.type:
//    'user'  → user dropdown (Profile + Logout)
//    'admin' → admin link only (no user routes)
//    guest   → Login + Register
// ─────────────────────────────────────────

$brand    = $brand ?? config('app.name', 'FluxPHP');
$authType = session_get('auth.type', 'guest');
$authName = session_get('auth.name', '');
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

            <ul class="navbar-nav ms-auto align-items-center">

                <?php if (has_slot('nav')): ?>
                    <?php yield_slot('nav') ?>

                <?php elseif ($authType === 'user'): ?>
                    <!-- User session — show user nav -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                           href="#" data-bs-toggle="dropdown">
                            <span class="navbar-avatar">
                                <?= strtoupper(substr($authName, 0, 1)) ?>
                            </span>
                            <?= e($authName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-header small text-muted">
                                    <?= e(session_get('auth.email', '')) ?>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="<?= url('/profile') ?>">
                                <i class="bi bi-person me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?= url('/dashboard') ?>">
                                <i class="bi bi-grid me-2"></i>Dashboard
                            </a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('/logout') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>

                <?php elseif ($authType === 'admin'): ?>
                    <!-- Admin session — only show admin panel link, no user routes -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2"
                           href="<?= url('/admin') ?>">
                            <i class="bi bi-shield-lock"></i>
                            Admin Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger-soft" href="<?= url('/admin/logout') ?>">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </li>

                <?php else: ?>
                    <!-- Guest — show login/register -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/login') ?>">Login</a>
                    </li>
                    <li class="nav-item ms-1">
                        <a class="btn btn-dark btn-sm px-3"
                           href="<?= url('/register') ?>">Register</a>
                    </li>
                <?php endif ?>

            </ul>
        </div>
    </div>
</nav>
