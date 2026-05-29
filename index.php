<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Front Controller
//  All HTTP requests enter here.
// ═══════════════════════════════════════════════════════

define('FLUX_ROOT', __DIR__);

require __DIR__ . '/bootstrap/app.php';

// Dispatch the request
router_dispatch();
