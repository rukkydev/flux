<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Rendering Engine (Phase 3 — v1.1)
//
//  Changes from v1.0:
//    - renderer_render(): slot buffer cleanup added (Bug #1)
//    - push(): warns on unknown stack names (Bug #2)
//    - renderer_render(): ob_get_level() verified after render
// ═══════════════════════════════════════════════════════

$_FLUX_LAYOUT        = null;
$_FLUX_SECTIONS      = [];
$_FLUX_SECTION       = null;
$_FLUX_STACKS        = [];
$_FLUX_STACK_CURRENT = null;
$_FLUX_SLOTS         = [];
$_FLUX_SLOT_CURRENT  = null;
$_FLUX_PAGE_TITLE    = '';
$_FLUX_META          = [];
$_FLUX_VIEW_DATA     = [];

// Known stack names — warn if developer typos a name
// Extend this list if you add new stacks to your layouts
const FLUX_KNOWN_STACKS = ['scripts', 'styles', 'head', 'footer'];

// ─────────────────────────────────────────────────────
//  LAYOUT
// ─────────────────────────────────────────────────────

function layout(string $name): void
{
    global $_FLUX_LAYOUT;
    $_FLUX_LAYOUT = $name;
}

function no_layout(): void
{
    global $_FLUX_LAYOUT;
    $_FLUX_LAYOUT = false;
}

function current_layout(): string|null|false
{
    global $_FLUX_LAYOUT;
    return $_FLUX_LAYOUT;
}

// ─────────────────────────────────────────────────────
//  PAGE TITLE & META
// ─────────────────────────────────────────────────────

function title(string $value): void
{
    global $_FLUX_PAGE_TITLE;
    $_FLUX_PAGE_TITLE = $value;
}

function page_title(bool $withApp = true): string
{
    global $_FLUX_PAGE_TITLE;
    $t = $_FLUX_PAGE_TITLE;

    if ($withApp) {
        $app = config('app.name', 'FluxPHP');
        return $t ? "{$t} — {$app}" : $app;
    }

    return $t;
}

function meta(string $key, string $value): void
{
    global $_FLUX_META;
    $_FLUX_META[$key] = $value;
}

function get_meta(string $key, string $default = ''): string
{
    global $_FLUX_META;
    return $_FLUX_META[$key] ?? $default;
}

// ─────────────────────────────────────────────────────
//  SHARED VIEW DATA
// ─────────────────────────────────────────────────────

function view_share(string $key, mixed $value): void
{
    global $_FLUX_VIEW_DATA;
    $_FLUX_VIEW_DATA[$key] = $value;
}

function view_data(): array
{
    global $_FLUX_VIEW_DATA;
    return $_FLUX_VIEW_DATA;
}

// ─────────────────────────────────────────────────────
//  SECTIONS
// ─────────────────────────────────────────────────────

function section(string $name): void
{
    global $_FLUX_SECTION;
    $_FLUX_SECTION = $name;
    ob_start();
}

function end_section(): void
{
    global $_FLUX_SECTIONS, $_FLUX_SECTION;

    if ($_FLUX_SECTION === null) {
        return;
    }

    $_FLUX_SECTIONS[$_FLUX_SECTION] = ob_get_clean();
    $_FLUX_SECTION = null;
}

function yield_section(string $name, string $default = ''): void
{
    global $_FLUX_SECTIONS;
    echo $_FLUX_SECTIONS[$name] ?? $default;
}

function has_section(string $name): bool
{
    global $_FLUX_SECTIONS;
    return isset($_FLUX_SECTIONS[$name]);
}

function yield_content(): void
{
    global $_FLUX_PAGE_CONTENT;
    echo $_FLUX_PAGE_CONTENT ?? '';
}

// ─────────────────────────────────────────────────────
//  STACKS  (push / end_push / yield_push)
// ─────────────────────────────────────────────────────

/**
 * Push content onto a named stack.
 *
 * FIX v1.1: Warns on unknown stack names so typos are caught early.
 *
 * <?php push('scripts') ?>
 *   <script src="/js/chart.js"></script>
 * <?php end_push() ?>
 */
function push(string $name): void
{
    global $_FLUX_STACK_CURRENT;

    // Warn if developer used an unknown stack name
    if (!in_array($name, FLUX_KNOWN_STACKS, true)) {
        log_warning("push(): Unknown stack name [{$name}]. Known stacks: " . implode(', ', FLUX_KNOWN_STACKS));
    }

    $_FLUX_STACK_CURRENT = $name;
    ob_start();
}

function end_push(): void
{
    global $_FLUX_STACKS, $_FLUX_STACK_CURRENT;

    if ($_FLUX_STACK_CURRENT === null) {
        return;
    }

    $_FLUX_STACKS[$_FLUX_STACK_CURRENT][] = ob_get_clean();
    $_FLUX_STACK_CURRENT = null;
}

function yield_push(string $name): void
{
    global $_FLUX_STACKS;

    foreach ($_FLUX_STACKS[$name] ?? [] as $chunk) {
        echo $chunk;
    }
}

// ─────────────────────────────────────────────────────
//  PARTIALS
// ─────────────────────────────────────────────────────

function partial(string $name, array $data = []): void
{
    global $_FLUX_VIEW_DATA;

    $rel        = str_replace('.', '/', $name);
    $candidates = [
        base_path('pages/partials/' . $rel . '.php'),
        base_path('pages/' . $rel . '.php'),
    ];

    $path = null;
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $path = $c;
            break;
        }
    }

    if ($path === null) {
        abort(500, "Partial not found: {$name}");
    }

    extract(array_merge($_FLUX_VIEW_DATA, $data), EXTR_SKIP);
    require $path;
}

// ─────────────────────────────────────────────────────
//  COMPONENTS  (with slots)
// ─────────────────────────────────────────────────────

function component(string $name, array $props = []): void
{
    global $_FLUX_VIEW_DATA;

    $candidates = [
        base_path('components/' . $name . '.php'),
        base_path('components/' . str_replace('.', '/', $name) . '.php'),
    ];

    $path = null;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $path = $candidate;
            break;
        }
    }

    if ($path === null) {
        abort(500, "Component not found: {$name}");
    }

    extract(array_merge($_FLUX_VIEW_DATA, $props), EXTR_SKIP);
    require $path;
}

function slot(string $name): void
{
    global $_FLUX_SLOT_CURRENT;
    $_FLUX_SLOT_CURRENT = $name;
    ob_start();
}

function end_slot(): void
{
    global $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT;

    if ($_FLUX_SLOT_CURRENT === null) {
        return;
    }

    $_FLUX_SLOTS[$_FLUX_SLOT_CURRENT] = ob_get_clean();
    $_FLUX_SLOT_CURRENT = null;
}

function yield_slot(string $name, string $default = ''): void
{
    global $_FLUX_SLOTS;

    $content = $_FLUX_SLOTS[$name] ?? $default;
    unset($_FLUX_SLOTS[$name]);  // single-use by design
    echo $content;
}

function has_slot(string $name): bool
{
    global $_FLUX_SLOTS;
    return isset($_FLUX_SLOTS[$name]);
}

// ─────────────────────────────────────────────────────
//  THEME
// ─────────────────────────────────────────────────────

function theme_asset(string $path, string $theme = ''): string
{
    $theme = $theme ?: config('app.theme', 'default');
    return url("themes/{$theme}/{$path}");
}

// ─────────────────────────────────────────────────────
//  INTERNAL RENDER PIPELINE
// ─────────────────────────────────────────────────────

/**
 * Render a page file through the layout pipeline.
 * Called internally by the router after dispatch.
 *
 * FIX v1.1:
 *   - Dangling slot buffer now cleaned up (was missing)
 *   - ob_get_level() verified and logged after full render
 *   - Each cleanup step restarts the main ob correctly
 */
function renderer_render(string $pageFile, array $data = []): void
{
    global $_FLUX_LAYOUT, $_FLUX_VIEW_DATA, $_FLUX_PAGE_CONTENT;
    global $_FLUX_STACKS, $_FLUX_STACK_CURRENT;
    global $_FLUX_SECTIONS, $_FLUX_SECTION;
    global $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT;

    // Reset per-request renderer state
    $_FLUX_LAYOUT       = null;
    $_FLUX_PAGE_CONTENT = '';

    $fullPath = base_path($pageFile);

    if (!file_exists($fullPath)) {
        abort(404, "Page not found: {$pageFile}");
    }

    $merged = array_merge($_FLUX_VIEW_DATA, route_params(), $data);

    // ── Step 1: Capture page output ──────────────────────────────
    $obLevelBefore = ob_get_level();
    ob_start();

    (static function () use ($fullPath, $merged) {
        global $_FLUX_LAYOUT, $_FLUX_SECTIONS, $_FLUX_SECTION,
               $_FLUX_STACKS, $_FLUX_STACK_CURRENT,
               $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT,
               $_FLUX_PAGE_TITLE, $_FLUX_META, $_FLUX_VIEW_DATA;
        extract($merged, EXTR_SKIP);
        require $fullPath;
    })();

    // ── Step 2: Safety — close any dangling buffers ───────────────
    // If the page called push()/section()/slot() without the matching
    // end_*() call, an ob_start() is still open inside the main buffer.
    // We rescue the content and close cleanly.

    // Dangling push()
    if ($_FLUX_STACK_CURRENT !== null) {
        $rescued = ob_get_clean();
        $_FLUX_STACKS[$_FLUX_STACK_CURRENT][] = $rescued;
        $_FLUX_STACK_CURRENT = null;
        log_warning("renderer_render(): Dangling push() buffer was auto-closed. Did you forget end_push()?");
        ob_start(); // restart main capture
    }

    // Dangling section()
    if ($_FLUX_SECTION !== null) {
        $rescued = ob_get_clean();
        $_FLUX_SECTIONS[$_FLUX_SECTION] = $rescued;
        $_FLUX_SECTION = null;
        log_warning("renderer_render(): Dangling section() buffer was auto-closed. Did you forget end_section()?");
        ob_start();
    }

    // FIX v1.1 — Dangling slot() (was NOT handled before)
    if ($_FLUX_SLOT_CURRENT !== null) {
        $rescued = ob_get_clean();
        $_FLUX_SLOTS[$_FLUX_SLOT_CURRENT] = $rescued;
        $_FLUX_SLOT_CURRENT = null;
        log_warning("renderer_render(): Dangling slot() buffer was auto-closed. Did you forget end_slot()?");
        ob_start();
    }

    $_FLUX_PAGE_CONTENT = ob_get_clean();

    // ── Step 3: Verify buffer level is back to baseline ──────────
    $obLevelAfter = ob_get_level();
    if ($obLevelAfter !== $obLevelBefore) {
        log_warning(sprintf(
            "renderer_render(): ob_get_level() mismatch after page capture. Before: %d, After: %d. Flushing excess buffers.",
            $obLevelBefore,
            $obLevelAfter
        ));
        // Flush extra buffers to recover cleanly
        while (ob_get_level() > $obLevelBefore) {
            ob_end_clean();
        }
    }

    // ── Step 4: Wrap in layout if one was set ────────────────────
    if ($_FLUX_LAYOUT) {
        $layoutPath = base_path('layouts/' . $_FLUX_LAYOUT . '.php');

        if (!file_exists($layoutPath)) {
            abort(500, "Layout not found: {$_FLUX_LAYOUT}");
        }

        (static function () use ($layoutPath, $merged) {
            global $_FLUX_LAYOUT, $_FLUX_SECTIONS, $_FLUX_SECTION,
                   $_FLUX_STACKS, $_FLUX_STACK_CURRENT,
                   $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT,
                   $_FLUX_PAGE_TITLE, $_FLUX_META, $_FLUX_VIEW_DATA,
                   $_FLUX_PAGE_CONTENT;
            extract($merged, EXTR_SKIP);
            require $layoutPath;
        })();

    } else {
        echo $_FLUX_PAGE_CONTENT;
    }
}
