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
        $stats = [
            'total_users'  => 0,
            'active_users' => 0,
            'failed_jobs'  => count(queue_failed()),
            'queue_jobs'   => array_sum(queue_stats()),
        ];

        // Guard against missing tables during setup
        try {
            if (db_schema_has_table('users')) {
                $stats['total_users']  = db_count('users');
                $stats['active_users'] = db_table('users')->whereNull('deleted_at')->count();
            }
        } catch (\Throwable $e) {
            log_warning('admin_stats: could not query users table', ['error' => $e->getMessage()]);
        }

        return $stats;
    }, 60);
}
