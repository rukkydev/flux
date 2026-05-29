<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: admin — Functions
// ─────────────────────────────────────────

/**
 * Update a key in the .env file at runtime.
 */
function env_update(string $key, string $value): void
{
    $path = base_path('.env');
    if (!file_exists($path)) return;

    $content = file_get_contents($path);

    if (str_contains($value, ' ')) {
        $value = '"' . $value . '"';
    }

    if (preg_match("/^{$key}=.*/m", $content)) {
        $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
    } else {
        $content .= "\n{$key}={$value}";
    }

    file_put_contents($path, $content);
    $_ENV[$key] = $value;
}

/**
 * Get admin dashboard stats (cached 60s).
 */
function admin_stats(): array
{
    return cache_remember('admin.stats', function () {
        return [
            'total_users'  => db_count('users'),
            'active_users' => db_table('users')->whereNull('deleted_at')->count(),
            'failed_jobs'  => count(queue_failed()),
            'queue_jobs'   => array_sum(queue_stats()),
        ];
    }, 60);
}

function admin_menu(): array
{
    return [
        ['label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge', 'url' => url('admin.dashboard')],
        ['label' => 'Users', 'icon' => 'fa-solid fa-users', 'url' => url('admin.users')],
        ['label' => 'Settings', 'icon' => 'fa-solid fa-cog', 'url' => url('admin.settings')],
    ];
}
