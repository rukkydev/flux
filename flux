#!/usr/bin/env php
<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP CLI — flux
//  Usage: php flux <command> [arguments]
// ═══════════════════════════════════════════════════════

define('FLUX_ROOT', __DIR__);
define('FLUX_CLI',  true);

// Bootstrap (no session/headers in CLI)
require __DIR__ . '/bootstrap/app.php';

// ─────────────────────────────────────────────────────
//  CLI Utilities
// ─────────────────────────────────────────────────────

function cli_args(): array   { return array_slice($_SERVER['argv'], 1); }
function cli_cmd(): string   { return cli_args()[0] ?? 'help'; }
function cli_arg(int $n): ?string { return cli_args()[$n] ?? null; }

function cli_out(string $msg, string $color = 'reset'): void
{
    $colors = [
        'reset'   => "\033[0m",
        'green'   => "\033[0;32m",
        'yellow'  => "\033[0;33m",
        'red'     => "\033[0;31m",
        'cyan'    => "\033[0;36m",
        'white'   => "\033[1;37m",
        'gray'    => "\033[0;90m",
        'bold'    => "\033[1m",
        'magenta' => "\033[0;35m",
    ];
    $c = $colors[$color] ?? $colors['reset'];
    $r = $colors['reset'];
    echo $c . $msg . $r . PHP_EOL;
}

function cli_info(string $msg): void    { cli_out("  {$msg}", 'cyan'); }
function cli_success(string $msg): void { cli_out("  ✓ {$msg}", 'green'); }
function cli_warn(string $msg): void    { cli_out("  ⚠ {$msg}", 'yellow'); }
function cli_error(string $msg): void   { cli_out("  ✗ {$msg}", 'red'); }
function cli_line(string $msg = ''): void { echo $msg . PHP_EOL; }

function cli_header(string $title): void
{
    $width = 56;
    $pad   = str_repeat('─', $width);
    cli_line();
    cli_out("  ┌{$pad}┐", 'cyan');
    cli_out("  │  " . str_pad("FluxPHP — {$title}", $width - 2) . "  │", 'cyan');
    cli_out("  └{$pad}┘", 'cyan');
    cli_line();
}

function cli_table(array $headers, array $rows): void
{
    // Calculate column widths
    $widths = array_map('strlen', $headers);

    foreach ($rows as $row) {
        foreach (array_values($row) as $i => $cell) {
            $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
        }
    }

    // Header
    $sep = '  +' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . '+';
    cli_line($sep);

    $headerLine = '  |';
    foreach ($headers as $i => $h) {
        $headerLine .= ' ' . str_pad($h, $widths[$i]) . ' |';
    }
    cli_out($headerLine, 'bold');
    cli_line($sep);

    // Rows
    foreach ($rows as $row) {
        $line = '  |';
        foreach (array_values($row) as $i => $cell) {
            $line .= ' ' . str_pad((string) $cell, $widths[$i]) . ' |';
        }
        cli_line($line);
    }

    cli_line($sep);
}

function cli_confirm(string $question): bool
{
    echo "  {$question} [y/N] ";
    $answer = strtolower(trim(fgets(STDIN)));
    return $answer === 'y' || $answer === 'yes';
}

function cli_ask(string $question, string $default = ''): string
{
    $hint = $default ? " [{$default}]" : '';
    echo "  {$question}{$hint}: ";
    $answer = trim(fgets(STDIN));
    return $answer !== '' ? $answer : $default;
}

function cli_ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function cli_write_file(string $path, string $content, bool $force = false): bool
{
    if (file_exists($path) && !$force) {
        cli_warn("Already exists: " . str_replace(FLUX_ROOT . '/', '', $path));
        return false;
    }

    cli_ensure_dir(dirname($path));
    file_put_contents($path, $content);
    cli_success("Created: " . str_replace(FLUX_ROOT . '/', '', $path));
    return true;
}

// ─────────────────────────────────────────────────────
//  COMMAND: help
// ─────────────────────────────────────────────────────

function cmd_help(): void
{
    cli_header('CLI Tool');

    cli_out('  Usage:', 'white');
    cli_line('    php flux <command> [arguments]');
    cli_line();

    cli_out('  System:', 'yellow');
    cli_line('    about                   Show framework info and loaded modules');
    cli_line('    serve [host] [port]     Start the PHP development server');
    cli_line('    key:generate            Generate and write APP_KEY to .env');
    cli_line();

    cli_out('  Routing:', 'yellow');
    cli_line('    route:list              List all registered routes');
    cli_line('    route:cache             Cache all routes for production');
    cli_line('    route:clear             Clear the route cache');
    cli_line();

    cli_out('  Generators:', 'yellow');
    cli_line('    make:page  <Name>       Create a new page (e.g. Admin/Users)');
    cli_line('    make:module <Name>      Create a new module (e.g. User)');
    cli_line('    make:api   <Name>       Create a new API endpoint');
    cli_line('    make:middleware <Name>  Create a new middleware');
    cli_line('    make:migration <Name>   Create a new migration file');
    cli_line('    make:seeder <Name>      Create a new seeder');
    cli_line('    make:crud  <Name>       Scaffold full CRUD (page + module + api)');
    cli_line('    make:auth               Scaffold all auth pages');
    cli_line('    make:layout <Name>      Create a new layout file');
    cli_line('    make:component <Name>   Create a new UI component');
    cli_line('    make:event <Name>       Create a new event listener');
    cli_line();

    cli_out('  Database:', 'yellow');
    cli_line('    migrate                 Run pending migrations');
    cli_line('    migrate:rollback        Roll back last migration batch');
    cli_line('    migrate:fresh           Drop all tables and re-run migrations');
    cli_line('    db:status               Show migration status table');
    cli_line('    seed                    Run all seeders');
    cli_line();

    cli_out('  Cache:', 'yellow');
    cli_line('    cache:clear             Clear all application cache');
    cli_line();

    cli_out('  Modules:', 'yellow');
    cli_line('    module:list             List all registered modules');
    cli_line();

    cli_out('  Storage:', 'yellow');
    cli_line('    storage:link            Symlink storage/uploads to public/uploads');
    cli_line();

    cli_out('  Queue:', 'yellow');
    cli_line('    queue:work [queue]      Start processing jobs from a queue');
    cli_line('    queue:status            Show job counts per queue');
    cli_line('    queue:failed            List all failed jobs');
    cli_line('    queue:flush             Delete all failed jobs');
    cli_line();

    cli_out('  Performance:', 'yellow');
    cli_line('    optimize                Cache routes + config for production');
    cli_line('    cache:clear             Clear all cache (routes, config, app)');
    cli_line('    cache:config            Cache application config');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: route:list
// ─────────────────────────────────────────────────────

function cmd_route_list(): void
{
    cli_header('Route List');

    // Load manual routes
    $webRoutes = FLUX_ROOT . '/routes/web.php';
    $apiRoutes = FLUX_ROOT . '/routes/api.php';
    if (file_exists($webRoutes)) require $webRoutes;
    if (file_exists($apiRoutes)) require $apiRoutes;

    $routes = router_list();

    if (empty($routes)) {
        cli_warn('No routes found.');
        return;
    }

    $filter = cli_arg(1);
    if ($filter) {
        $routes = array_filter($routes, fn($r) => str_contains($r['uri'], $filter));
        cli_info("Filtered by: {$filter}");
        cli_line();
    }

    cli_table(
        ['URI', 'File', 'Middleware', 'Type'],
        array_map(fn($r) => [
            $r['uri'],
            $r['file'],
            $r['middleware'] ?: '—',
            $r['type'],
        ], $routes)
    );

    cli_line();
    cli_info(count($routes) . ' route(s) found.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: route:cache
// ─────────────────────────────────────────────────────

function cmd_route_cache(): void
{
    cli_header('Route Cache');
    cli_info('Building route cache...');

    $map = router_cache_build();
    router_cache_write($map);

    cli_success('Route cache written to storage/cache/routes.cache.php');
    cli_info(count($map) . ' routes cached.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: route:clear
// ─────────────────────────────────────────────────────

function cmd_route_clear(): void
{
    cli_header('Route Cache Clear');
    router_cache_clear();
    cli_success('Route cache cleared.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: cache:clear
// ─────────────────────────────────────────────────────

function cmd_cache_clear(): void
{
    cli_header('Cache Clear');
    $count = cache_clear_all();
    cmd_route_clear_silent();
    cli_success("Cleared {$count} cache file(s).");
    cli_line();
}

function cmd_route_clear_silent(): void
{
    router_cache_clear();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:page
// ─────────────────────────────────────────────────────

function cmd_make_page(): void
{
    cli_header('Make Page');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Page name (e.g. Admin/Users, user/profile)');
    }
    if (!$name) {
        cli_error('Page name is required.');
        return;
    }

    $path    = str_replace('\\', '/', $name);
    $file    = FLUX_ROOT . '/pages/' . $path . '.php';
    $uri     = '/' . strtolower($path);
    $display = basename($path);

    $stub = <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Page: {$display}
    //  URI:  {$uri}
    // ─────────────────────────────────────────

    layout('app');

    ?>

    <div class="py-4">
        <h1>{$display}</h1>
        <p class="text-muted">Welcome to the {$display} page.</p>
    </div>
    PHP;

    cli_write_file($file, $stub);
    cli_line();
    cli_info("Route (auto):  {$uri}");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:module
// ─────────────────────────────────────────────────────

function cmd_make_module(): void
{
    cli_header('Make Module');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Module name (e.g. User, Property)');
    }
    if (!$name) {
        cli_error('Module name is required.');
        return;
    }

    $slug    = strtolower($name);
    $prefix  = $slug . '_';
    $table   = $slug . 's';
    $base    = FLUX_ROOT . '/modules/' . $slug;

    // functions.php
    cli_write_file($base . '/functions.php', <<<PHP
    <?php

    declare(strict_types=1);

    // ─────────────────────────────────────────
    //  Module: {$name}
    //  Business logic and helper functions
    // ─────────────────────────────────────────

    function {$prefix}find(int|string \$id): ?array
    {
        return db_find('{$table}', \$id);
    }

    function {$prefix}all(): array
    {
        return db_all('{$table}');
    }

    function {$prefix}create(array \$data): string
    {
        \$data['created_at'] = date('Y-m-d H:i:s');
        \$data['updated_at'] = date('Y-m-d H:i:s');
        return db_insert('{$table}', \$data);
    }

    function {$prefix}update(int|string \$id, array \$data): int
    {
        \$data['updated_at'] = date('Y-m-d H:i:s');
        return db_update('{$table}', \$id, \$data);
    }

    function {$prefix}delete(int|string \$id): int
    {
        return db_delete('{$table}', \$id);
    }

    function {$prefix}exists(int|string \$id): bool
    {
        return db_exists('{$table}', ['id' => \$id]);
    }

    function {$prefix}paginate(int \$page = 1, int \$perPage = 15): array
    {
        return db_paginate('{$table}', \$page, \$perPage);
    }
    PHP);

    // validation.php
    cli_write_file($base . '/validation.php', <<<PHP
    <?php

    declare(strict_types=1);

    // ─────────────────────────────────────────
    //  Module: {$name} — Validation Rules
    // ─────────────────────────────────────────

    function {$prefix}validate_create(array \$data): array
    {
        return validate(\$data, [
            'name' => 'required|min:2|max:255',
        ]);
    }

    function {$prefix}validate_update(array \$data): array
    {
        return validate(\$data, [
            'name' => 'sometimes|min:2|max:255',
        ]);
    }
    PHP);

    // hooks.php
    cli_write_file($base . '/hooks.php', <<<PHP
    <?php

    declare(strict_types=1);

    // ─────────────────────────────────────────
    //  Module: {$name} — Event Hooks
    // ─────────────────────────────────────────

    // event_listen('{$slug}.created', function(array \$data) {
    //     log_info('{$name} created', \$data);
    // });
    PHP);

    // config.php
    cli_write_file($base . '/config.php', <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Module: {$name} — Config
    // ─────────────────────────────────────────

    return [
        'table'    => '{$table}',
        'per_page' => 15,
    ];
    PHP);

    cli_line();
    cli_success("Module '{$name}' scaffolded at modules/{$slug}/");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:api
// ─────────────────────────────────────────────────────

function cmd_make_api(): void
{
    cli_header('Make API Endpoint');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('API endpoint path (e.g. user/profile, posts/[id])');
    }

    $path = str_replace('\\', '/', strtolower($name));
    $file = FLUX_ROOT . '/api/' . $path . '.php';
    $uri  = '/api/' . $path;

    cli_write_file($file, <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  API: {$uri}
    // ─────────────────────────────────────────

    // middleware('api.auth');

    if (request_is('GET')) {
        response_success([], 'OK');
    }

    if (request_is('POST')) {
        \$data = request_all();
        // validate, process, respond
        response_created(null, 'Created');
    }

    response_error('Method not allowed', 405);
    PHP);

    cli_line();
    cli_info("API route (auto):  {$uri}");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:middleware
// ─────────────────────────────────────────────────────

function cmd_make_middleware(): void
{
    cli_header('Make Middleware');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Middleware name (e.g. verified, subscription)');
    }

    $slug = strtolower($name);
    $file = FLUX_ROOT . '/middleware/' . $slug . '.php';

    cli_write_file($file, <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Middleware: {$name}
    //  Register in bootstrap/app.php:
    //  middleware_register('{$slug}', function() {
    //      require base_path('middleware/{$slug}.php');
    //  });
    // ─────────────────────────────────────────

    // Add your middleware logic here.
    // Call abort() or redirect() to halt the request.

    // Example:
    // if (!session_get('user.verified')) {
    //     flash('error', 'Please verify your email first.');
    //     redirect('/email/verify');
    // }
    PHP);

    cli_line();
    cli_info("Register in bootstrap/app.php:");
    cli_line("    middleware_register('{$slug}', function() {");
    cli_line("        require base_path('middleware/{$slug}.php');");
    cli_line("    });");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:migration
// ─────────────────────────────────────────────────────

function cmd_make_migration(): void
{
    cli_header('Make Migration');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Migration name (e.g. create_users_table, add_email_to_posts)');
    }

    $slug      = strtolower(str_replace(' ', '_', $name));
    $timestamp = date('Y_m_d_His');
    $file      = FLUX_ROOT . '/database/migrations/' . $timestamp . '_' . $slug . '.php';

    $fnName = 'migration_' . $slug;

    cli_write_file($file, <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Migration: {$name}
    //  Created: {$timestamp}
    // ─────────────────────────────────────────

    function {$fnName}_up(): void
    {
        db_execute("
            CREATE TABLE IF NOT EXISTS `example` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(255)    NOT NULL,
                `created_at` DATETIME        NOT NULL,
                `updated_at` DATETIME        NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    function down(): void
    {
        db_execute("DROP TABLE IF EXISTS `example`");
    }
    PHP);

    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:seeder
// ─────────────────────────────────────────────────────

function cmd_make_seeder(): void
{
    cli_header('Make Seeder');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Seeder name (e.g. UsersSeeder)');
    }

    $file = FLUX_ROOT . '/database/seeders/' . $name . '.php';

    cli_write_file($file, <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Seeder: {$name}
    // ─────────────────────────────────────────

    function run(): void
    {
        db_insert('example', [
            'name'       => 'Sample Record',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    run();
    PHP);

    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:crud
// ─────────────────────────────────────────────────────

function cmd_make_crud(): void
{
    cli_header('Make CRUD Scaffold');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Resource name (e.g. Property, Post, Product)');
    }

    $slug   = strtolower($name);
    $plural = $slug . 's';
    $prefix = $slug . '_';

    cli_info("Scaffolding CRUD for: {$name}");
    cli_line();

    // Module
    $_SERVER['argv'] = ['flux', 'make:module', $name];
    cmd_make_module();

    // Pages
    $pages = [
        "pages/{$plural}/index.php"        => cmd_crud_stub_index($name, $slug, $plural),
        "pages/{$plural}/create.php"       => cmd_crud_stub_create($name, $slug, $plural),
        "pages/{$plural}/[id]/show.php"    => cmd_crud_stub_show($name, $slug, $plural),
        "pages/{$plural}/[id]/edit.php"    => cmd_crud_stub_edit($name, $slug, $plural),
    ];

    foreach ($pages as $rel => $content) {
        cli_write_file(FLUX_ROOT . '/' . $rel, $content);
    }

    // API endpoints
    $apis = [
        "api/{$plural}/index.php"    => cmd_crud_stub_api_index($name, $slug, $plural, $prefix),
        "api/{$plural}/store.php"    => cmd_crud_stub_api_store($name, $slug, $plural, $prefix),
        "api/{$plural}/[id]/show.php"  => cmd_crud_stub_api_show($name, $slug, $plural, $prefix),
        "api/{$plural}/[id]/update.php"=> cmd_crud_stub_api_update($name, $slug, $plural, $prefix),
        "api/{$plural}/[id]/delete.php"=> cmd_crud_stub_api_delete($name, $slug, $plural, $prefix),
    ];

    foreach ($apis as $rel => $content) {
        cli_write_file(FLUX_ROOT . '/' . $rel, $content);
    }

    cli_line();
    cli_success("CRUD scaffold complete for '{$name}'");
    cli_line();
    cli_info("Pages:   /{$plural}, /{$plural}/create, /{$plural}/[id]/show, /{$plural}/[id]/edit");
    cli_info("API:     /api/{$plural} (index, store, show, update, delete)");
    cli_line();
}

function cmd_crud_stub_index(string $name, string $slug, string $plural): string
{
    return <<<PHP
    <?php
    title('{$name}s');
    layout('app');
    \$page    = (int) (request('page') ?? 1);
    \$results = {$slug}_paginate(\$page);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">{$name}s</h1>
        <a href="<?= url('/{$plural}/create') ?>" class="btn btn-dark">+ New {$name}</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (\$results['data'] as \$item): ?>
                    <tr>
                        <td class="text-muted small"><?= e(\$item['id']) ?></td>
                        <td><?= e(\$item['name'] ?? '') ?></td>
                        <td class="text-muted small"><?= e(\$item['created_at'] ?? '') ?></td>
                        <td class="text-end">
                            <a href="<?= url('/{$plural}/' . \$item['id'] . '/show') ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="<?= url('/{$plural}/' . \$item['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (empty(\$results['data'])): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No {$plural} found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3"><?php component('pagination', ['paginator' => \$results]) ?></div>
    PHP;
}

function cmd_crud_stub_create(string $name, string $slug, string $plural): string
{
    return <<<PHP
    <?php
    layout('app');
    if (request_is('POST')) {
        \$data = request_only(['name']);
        \$id   = {$slug}_create(\$data);
        flash('success', '{$name} created.');
        redirect('/{$plural}/' . \$id . '/show');
    }
    ?>
    <h1>New {$name}</h1>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= e(old('name')) ?>">
        </div>
        <button class="btn btn-dark">Create {$name}</button>
        <a href="/{$plural}" class="btn btn-link">Cancel</a>
    </form>
    PHP;
}

function cmd_crud_stub_show(string $name, string $slug, string $plural): string
{
    return <<<PHP
    <?php
    layout('app');
    \$item = {$slug}_find((int) route_param('id'));
    if (!\$item) abort(404, '{$name} not found.');
    ?>
    <h1><?= e(\$item['name'] ?? '{$name}') ?></h1>
    <p class="text-muted">ID: <?= e(\$item['id']) ?></p>
    <a href="/{$plural}/<?= e(\$item['id']) ?>/edit" class="btn btn-outline-primary">Edit</a>
    <a href="/{$plural}" class="btn btn-link">Back</a>
    PHP;
}

function cmd_crud_stub_edit(string $name, string $slug, string $plural): string
{
    return <<<PHP
    <?php
    layout('app');
    \$item = {$slug}_find((int) route_param('id'));
    if (!\$item) abort(404, '{$name} not found.');
    if (request_is('POST')) {
        \$data = request_only(['name']);
        {$slug}_update(\$item['id'], \$data);
        flash('success', '{$name} updated.');
        redirect('/{$plural}/' . \$item['id'] . '/show');
    }
    ?>
    <h1>Edit {$name}</h1>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="POST">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= e(old('name', \$item['name'] ?? '')) ?>">
        </div>
        <button class="btn btn-dark">Save Changes</button>
        <a href="/{$plural}/<?= e(\$item['id']) ?>/show" class="btn btn-link">Cancel</a>
    </form>
    PHP;
}

function cmd_crud_stub_api_index(string $name, string $slug, string $plural, string $prefix): string
{
    return "<?php\n// GET /api/{$plural}\nresponse_success({$prefix}all());\n";
}

function cmd_crud_stub_api_store(string $name, string $slug, string $plural, string $prefix): string
{
    return "<?php\n// POST /api/{$plural}\n\$data = request_all();\n\$id = {$prefix}create(\$data);\nresponse_created({$prefix}find(\$id));\n";
}

function cmd_crud_stub_api_show(string $name, string $slug, string $plural, string $prefix): string
{
    return "<?php\n// GET /api/{$plural}/[id]\n\$item = {$prefix}find((int) route_param('id'));\nif (!\$item) response_not_found();\nresponse_success(\$item);\n";
}

function cmd_crud_stub_api_update(string $name, string $slug, string $plural, string $prefix): string
{
    return "<?php\n// POST /api/{$plural}/[id]/update\n\$item = {$prefix}find((int) route_param('id'));\nif (!\$item) response_not_found();\n{$prefix}update(\$item['id'], request_all());\nresponse_success({$prefix}find(\$item['id']));\n";
}

function cmd_crud_stub_api_delete(string $name, string $slug, string $plural, string $prefix): string
{
    return "<?php\n// POST /api/{$plural}/[id]/delete\n\$item = {$prefix}find((int) route_param('id'));\nif (!\$item) response_not_found();\n{$prefix}delete(\$item['id']);\nresponse_no_content();\n";
}

// ─────────────────────────────────────────────────────
//  COMMAND: migrate
// ─────────────────────────────────────────────────────

function cmd_migrate(): void
{
    cli_header('Database Migrations');

    // Ensure migrations table exists
    db_execute("
        CREATE TABLE IF NOT EXISTS `_migrations` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration`  VARCHAR(255) NOT NULL,
            `batch`      INT UNSIGNED NOT NULL,
            `ran_at`     DATETIME     NOT NULL,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add missing columns if table existed before this fix
    $cols = db_schema_columns('_migrations');
    if (!in_array('created_at', $cols)) {
        db_execute("ALTER TABLE `_migrations` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('updated_at', $cols)) {
        db_execute("ALTER TABLE `_migrations` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    $ran   = array_column(db_select("SELECT migration FROM `_migrations`"), 'migration');
    $files = glob(FLUX_ROOT . '/database/migrations/*.php');
    sort($files);

    $batch   = (int) (db_value("SELECT MAX(batch) FROM `_migrations`") ?? 0) + 1;
    $pending = array_filter($files, fn($f) => !in_array(basename($f), $ran));

    if (empty($pending)) {
        cli_info('Nothing to migrate.');
        return;
    }

    foreach ($pending as $file) {
        $name = basename($file);
        cli_info("Running: {$name}");

        require_once $file;

        // Derive unique function name from filename
        // e.g. 2024_01_01_000001_create_users_table.php
        //   -> migration_create_users_table_up()
        $slug   = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($name, '.php'));
        $upFn   = 'migration_' . $slug . '_up';

        if (function_exists($upFn)) {
            $upFn();
        } else {
            cli_warn("No up() function found in: {$name}");
        }

        db_insert('_migrations', [
            'migration' => $name,
            'batch'     => $batch,
            'ran_at'    => date('Y-m-d H:i:s'),
        ]);

        cli_success("Done: {$name}");
    }

    cli_line();
    cli_success("Batch {$batch} complete. " . count($pending) . " migration(s) ran.");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: migrate:rollback
// ─────────────────────────────────────────────────────

function cmd_migrate_rollback(): void
{
    cli_header('Migration Rollback');

    $batch = (int) (db_value("SELECT MAX(batch) FROM `_migrations`") ?? 0);

    if ($batch === 0) {
        cli_warn('Nothing to roll back.');
        return;
    }

    $rows = db_select("SELECT migration FROM `_migrations` WHERE batch = ? ORDER BY id DESC", [$batch]);

    foreach ($rows as $row) {
        $file = FLUX_ROOT . '/database/migrations/' . $row['migration'];
        cli_info("Rolling back: {$row['migration']}");

        if (file_exists($file)) {
            require_once $file;

            $slug   = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($row['migration'], '.php'));
            $downFn = 'migration_' . $slug . '_down';

            if (function_exists($downFn)) {
                $downFn();
            } else {
                cli_warn("No down() function found in: {$row['migration']}");
            }
        }

        db_delete_where('_migrations', ['migration' => $row['migration']]);
        cli_success("Rolled back: {$row['migration']}");
    }

    cli_line();
    cli_success("Batch {$batch} rolled back.");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: migrate:fresh
// ─────────────────────────────────────────────────────

function cmd_migrate_fresh(): void
{
    cli_header('Fresh Migration');

    if (!cli_confirm('This will DROP ALL TABLES. Continue?')) {
        cli_warn('Aborted.');
        return;
    }

    // Drop all tables
    $tables = db_select("SHOW TABLES");
    db_execute("SET FOREIGN_KEY_CHECKS=0");
    foreach ($tables as $row) {
        $table = reset($row);
        db_execute("DROP TABLE IF EXISTS `{$table}`");
        cli_info("Dropped: {$table}");
    }
    db_execute("SET FOREIGN_KEY_CHECKS=1");

    cli_line();
    cmd_migrate();
}

// ─────────────────────────────────────────────────────
//  COMMAND: seed
// ─────────────────────────────────────────────────────

function cmd_seed(): void
{
    cli_header('Database Seeder');

    $files = glob(FLUX_ROOT . '/database/seeders/*.php');

    if (empty($files)) {
        cli_warn('No seeders found.');
        return;
    }

    foreach ($files as $file) {
        $name = basename($file);
        cli_info("Running seeder: {$name}");
        require $file;
        cli_success("Done: {$name}");
    }

    cli_line();
    cli_success(count($files) . ' seeder(s) ran.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: module:list
// ─────────────────────────────────────────────────────

function cmd_module_list(): void
{
    cli_header('Module List');

    $dirs = glob(FLUX_ROOT . '/modules/*', GLOB_ONLYDIR);

    if (empty($dirs)) {
        cli_warn('No modules found.');
        return;
    }

    $rows = [];
    foreach ($dirs as $dir) {
        $name  = basename($dir);
        $files = array_map('basename', glob($dir . '/*.php') ?: []);
        $rows[] = [
            'module' => $name,
            'files'  => implode(', ', $files),
        ];
    }

    cli_table(['Module', 'Files'], $rows);
    cli_line();
    cli_info(count($dirs) . ' module(s) found.');
    cli_line();
}


// ─────────────────────────────────────────────────────
//  COMMAND: about
// ─────────────────────────────────────────────────────

function cmd_about(): void
{
    cli_header('About FluxPHP');

    $modules = module_all();
    $enabled = array_filter($modules, fn($m) => $m['enabled'] ?? true);

    cli_out('  Framework', 'yellow');
    cli_line('    Name:        FluxPHP');
    cli_line('    Version:     1.0.0');
    cli_line('    PHP:         ' . PHP_VERSION);
    cli_line('    OS:          ' . PHP_OS);
    cli_line('    Environment: ' . config('app.env', 'local'));
    cli_line('    Debug:       ' . (is_debug() ? 'true' : 'false'));
    cli_line('    Root:        ' . FLUX_ROOT);
    cli_line();

    cli_out('  Database', 'yellow');
    cli_line('    Driver:   ' . config('database.default', 'mysql'));
    cli_line('    Host:     ' . config('database.connections.mysql.host', '—'));
    cli_line('    Database: ' . config('database.connections.mysql.database', '—'));
    cli_line();

    cli_out('  Modules (' . count($enabled) . ' loaded)', 'yellow');
    foreach ($enabled as $name => $manifest) {
        cli_line('    ✓ ' . $name . ' v' . ($manifest['version'] ?? '1.0.0'));
    }
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: key:generate
// ─────────────────────────────────────────────────────

function cmd_key_generate(): void
{
    cli_header('Generate App Key');

    $key     = 'base64:' . base64_encode(random_bytes(32));
    $envFile = FLUX_ROOT . '/.env';

    if (!file_exists($envFile)) {
        cli_error('.env file not found.');
        return;
    }

    $content = file_get_contents($envFile);

    if (str_contains($content, 'APP_KEY=')) {
        $content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content);
    } else {
        $content .= "
APP_KEY={$key}
";
    }

    file_put_contents($envFile, $content);

    cli_success("APP_KEY set: {$key}");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: serve
// ─────────────────────────────────────────────────────

function cmd_serve(): void
{
    cli_header('Development Server');

    $host = cli_arg(1) ?? 'localhost';
    $port = cli_arg(2) ?? '3000';

    cli_success("FluxPHP development server started.");
    cli_info("URL:  http://{$host}:{$port}");
    cli_info("Root: " . FLUX_ROOT);
    cli_line();
    cli_warn("Note: url() and asset() auto-detect the base path.");
    cli_warn("APP_URL in .env is only used for emails/webhooks.");
    cli_line();
    cli_out('  Press Ctrl+C to stop.', 'gray');
    cli_line();

    passthru("php -S {$host}:{$port} -t " . escapeshellarg(FLUX_ROOT));
}

// ─────────────────────────────────────────────────────
//  COMMAND: db:status
// ─────────────────────────────────────────────────────

function cmd_db_status(): void
{
    cli_header('Migration Status');

    // Check _migrations table exists
    if (!db_schema_has_table('_migrations')) {
        cli_warn('Migrations table does not exist yet. Run: php flux migrate');
        return;
    }

    $ran   = db_select("SELECT migration, batch, ran_at FROM `_migrations` ORDER BY id ASC");
    $files = glob(FLUX_ROOT . '/database/migrations/*.php') ?: [];
    sort($files);

    $ranNames = array_column($ran, 'migration');
    $rows     = [];

    foreach ($files as $file) {
        $name = basename($file);
        $idx  = array_search($name, $ranNames);

        if ($idx !== false) {
            $rows[] = [
                'migration' => $name,
                'batch'     => $ran[$idx]['batch'],
                'ran_at'    => $ran[$idx]['ran_at'],
                'status'    => 'Ran',
            ];
        } else {
            $rows[] = [
                'migration' => $name,
                'batch'     => '—',
                'ran_at'    => '—',
                'status'    => 'Pending',
            ];
        }
    }

    cli_table(['Migration', 'Batch', 'Ran At', 'Status'], $rows);
    cli_line();

    $pending = count(array_filter($rows, fn($r) => $r['status'] === 'Pending'));
    $done    = count(array_filter($rows, fn($r) => $r['status'] === 'Ran'));

    cli_info("{$done} ran, {$pending} pending.");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:auth
// ─────────────────────────────────────────────────────

function cmd_make_auth(): void
{
    cli_header('Scaffold Auth Pages');

    cli_info('Scaffolding auth pages...');
    cli_line();

    $pages = [
        'pages/auth/login.php'          => cmd_stub_auth_login(),
        'pages/auth/register.php'       => cmd_stub_auth_register(),
        'pages/auth/logout.php'         => cmd_stub_auth_logout(),
        'pages/auth/forgot-password.php'=> cmd_stub_auth_forgot(),
    ];

    foreach ($pages as $rel => $content) {
        cli_write_file(FLUX_ROOT . '/' . $rel, $content);
    }

    // reset-password dynamic page
    cli_write_file(
        FLUX_ROOT . '/pages/reset-password/[token].php',
        cmd_stub_auth_reset()
    );

    cli_line();
    cli_success('Auth pages scaffolded.');
    cli_line();
    cli_info('Add to routes/web.php:');
    cli_line("    route('/login',           'pages/auth/login.php',            ['guest']);");
    cli_line("    route('/register',        'pages/auth/register.php',         ['guest']);");
    cli_line("    route('/logout',          'pages/auth/logout.php',           ['auth']);");
    cli_line("    route('/forgot-password', 'pages/auth/forgot-password.php',  ['guest']);");
    cli_line();
}

function cmd_stub_auth_login(): string
{
    return <<<'PHP'
<?php
middleware('guest');
title('Login');
layout('auth');
if (request_is('POST')) {
    $data   = request_only(['email', 'password', 'remember']);
    $errors = validation_run($data, ['email' => 'required|email', 'password' => 'required']);
    if (!empty($errors)) { $_SESSION['_errors'] = $errors; $_SESSION['_old_input'] = $data; back(); }
    if (auth_throttle_exceeded($data['email'])) { flash('error', 'Too many attempts. Please wait.'); back(); }
    if (!auth_attempt($data['email'], $data['password'], !empty($data['remember']))) {
        flash('error', 'Invalid credentials.');
        $_SESSION['_old_input'] = $data;
        back();
    }
    redirect(url('/dashboard'));
}
$errors = validation_errors();
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Sign in</h4>
        <p class="text-muted small mb-4">Welcome back.</p>
        <?php partial('alerts', ['errors' => $errors]) ?>
        <form method="POST" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control <?= error_class('email') ?>" value="<?= e(old('email')) ?>" autofocus>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label class="form-label">Password</label>
                    <a href="<?= url('/forgot-password') ?>" class="small text-muted">Forgot?</a>
                </div>
                <input type="password" name="password" class="form-control <?= error_class('password') ?>">
            </div>
            <div class="mb-4 form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember" value="1">
                <label class="form-check-label text-muted small" for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn btn-dark w-100">Sign in</button>
        </form>
    </div>
</div>
<p class="text-center text-muted small mt-3">
    No account? <a href="<?= url('/register') ?>" class="text-dark fw-medium">Register</a>
</p>
PHP;
}

function cmd_stub_auth_register(): string
{
    return <<<'PHP'
<?php
middleware('guest');
title('Register');
layout('auth');
if (request_is('POST')) {
    $data   = request_only(['name', 'email', 'password', 'password_confirmation']);
    $errors = validation_run($data, [
        'name'                  => 'required|min:2|max:255',
        'email'                 => 'required|email|max:255',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);
    if (empty($errors) && user_exists($data['email'])) { $errors['email'][] = 'Email already registered.'; }
    if (!empty($errors)) { $_SESSION['_errors'] = $errors; $_SESSION['_old_input'] = $data; back(); }
    $id = user_create(arr_except($data, ['password_confirmation']));
    auth_login(user_find($id));
    flash('success', 'Welcome!');
    redirect(url('/dashboard'));
}
$errors = validation_errors();
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Create account</h4>
        <p class="text-muted small mb-4">Get started free.</p>
        <?php partial('alerts', ['errors' => $errors]) ?>
        <form method="POST" action="<?= url('/register') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control <?= error_class('name') ?>" value="<?= e(old('name')) ?>" autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control <?= error_class('email') ?>" value="<?= e(old('email')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control <?= error_class('password') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control">
            </div>
            <button type="submit" class="btn btn-dark w-100">Create account</button>
        </form>
    </div>
</div>
<p class="text-center text-muted small mt-3">
    Have an account? <a href="<?= url('/login') ?>" class="text-dark fw-medium">Sign in</a>
</p>
PHP;
}

function cmd_stub_auth_logout(): string
{
    return <<<'PHP'
<?php
middleware('auth');
auth_logout();
flash('success', 'You have been logged out.');
redirect(url('/login'));
PHP;
}

function cmd_stub_auth_forgot(): string
{
    return <<<'PHP'
<?php
middleware('guest');
title('Forgot Password');
layout('auth');
if (request_is('POST')) {
    $data   = request_only(['email']);
    $errors = validation_run($data, ['email' => 'required|email']);
    if (!empty($errors)) { $_SESSION['_errors'] = $errors; back(); }
    $user = user_find_by_email($data['email']);
    if ($user) {
        $token = generate_token(32);
        db_delete_where('password_resets', ['email' => $user['email']]);
        db_insert('password_resets', [
            'email'      => $user['email'],
            'token'      => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        event('auth.password_reset_requested', ['user' => $user, 'token' => $token]);
    }
    flash('success', 'If that email exists, a reset link has been sent.');
    redirect(url('/forgot-password'));
}
$errors = validation_errors();
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Forgot password?</h4>
        <p class="text-muted small mb-4">We'll send you a reset link.</p>
        <?php partial('alerts', ['errors' => $errors]) ?>
        <form method="POST" action="<?= url('/forgot-password') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control <?= error_class('email') ?>" autofocus>
            </div>
            <button type="submit" class="btn btn-dark w-100">Send reset link</button>
        </form>
    </div>
</div>
<p class="text-center text-muted small mt-3"><a href="<?= url('/login') ?>" class="text-dark">← Back to login</a></p>
PHP;
}

function cmd_stub_auth_reset(): string
{
    return <<<'PHP'
<?php
middleware('guest');
title('Reset Password');
layout('auth');
$token  = route_param('token');
$record = db_first("SELECT * FROM `password_resets` WHERE token = ? AND expires_at > NOW() LIMIT 1", [hash('sha256', $token)]);
if (!$record) { flash('error', 'This reset link is invalid or has expired.'); redirect(url('/forgot-password')); }
if (request_is('POST')) {
    $data   = request_only(['password', 'password_confirmation']);
    $errors = validation_run($data, ['password' => 'required|min:8|confirmed', 'password_confirmation' => 'required']);
    if (!empty($errors)) { $_SESSION['_errors'] = $errors; back(); }
    $user = user_find_by_email($record['email']);
    if ($user) {
        db_update('users', $user['id'], ['password' => password_make($data['password']), 'remember_token' => null]);
        db_delete_where('password_resets', ['email' => $record['email']]);
        event('auth.password_reset', ['user_id' => $user['id']]);
    }
    flash('success', 'Password updated. Please log in.');
    redirect(url('/login'));
}
$errors = validation_errors();
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1">Set new password</h4>
        <?php partial('alerts', ['errors' => $errors]) ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <input type="password" name="password" class="form-control <?= error_class('password') ?>" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm password</label>
                <input type="password" name="password_confirmation" class="form-control">
            </div>
            <button type="submit" class="btn btn-dark w-100">Update password</button>
        </form>
    </div>
</div>
PHP;
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:layout
// ─────────────────────────────────────────────────────

function cmd_make_layout(): void
{
    cli_header('Make Layout');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Layout name (e.g. marketing, docs)');
    }

    $file = FLUX_ROOT . '/layouts/' . strtolower($name) . '.php';

    cli_write_file($file, <<<PHP
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(page_title()) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
        <?php yield_push('styles') ?>
    </head>
    <body>
        <?php yield_content() ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <?php yield_push('scripts') ?>
    </body>
    </html>
    PHP);

    cli_line();
    cli_info("Use in pages:  layout('{$name}');");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:component
// ─────────────────────────────────────────────────────

function cmd_make_component(): void
{
    cli_header('Make Component');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Component name (e.g. alert, hero, stat-card)');
    }

    $slug = strtolower($name);
    $file = FLUX_ROOT . '/components/' . $slug . '.php';

    cli_write_file($file, <<<PHP
    <?php
    // Component: {$slug}
    // Props: \$title (optional)
    \$title = \$title ?? '';
    ?>
    <div class="flux-{$slug}">
        <?php if (\$title): ?>
            <h5><?= e(\$title) ?></h5>
        <?php endif ?>
        <?php yield_slot('default', '') ?>
    </div>
    PHP);

    cli_line();
    cli_info("Use in pages:  component('{$slug}', ['title' => '...'])");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: make:event
// ─────────────────────────────────────────────────────

function cmd_make_event(): void
{
    cli_header('Make Event Listener');

    $name = cli_arg(1);
    if (!$name) {
        $name = cli_ask('Event name (e.g. user.registered, order.placed)');
    }

    $slug = str_replace(['.', '-', ' '], '_', strtolower($name));
    $file = FLUX_ROOT . '/modules/' . explode('_', $slug)[0] . '/events.php';

    $stub = <<<PHP
    <?php

    // ─────────────────────────────────────────
    //  Event Listener: {$name}
    // ─────────────────────────────────────────

    event_listen('{$name}', function(array \$payload): void {
        log_info('Event: {$name}', \$payload);

        // Add your listener logic here
    });
    PHP;

    if (file_exists($file)) {
        // Append to existing events file
        file_put_contents($file, "

" . $stub, FILE_APPEND);
        cli_success("Appended listener to: " . str_replace(FLUX_ROOT . '/', '', $file));
    } else {
        cli_write_file($file, "<?php

" . $stub);
    }

    cli_line();
    cli_info("Dispatch with:  event('{$name}', \$payload)");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: storage:link  (future-ready)
// ─────────────────────────────────────────────────────

function cmd_storage_link(): void
{
    cli_header('Storage Link');

    $target = FLUX_ROOT . '/storage/uploads';
    $link   = FLUX_ROOT . '/public/uploads';

    if (file_exists($link)) {
        cli_warn('Link already exists: public/uploads');
        return;
    }

    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }

    if (symlink($target, $link)) {
        cli_success('Symlink created: public/uploads → storage/uploads');
    } else {
        cli_error('Failed to create symlink. Try running as administrator.');
    }

    cli_line();
}


// ─────────────────────────────────────────────────────
//  COMMAND: queue:work
// ─────────────────────────────────────────────────────

function cmd_queue_work(): void
{
    cli_header('Queue Worker');

    $queue = cli_arg(1) ?? 'default';
    $limit = (int) (cli_arg(2) ?? 0); // 0 = run forever
    $count = 0;

    cli_info("Processing queue: [{$queue}]");
    cli_info("Press Ctrl+C to stop.");
    cli_line();

    while (true) {
        $job = queue_next($queue);

        if (!$job) {
            sleep(1);
            continue;
        }

        cli_info("Processing: {$job['handler']} [{$job['id']}]");

        $success = queue_process($job);

        if ($success) {
            cli_success("Done: {$job['handler']}");
        } else {
            cli_error("Failed: {$job['handler']}");
        }

        $count++;

        if ($limit > 0 && $count >= $limit) {
            cli_line();
            cli_info("Processed {$count} job(s). Stopping.");
            break;
        }
    }
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: queue:status
// ─────────────────────────────────────────────────────

function cmd_queue_status(): void
{
    cli_header('Queue Status');

    $stats = queue_stats();

    if (empty($stats)) {
        cli_warn('No queues found.');
        return;
    }

    $rows = [];
    foreach ($stats as $queue => $count) {
        $rows[] = ['queue' => $queue, 'jobs' => $count];
    }

    cli_table(['Queue', 'Jobs'], $rows);
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: queue:failed
// ─────────────────────────────────────────────────────

function cmd_queue_failed(): void
{
    cli_header('Failed Jobs');

    $jobs = queue_failed();

    if (empty($jobs)) {
        cli_info('No failed jobs.');
        cli_line();
        return;
    }

    $rows = [];
    foreach ($jobs as $job) {
        $rows[] = [
            'id'        => substr($job['id'], 0, 8) . '...',
            'handler'   => $job['handler'],
            'queue'     => $job['queue'],
            'attempts'  => $job['attempts'],
            'failed_at' => $job['failed_at'] ?? '—',
        ];
    }

    cli_table(['ID', 'Handler', 'Queue', 'Attempts', 'Failed At'], $rows);
    cli_line();
    cli_info(count($jobs) . ' failed job(s). Run queue:flush to clear.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: queue:flush
// ─────────────────────────────────────────────────────

function cmd_queue_flush(): void
{
    cli_header('Flush Failed Jobs');

    if (!cli_confirm('Delete all failed jobs?')) {
        cli_warn('Aborted.');
        return;
    }

    $count = queue_flush_failed();
    cli_success("Flushed {$count} failed job(s).");
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: cache:config
// ─────────────────────────────────────────────────────

function cmd_cache_config(): void
{
    cli_header('Cache Config');
    config_cache_write();
    cli_success('Config cached to storage/cache/config.cache.php');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND: optimize
// ─────────────────────────────────────────────────────

function cmd_optimize(): void
{
    cli_header('Optimize for Production');

    cli_info('Caching routes...');
    $map = router_cache_build();
    router_cache_write($map);
    cli_success(count($map) . ' routes cached.');

    cli_info('Caching config...');
    config_cache_write();
    cli_success('Config cached.');

    cli_line();
    cli_success('Application optimized for production.');
    cli_info('Run "php flux cache:clear" to reset.');
    cli_line();
}

// ─────────────────────────────────────────────────────
//  COMMAND DISPATCHER
// ─────────────────────────────────────────────────────

$command = cli_cmd();

match ($command) {
    'help', '--help', '-h'  => cmd_help(),
    'about'                 => cmd_about(),
    'serve'                 => cmd_serve(),
    'key:generate'          => cmd_key_generate(),
    'route:list'            => cmd_route_list(),
    'route:cache'           => cmd_route_cache(),
    'route:clear'           => cmd_route_clear(),
    'cache:clear'           => cmd_cache_clear(),
    'make:page'             => cmd_make_page(),
    'make:module'           => cmd_make_module(),
    'make:api'              => cmd_make_api(),
    'make:middleware'       => cmd_make_middleware(),
    'make:migration'        => cmd_make_migration(),
    'make:seeder'           => cmd_make_seeder(),
    'make:crud'             => cmd_make_crud(),
    'make:auth'             => cmd_make_auth(),
    'make:layout'           => cmd_make_layout(),
    'make:component'        => cmd_make_component(),
    'make:event'            => cmd_make_event(),
    'migrate'               => cmd_migrate(),
    'migrate:rollback'      => cmd_migrate_rollback(),
    'migrate:fresh'         => cmd_migrate_fresh(),
    'db:status'             => cmd_db_status(),
    'seed'                  => cmd_seed(),
    'module:list'           => cmd_module_list(),
    'storage:link'          => cmd_storage_link(),
    'queue:work'            => cmd_queue_work(),
    'queue:status'          => cmd_queue_status(),
    'queue:failed'          => cmd_queue_failed(),
    'queue:flush'           => cmd_queue_flush(),
    'cache:config'          => cmd_cache_config(),
    'optimize'              => cmd_optimize(),
    default => (function() use ($command) {
        cli_error("Unknown command: {$command}");
        cli_line();
        cmd_help();
    })(),
};
