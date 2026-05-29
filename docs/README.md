# FluxPHP Documentation

**Version:** 1.0.0 | **PHP:** 8.3+ | **License:** MIT

---

## Table of Contents

1. [Installation](#installation)
2. [Directory Structure](#directory-structure)
3. [Routing](#routing)
4. [Pages](#pages)
5. [Layouts & Components](#layouts--components)
6. [Modules](#modules)
7. [Database](#database)
8. [Authentication](#authentication)
9. [Validation](#validation)
10. [API](#api)
11. [Cache](#cache)
12. [Queue](#queue)
13. [Events](#events)
14. [Middleware](#middleware)
15. [CLI Reference](#cli-reference)
16. [Configuration](#configuration)

---

## Installation

```bash
git clone https://github.com/fluxphp/fluxphp.git myapp
cd myapp
cp .env.example .env
php flux key:generate
php flux migrate
php flux seed
php flux serve
```

---

## Directory Structure

```
project/
├── api/            # File-based API endpoints
├── bootstrap/      # Framework boot sequence
├── components/     # Reusable UI components
├── config/         # App, DB, cache, mail configs
├── core/           # Framework engine files
├── database/
│   ├── migrations/ # Database schema migrations
│   └── seeders/    # Database seed files
├── docs/           # This documentation
├── layouts/        # Page layout templates
├── middleware/      # Custom middleware files
├── modules/        # Business domain modules
├── pages/          # File-based page routes
├── public/         # Web root (CSS, JS, images)
├── routes/         # Manual route overrides
├── storage/        # Logs, cache, sessions, queue
├── themes/         # Theme assets
├── websocket/      # WebSocket server (optional)
├── .env            # Environment variables
├── flux            # CLI tool
└── index.php       # Front controller
```

---

## Routing

FluxPHP uses **automatic file-based routing**. Every file in `pages/` becomes a route automatically.

### File → Route Mapping

```
pages/index.php              → /
pages/about.php              → /about
pages/user/profile.php       → /user/profile
pages/user/[id].php          → /user/42       ($id = '42')
pages/post/[id]/edit.php     → /post/5/edit   ($id = '5')
pages/admin/index.php        → /admin
```

### Dynamic Parameters

```php
// pages/user/[id].php
$id   = route_param('id');       // '42'
$all  = route_params();          // ['id' => '42']
```

### Manual Route Overrides

```php
// routes/web.php
route('/login',    'pages/auth/login.php',    ['guest']);
route('/register', 'pages/auth/register.php', ['guest']);

// Route groups
route_group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function() {
    route('/admin/reports', 'pages/admin/reports.php');
});
```

### Directory Middleware

Drop a `_middleware.php` file in any directory to protect all pages inside:

```php
// pages/admin/_middleware.php
return ['auth', 'admin'];
```

---

## Pages

Pages are thin. They call module functions, set layout, render views.

```php
<?php
// pages/user/profile.php

middleware('auth');
title('My Profile');
layout('app');

$user = auth_user();
?>

<h1>Hello, <?= e($user['name']) ?></h1>
```

### Layout & Title

```php
layout('app');           // use layouts/app.php
layout('dashboard');     // use layouts/dashboard.php
layout('auth');          // use layouts/auth.php
no_layout();             // raw output (for HTMX partials etc.)

title('Page Title');
meta('description', 'Page meta description');
```

### Sections & Stacks

```php
// In a page:
<?php section('head') ?>
    <link rel="stylesheet" href="...">
<?php end_section() ?>

<?php push('scripts') ?>
    <script src="/js/chart.js"></script>
<?php end_push() ?>

// In a layout:
<?php yield_section('head') ?>
<?php yield_push('scripts') ?>
<?php yield_content() ?>
```

---

## Layouts & Components

### Built-in Layouts

| Layout | Use case |
|--------|----------|
| `app` | Public-facing pages with navbar + footer |
| `auth` | Login, register, password reset |
| `dashboard` | Admin/user dashboard with sidebar |
| `blank` | Raw output, no Chrome |

### Components

```php
component('navbar');
component('flash');
component('card',       ['title' => 'My Card']);
component('modal',      ['id' => 'confirm', 'title' => 'Confirm']);
component('pagination', ['paginator' => $results]);
```

### Slots

```php
// In a page:
<?php slot('default') ?>
    <p>Card content here</p>
<?php end_slot() ?>
<?php component('card', ['title' => 'Hello']) ?>

// In a component file:
<?php yield_slot('default', 'Fallback content') ?>
```

### Partials

```php
partial('alerts', ['errors' => $errors]);
partial('user.card', ['user' => $user]);
```

---

## Modules

Modules are business domains. Each lives in `modules/<name>/`.

```
modules/user/
├── module.php       # Manifest (name, version, enabled)
├── functions.php    # Domain helper functions (auto-loaded)
├── validation.php   # Validation rule sets
├── queries.php      # Complex query helpers
├── middleware.php   # Middleware registration
├── hooks.php        # Event listeners, view shares
├── routes.php       # Manual route overrides
├── config.php       # Module config (merged into config())
└── api.php          # API-specific helpers
```

### Creating a Module

```bash
php flux make:module Property
```

### Module Functions Pattern

```php
// modules/property/functions.php
function property_find(int $id): ?array
{
    return db_find('properties', $id);
}

function property_create(array $data): string
{
    return db_insert('properties', $data);
}
```

### Module Config

```php
// modules/property/config.php
config_set('modules.property', [
    'table'    => 'properties',
    'per_page' => 20,
]);

// Access anywhere:
config('modules.property.per_page'); // 20
```

---

## Database

### CRUD Helpers

```php
db_find('users', 1);
db_find_where('users', ['email' => 'a@b.com']);
db_find_or_fail('users', 1);
db_all('users');
db_where('users', ['role' => 'admin']);
db_insert('users', $data);
db_update('users', $id, $data);
db_delete('users', $id);
db_count('users', ['role' => 'admin']);
db_exists('users', ['email' => 'a@b.com']);
db_paginate('users', page: 1, perPage: 15);
```

### Query Builder

```php
$users = db_table('users')
    ->select('id, name, email')
    ->where('role', 'admin')
    ->where('created_at', '>=', '2024-01-01')
    ->whereNotNull('email_verified_at')
    ->order('name', 'ASC')
    ->limit(10)
    ->get();

$user = db_table('users')
    ->where('email', $email)
    ->first();

$count = db_table('users')
    ->where('role', 'admin')
    ->count();

// Paginated
$results = db_table('users')
    ->where('role', 'user')
    ->order('created_at', 'DESC')
    ->paginate(page: 1, perPage: 15);
```

### Transactions

```php
$id = db_transaction(function() use ($data) {
    $id = db_insert('orders', $data['order']);
    foreach ($data['items'] as $item) {
        db_insert('order_items', ['order_id' => $id, ...$item]);
    }
    return $id;
});
```

### Migrations

```bash
php flux make:migration create_products_table
php flux migrate
php flux migrate:rollback
php flux migrate:fresh
php flux db:status
```

---

## Authentication

```php
// Check auth state
auth_check();           // bool
auth_id();              // int|null
auth_user();            // array|null
auth_role();            // string
auth_is('admin');       // bool
auth_is_admin();        // bool

// Login / logout
auth_attempt($email, $password, $remember);
auth_login($user, $remember);
auth_logout();

// API tokens
auth_token_create($userId, 'api');
auth_token_revoke($userId, 'api');
```

### Middleware Guards

```php
middleware('auth');      // Must be logged in
middleware('guest');     // Must NOT be logged in
middleware('admin');     // Must be admin
middleware('verified'); // Must have verified email
middleware('api.auth'); // Must have valid API token
```

---

## Validation

```php
// Validate and abort on failure
$data = validate($_POST, [
    'name'     => 'required|min:2|max:255',
    'email'    => 'required|email',
    'password' => 'required|min:8|confirmed',
    'role'     => 'required|in:user,admin',
    'age'      => 'required|integer|min:18',
]);

// Validate without aborting (returns error bag)
$errors = validation_run($data, $rules);

// In views
has_error('email')              // bool
validation_error('email')       // string (first error)
error_class('email')            // 'is-invalid' or ''
old('email', $default)          // repopulate form
```

### Available Rules

`required`, `nullable`, `sometimes`, `string`, `integer`, `numeric`, `boolean`, `email`, `url`, `min:N`, `max:N`, `between:N,M`, `confirmed`, `same:field`, `different:field`, `in:a,b,c`, `not_in:a,b`, `alpha`, `alpha_num`, `regex:/pattern/`, `date`, `array`, `unique:table,column`

---

## API

### Endpoint Structure

```php
// api/products/index.php

api_only('GET', 'POST');
api_auth_required();
api_rate_limit(60);

api_dispatch([
    'GET'  => function() {
        $page    = (int) (request('page') ?? 1);
        $results = db_table('products')->paginate($page);
        response_paginated($results);
    },
    'POST' => function() {
        $data = api_validate([
            'name'  => 'required|max:255',
            'price' => 'required|numeric',
        ]);
        $id = db_insert('products', $data);
        response_created(db_find('products', $id));
    },
]);
```

### Response Helpers

```php
response_success($data, 'OK');
response_created($data, 'Created');
response_error('Bad request', 400);
response_not_found('Not found');
response_unauthorized('Login required');
response_forbidden('Access denied');
response_validation_error($errors);
response_no_content();
response_paginated($paginator);
```

---

## Cache

```php
cache_set('key', $value, 300);      // 5 min TTL
cache_get('key', $default);
cache_has('key');
cache_forget('key');
cache_remember('key', fn() => expensive_call(), 300);
cache_remember_forever('key', fn() => db_all('settings'));

// Query caching
cache_query('users.all', fn() => db_all('users'), 60);
cache_query_forget('users.all');

// Tag-based invalidation
cache_tag_set('users', 'users.list', $data, 300);
cache_tag_flush('users');   // invalidates all 'users' tagged cache

// Counters
cache_increment('page_views');
cache_decrement('tickets_remaining');
```

---

## Queue

```php
// Dispatch a job
queue_dispatch('default', 'send_welcome_email', ['user_id' => $id]);
queue_dispatch('emails', 'send_invoice', $payload, delay: 60);

// Define handler function (in a module's functions.php)
function send_welcome_email(array $payload): void
{
    $user = user_find($payload['user_id']);
    // send email...
}

// Process jobs
// php flux queue:work
// php flux queue:work emails
```

---

## Events

```php
// Listen
event_listen('user.registered', function(array $payload) {
    queue_dispatch('emails', 'send_welcome_email', $payload);
});

// Dispatch
event('user.registered', ['id' => $id, 'email' => $email]);

// Check
event_has_listeners('user.registered');  // bool
event_listeners();                        // array of counts
```

---

## Middleware

```php
// Apply in a page
middleware('auth');
middleware(['auth', 'verified']);

// Register custom middleware
middleware_register('subscription', function() {
    $user = auth_user();
    if (empty($user['subscribed_at'])) {
        redirect(url('/billing'));
    }
});

// Directory-level (all pages in folder)
// pages/billing/_middleware.php
return ['auth', 'subscription'];
```

---

## CLI Reference

```bash
# System
php flux help
php flux about
php flux serve [host] [port]
php flux key:generate

# Scaffolding
php flux make:page Admin/Reports
php flux make:module Invoice
php flux make:api invoices/[id]
php flux make:crud Product
php flux make:auth
php flux make:layout marketing
php flux make:component hero-banner
php flux make:event order.placed
php flux make:migration create_invoices_table
php flux make:seeder InvoicesSeeder
php flux make:middleware subscription

# Database
php flux migrate
php flux migrate:rollback
php flux migrate:fresh
php flux db:status
php flux seed

# Routes
php flux route:list
php flux route:cache
php flux route:clear

# Cache & Performance
php flux optimize
php flux cache:clear
php flux cache:config

# Queue
php flux queue:work [queue]
php flux queue:status
php flux queue:failed
php flux queue:flush

# Modules
php flux module:list

# Storage
php flux storage:link
```

---

## Configuration

All config files live in `config/`. Values pull from `.env`.

```php
config('app.name');
config('database.connections.mysql.host');
config('cache.ttl');
config('api.rate_limit');

// Set at runtime
config_set('app.theme', 'dark');

// Environment
env('APP_NAME', 'default');
app_env('production');   // bool
is_debug();              // bool
```

---

## Best Practices

1. **Keep pages thin** — pages call module functions, never business logic directly
2. **Fat modules** — all domain logic, queries, validation in modules
3. **Use `url()`** — always wrap hrefs and redirects: `url('/login')`, `redirect(url('/dashboard'))`
4. **Cache expensive queries** — use `cache_query()` on heavy DB reads
5. **Validate early** — call `validate()` or `api_validate()` before any processing
6. **Use events** — decouple side effects (emails, logs, cache busting) with `event()`
7. **Dispatch jobs** — slow work (emails, reports) goes to the queue
8. **Run `php flux optimize`** before deploying to production
