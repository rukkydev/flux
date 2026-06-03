<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Module System (Phase 5)
//
//  A module is a self-contained business domain.
//  Each module lives in modules/<name>/ and may contain:
//
//    functions.php   — public domain helpers (auto-loaded)
//    hooks.php       — event listeners, view shares (auto-loaded)
//    validation.php  — validation rule sets
//    queries.php     — complex query helpers
//    middleware.php  — domain middleware registration
//    routes.php      — manual route overrides for this module
//    config.php      — module-level config (merged into config())
//    api.php         — API-specific helpers
//    events.php      — event definitions / dispatchers
//    module.php      — module manifest (name, version, enabled)
//
//  Load order per module:
//    module.php → config.php → functions.php → validation.php
//    → queries.php → api.php → middleware.php → hooks.php
// ═══════════════════════════════════════════════════════

$_FLUX_MODULES          = [];   // registered module manifests
$_FLUX_MODULE_DISABLED  = [];   // explicitly disabled modules

// ─────────────────────────────────────────────────────
//  DISCOVERY & BOOT
// ─────────────────────────────────────────────────────

/**
 * Discover and boot all modules under /modules.
 * Called from bootstrap/app.php.
 */
function modules_boot(): void
{
    global $_FLUX_MODULES, $_FLUX_MODULE_DISABLED;

    $modulesPath = base_path('modules');

    if (!is_dir($modulesPath)) {
        return;
    }

    $dirs = glob($modulesPath . '/*', GLOB_ONLYDIR);

    if (!$dirs) {
        return;
    }

    foreach ($dirs as $dir) {
        $name = basename($dir);

        // Load manifest first
        $manifest = module_load_manifest($dir, $name);
        $_FLUX_MODULES[$name] = $manifest;

        // Check enabled flag
        if (!($manifest['enabled'] ?? true)) {
            $_FLUX_MODULE_DISABLED[] = $name;
            log_debug("Module disabled: {$name}");
            continue;
        }

        // Load in correct order
        module_load_files($dir, [
            'config.php',
            'functions.php',
            'throttle.php',
            'validation.php',
            'queries.php',
            'api.php',
            'middleware.php',
            'events.php',
            'hooks.php',
        ]);

        log_debug("Module booted: {$name}");
    }
}

/**
 * Load a module's manifest from module.php.
 * Falls back to sensible defaults if file missing.
 */
function module_load_manifest(string $dir, string $name): array
{
    $file = $dir . '/module.php';

    $defaults = [
        'name'        => $name,
        'version'     => '1.0.0',
        'description' => '',
        'author'      => '',
        'enabled'     => true,
        'requires'    => [],
    ];

    if (!file_exists($file)) {
        return $defaults;
    }

    $manifest = require $file;

    return array_merge($defaults, is_array($manifest) ? $manifest : []);
}

/**
 * Require a list of files from a module directory.
 * Uses require_once so re-booting is safe.
 */
function module_load_files(string $dir, array $files): void
{
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

/**
 * Load module route files after the main route files.
 * Called from bootstrap/app.php.
 */
function modules_load_routes(): void
{
    global $_FLUX_MODULE_DISABLED;

    foreach (glob(base_path('modules/*/routes.php')) as $file) {
        $name = basename(dirname($file));
        if (!in_array($name, $_FLUX_MODULE_DISABLED, true)) {
            require $file;
        }
    }
}

// ─────────────────────────────────────────────────────
//  MODULE REGISTRY API
// ─────────────────────────────────────────────────────

/**
 * Get all registered module manifests.
 */
function module_all(): array
{
    global $_FLUX_MODULES;
    return $_FLUX_MODULES;
}

/**
 * Get a single module's manifest.
 */
function module_get(string $name): ?array
{
    global $_FLUX_MODULES;
    return $_FLUX_MODULES[$name] ?? null;
}

/**
 * Check if a module is registered and enabled.
 */
function module_enabled(string $name): bool
{
    global $_FLUX_MODULES, $_FLUX_MODULE_DISABLED;
    return isset($_FLUX_MODULES[$name]) && !in_array($name, $_FLUX_MODULE_DISABLED, true);
}

/**
 * Check if a module exists (regardless of enabled state).
 */
function module_exists(string $name): bool
{
    return is_dir(base_path("modules/{$name}"));
}

/**
 * Require a module dependency — abort if not enabled.
 * Call from inside a module's hooks.php or functions.php.
 *
 * module_require('user');
 */
function module_require(string $name): void
{
    if (!module_enabled($name)) {
        throw new \RuntimeException("Required module [{$name}] is not enabled.");
    }
}

/**
 * Get a module config value.
 * Merges module config.php into the global config under config('modules.<name>.*').
 */
function module_config(string $module, string $key, mixed $default = null): mixed
{
    return config("modules.{$module}.{$key}", $default);
}

// ─────────────────────────────────────────────────────
//  EVENT SYSTEM
// ─────────────────────────────────────────────────────

$_FLUX_LISTENERS = [];

/**
 * Register a listener for an event.
 *
 * event_listen('user.registered', function(array $data) {
 *     // send welcome email
 * });
 */
function event_listen(string $event, callable $listener): void
{
    global $_FLUX_LISTENERS;
    $_FLUX_LISTENERS[$event][] = $listener;
}

/**
 * Dispatch an event to all registered listeners.
 *
 * event_dispatch('user.registered', ['id' => $id, 'email' => $email]);
 */
function event_dispatch(string $event, array $payload = []): void
{
    global $_FLUX_LISTENERS;

    $listeners = $_FLUX_LISTENERS[$event] ?? [];

    foreach ($listeners as $listener) {
        try {
            $listener($payload);
        } catch (\Throwable $e) {
            log_error("Event listener failed [{$event}]", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            // In debug mode — re-throw so the error page shows the real cause
            // In production — swallow so one bad listener doesn't kill the request
            if (is_debug()) {
                throw $e;
            }
        }
    }

    log_debug("Event dispatched: {$event}", ['listeners' => count($listeners)]);
}

/**
 * Alias: event() — shorthand for event_dispatch().
 *
 * event('user.registered', ['id' => $id]);
 */
function event(string $name, array $payload = []): void
{
    event_dispatch($name, $payload);
}

/**
 * Get all registered listeners (for debugging).
 */
function event_listeners(): array
{
    global $_FLUX_LISTENERS;
    return array_map(fn($listeners) => count($listeners), $_FLUX_LISTENERS);
}

/**
 * Check if any listeners are registered for an event.
 */
function event_has_listeners(string $event): bool
{
    global $_FLUX_LISTENERS;
    return !empty($_FLUX_LISTENERS[$event]);
}
