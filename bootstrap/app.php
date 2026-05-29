<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Application Bootstrap
// ═══════════════════════════════════════════════════════

// ── 1. PHP baseline ───────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ── 2. Root constant ──────────────────────────────────
if (!defined('FLUX_ROOT')) {
    define('FLUX_ROOT', dirname(__DIR__));
}
define('FLUX_START', microtime(true));

// ── 3. Core files ─────────────────────────────────────
$coreFiles = [
    'core/env.php',
    'core/config.php',
    'core/helpers.php',
    'core/logger.php',
    'core/request.php',
    'core/response.php',
    'core/session.php',
    'core/security.php',
    'core/cache.php',
    'core/database.php',
    'core/validation.php',
    'core/mail.php',
    'core/api.php',
    'core/queue.php',
    'core/modules.php',
    'core/middleware.php',
    'core/renderer.php',
    'core/router.php',
];

$autoloader = FLUX_ROOT . '/vendor/autoload.php';

if (file_exists($autoloader)) {
    require $autoloader;
} else {
    foreach ($coreFiles as $file) {
        require FLUX_ROOT . '/' . $file;
    }
}

// ── 4. Environment ────────────────────────────────────
env_load(FLUX_ROOT . '/.env');

// ── 5. Config ─────────────────────────────────────────
// Load from cache in production, otherwise load from files
if (!config_cache_load()) {
    config_load(FLUX_ROOT . '/config');
}

// ── 6. PHP settings ───────────────────────────────────
ini_set('display_errors', is_debug() ? '1' : '0');
date_default_timezone_set(config('app.timezone', 'UTC'));

// ── 7. Error handlers ─────────────────────────────────
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    log_error("PHP Error [{$severity}]: {$message}", ['file' => $file, 'line' => $line]);
    if (is_debug()) {
        if (is_cli()) {
            echo "\n\033[0;33m  PHP Error [{$severity}]:\033[0m {$message}\n";
            echo "  in {$file} on line {$line}\n\n";
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

set_exception_handler(function (\Throwable $e): void {
    log_critical('Uncaught exception: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (is_cli()) {
        // Clean plain-text output for CLI
        echo "\n\033[0;31m  " . get_class($e) . "\033[0m\n";
        echo "  " . $e->getMessage() . "\n";
        echo "  in " . $e->getFile() . " on line " . $e->getLine() . "\n\n";
        if (is_debug()) {
            echo $e->getTraceAsString() . "\n";
        }
    } elseif (is_debug()) {
        // Laravel-style debug page (local only)
        http_response_code(500);
        $exception = $e;
        $message   = $e->getMessage();
        $file      = $e->getFile();
        $line      = $e->getLine();
        $trace     = $e->getTrace();
        $debugPage = base_path('pages/errors/debug.php');
        if (file_exists($debugPage)) {
            require $debugPage;
        } else {
            echo '<pre style="background:#1e1e1e;color:#f48771;padding:20px">';
            echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage());
            echo "\n\n" . htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        }
    } else {
        http_response_code(500);
        $page = base_path('pages/errors/500.php');
        file_exists($page) ? require $page : print('<h1>500 — Server Error</h1>');
    }
    exit(1);
});

// ── 8. Session ────────────────────────────────────────
if (!is_cli()) {
    session_start_flux();
}

// ── 9. Security headers ───────────────────────────────
if (!is_cli()) {
    security_headers();
}

// ── 10. Boot modules (auto-discovery) ────────────────
modules_boot();

// ── 11. Load manual routes ────────────────────────────
foreach (['routes/web.php', 'routes/api.php'] as $rf) {
    $p = FLUX_ROOT . '/' . $rf;
    if (file_exists($p)) require $p;
}

// ── 12. Load module routes ────────────────────────────
modules_load_routes();

// ── 13. Fire booted event ─────────────────────────────
event('flux.booted');
