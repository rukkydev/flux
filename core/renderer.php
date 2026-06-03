<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Rendering Engine (Phase 3)
//
//  Supports:
//    layout()        — set active layout
//    section()       — open a named section
//    end_section()   — close a named section
//    yield_section() — output a section inside a layout
//    yield_content() — output main page body in layout
//    push()          — append to a stack (scripts, styles)
//    yield_push()    — output a stack in layout
//    partial()       — include a partial file
//    component()     — include a UI component with props
//    slot()          — named slot inside a component
//    end_slot()      — close a slot
//    yield_slot()    — output a slot inside a component
//    title()         — set page <title>
//    page_title()    — get current page title
//    meta()          — set a meta value
//    get_meta()      — get a meta value
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
$_FLUX_VIEW_DATA     = [];   // shared data available to all views

// ─────────────────────────────────────────────────────
//  LAYOUT
// ─────────────────────────────────────────────────────

/**
 * Set the layout for the current page.
 * Call at the top of any page file.
 *
 * layout('app');
 * layout('dashboard');
 * layout('auth');          // layouts/auth.php
 */
function layout(string $name): void
{
    global $_FLUX_LAYOUT;
    $_FLUX_LAYOUT = $name;
}

/**
 * Disable layout for the current page (raw output).
 */
function no_layout(): void
{
    global $_FLUX_LAYOUT;
    $_FLUX_LAYOUT = false;
}

/**
 * Get the active layout name.
 */
function current_layout(): string|null|false
{
    global $_FLUX_LAYOUT;
    return $_FLUX_LAYOUT;
}

// ─────────────────────────────────────────────────────
//  PAGE TITLE & META
// ─────────────────────────────────────────────────────

/**
 * Set the page <title>.
 * title('User Profile');
 */
function title(string $value): void
{
    global $_FLUX_PAGE_TITLE;
    $_FLUX_PAGE_TITLE = $value;
}

/**
 * Get the page title, with optional app name suffix.
 * page_title()           → "User Profile — FluxPHP"
 * page_title(false)      → "User Profile"
 */
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

/**
 * Set an arbitrary meta value (description, og:image, etc).
 * meta('description', 'My page description');
 */
function meta(string $key, string $value): void
{
    global $_FLUX_META;
    $_FLUX_META[$key] = $value;
}

/**
 * Get a meta value.
 */
function get_meta(string $key, string $default = ''): string
{
    global $_FLUX_META;
    return $_FLUX_META[$key] ?? $default;
}

// ─────────────────────────────────────────────────────
//  SHARED VIEW DATA
// ─────────────────────────────────────────────────────

/**
 * Share data with ALL views/layouts/components for this request.
 * Call from a module hook or bootstrap.
 *
 * view_share('auth_user', auth_user());
 */
function view_share(string $key, mixed $value): void
{
    global $_FLUX_VIEW_DATA;
    $_FLUX_VIEW_DATA[$key] = $value;
}

/**
 * Get all shared view data.
 */
function view_data(): array
{
    global $_FLUX_VIEW_DATA;
    return $_FLUX_VIEW_DATA;
}

// ─────────────────────────────────────────────────────
//  SECTIONS
// ─────────────────────────────────────────────────────

/**
 * Start capturing a named section.
 *
 * <?php section('head') ?>
 *   <link rel="stylesheet" href="...">
 * <?php end_section() ?>
 */
function section(string $name): void
{
    global $_FLUX_SECTION;
    $_FLUX_SECTION = $name;
    ob_start();
}

/**
 * End the current section capture.
 */
function end_section(): void
{
    global $_FLUX_SECTIONS, $_FLUX_SECTION;

    if ($_FLUX_SECTION === null) {
        return;
    }

    $_FLUX_SECTIONS[$_FLUX_SECTION] = ob_get_clean();
    $_FLUX_SECTION = null;
}

/**
 * Output a section inside a layout.
 * yield_section('head')
 * yield_section('sidebar', '<p>Default sidebar</p>')
 */
function yield_section(string $name, string $default = ''): void
{
    global $_FLUX_SECTIONS;
    echo $_FLUX_SECTIONS[$name] ?? $default;
}

/**
 * Check if a section has been defined.
 */
function has_section(string $name): bool
{
    global $_FLUX_SECTIONS;
    return isset($_FLUX_SECTIONS[$name]);
}

/**
 * Output the main page body inside a layout.
 */
function yield_content(): void
{
    global $_FLUX_PAGE_CONTENT;
    echo $_FLUX_PAGE_CONTENT ?? '';
}

// ─────────────────────────────────────────────────────
//  STACKS  (push/yield_push)
// ─────────────────────────────────────────────────────

/**
 * Push content onto a named stack.
 * Useful for adding per-page scripts/styles into layout slots.
 *
 * <?php push('scripts') ?>
 *   <script src="/js/chart.js"></script>
 * <?php end_push() ?>
 */
function push(string $name): void
{
    global $_FLUX_STACK_CURRENT;
    $_FLUX_STACK_CURRENT = $name;
    ob_start();
}

/**
 * End a push block.
 */
function end_push(): void
{
    global $_FLUX_STACKS, $_FLUX_STACK_CURRENT;

    if ($_FLUX_STACK_CURRENT === null) {
        return;
    }

    $_FLUX_STACKS[$_FLUX_STACK_CURRENT][] = ob_get_clean();
    $_FLUX_STACK_CURRENT = null;
}

/**
 * Output all pushed content for a stack.
 *
 * yield_push('scripts')   — in the layout, before </body>
 * yield_push('styles')    — in the layout, inside <head>
 */
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

/**
 * Include a partial template.
 * Looks in pages/partials/ first, then pages/.
 *
 * partial('alerts')
 * partial('user.card', ['user' => $user])
 */
function partial(string $name, array $data = []): void
{
    global $_FLUX_VIEW_DATA;

    $rel  = str_replace('.', '/', $name);
    $candidates = [
        base_path('pages/partials/' . $rel . '.php'),
        base_path('pages/' . $rel . '.php'),
    ];

    $path = null;
    foreach ($candidates as $c) {
        if (file_exists($c)) { $path = $c; break; }
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

/**
 * Include a UI component with optional props.
 *
 * component('card', ['title' => 'Hello'])
 * component('modal', ['id' => 'confirm-modal'])
 *
 * With slots:
 *   component('card', ['title' => 'Hello']);
 *     slot('body'); ?>  <p>Content</p>  <?php end_slot();
 *   end_component();
 */
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

/**
 * Start capturing a named slot for the current component.
 */
function slot(string $name): void
{
    global $_FLUX_SLOT_CURRENT;
    $_FLUX_SLOT_CURRENT = $name;
    ob_start();
}

/**
 * End a slot capture.
 */
function end_slot(): void
{
    global $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT;

    if ($_FLUX_SLOT_CURRENT === null) {
        return;
    }

    $_FLUX_SLOTS[$_FLUX_SLOT_CURRENT] = ob_get_clean();
    $_FLUX_SLOT_CURRENT = null;
}

/**
 * Output a slot inside a component file.
 * yield_slot('body', '<p>Default content</p>')
 */
function yield_slot(string $name, string $default = ''): void
{
    global $_FLUX_SLOTS;

    $content = $_FLUX_SLOTS[$name] ?? $default;
    unset($_FLUX_SLOTS[$name]);   // consumed — reset for reuse
    echo $content;
}

/**
 * Check if a slot was provided.
 */
function has_slot(string $name): bool
{
    global $_FLUX_SLOTS;
    return isset($_FLUX_SLOTS[$name]);
}

// ─────────────────────────────────────────────────────
//  THEME
// ─────────────────────────────────────────────────────

/**
 * Output the URL to a theme asset.
 * theme_asset('css/app.css')  →  /themes/default/css/app.css
 */
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
 */
function renderer_render(string $pageFile, array $data = []): void
{
    global $_FLUX_LAYOUT, $_FLUX_VIEW_DATA, $_FLUX_PAGE_CONTENT;

    // Reset per-request renderer state
    $_FLUX_LAYOUT       = null;
    $_FLUX_PAGE_CONTENT = '';

    $fullPath = base_path($pageFile);

    if (!file_exists($fullPath)) {
        abort(404, "Page not found: {$pageFile}");
    }

    // Merge shared data + route params + passed data
    $merged = array_merge($_FLUX_VIEW_DATA, route_params(), $data);

    // ── Step 1: Capture page output ──────────────────
    // layout(), title(), section(), push() all run INSIDE this buffer.
    // We MUST read $_FLUX_LAYOUT after ob_get_clean() — the page sets it.
    // IMPORTANT: use global variables directly — closures don't share
    // global state the same way as inline code, so we require the file
    // in the current scope using a simple include wrapper.
    ob_start();
    (static function() use ($fullPath, $merged) {
        // Bring all renderer globals into scope explicitly
        global $_FLUX_LAYOUT, $_FLUX_SECTIONS, $_FLUX_SECTION,
               $_FLUX_STACKS, $_FLUX_STACK_CURRENT,
               $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT,
               $_FLUX_PAGE_TITLE, $_FLUX_META, $_FLUX_VIEW_DATA;
        extract($merged, EXTR_SKIP);
        require $fullPath;
    })();
    $_FLUX_PAGE_CONTENT = ob_get_clean();

    // ── Step 2: Wrap in layout if one was set ─────────
    if ($_FLUX_LAYOUT) {
        $layoutPath = base_path('layouts/' . $_FLUX_LAYOUT . '.php');

        if (!file_exists($layoutPath)) {
            abort(500, "Layout not found: {$_FLUX_LAYOUT}");
        }

        (static function() use ($layoutPath, $merged) {
            global $_FLUX_LAYOUT, $_FLUX_SECTIONS, $_FLUX_SECTION,
                   $_FLUX_STACKS, $_FLUX_STACK_CURRENT,
                   $_FLUX_SLOTS, $_FLUX_SLOT_CURRENT,
                   $_FLUX_PAGE_TITLE, $_FLUX_META, $_FLUX_VIEW_DATA,
                   $_FLUX_PAGE_CONTENT;
            extract($merged, EXTR_SKIP);
            require $layoutPath;
        })();

    } else {
        // no_layout() or layout() never called — output raw
        echo $_FLUX_PAGE_CONTENT;
    }
}
