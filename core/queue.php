<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Queue System (Phase 9)
//
//  File-based job queue ready for async workers.
//  Dispatch jobs now, process them with:
//    php flux queue:work
//
//  Job structure:
//    queue_dispatch('emails', SendWelcomeEmail::class, $payload);
//    queue_dispatch('default', 'process_report', $payload);
// ═══════════════════════════════════════════════════════

define('QUEUE_PATH', FLUX_ROOT . '/storage/queue');

// ─────────────────────────────────────────────────────
//  DISPATCH
// ─────────────────────────────────────────────────────

/**
 * Push a job onto a queue.
 *
 * queue_dispatch('default', 'send_welcome_email', ['user_id' => 1]);
 * queue_dispatch('emails', 'send_invoice', $payload, delay: 60);
 */
function queue_dispatch(
    string $queue,
    string $handler,
    array  $payload  = [],
    int    $delay    = 0,
    int    $attempts = 3
): string {
    $id  = uuid();
    $job = [
        'id'           => $id,
        'queue'        => $queue,
        'handler'      => $handler,
        'payload'      => $payload,
        'attempts'     => 0,
        'max_attempts' => $attempts,
        'available_at' => time() + $delay,
        'created_at'   => date('Y-m-d H:i:s'),
    ];

    $dir = QUEUE_PATH . '/' . $queue;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $file = $dir . '/' . $id . '.job';
    file_put_contents($file, serialize($job), LOCK_EX);

    log_debug("Job dispatched: {$handler} [{$id}] on queue [{$queue}]");

    return $id;
}

// ─────────────────────────────────────────────────────
//  WORKER HELPERS
// ─────────────────────────────────────────────────────

/**
 * Get the next available job from a queue.
 */
function queue_next(string $queue = 'default'): ?array
{
    $dir = QUEUE_PATH . '/' . $queue;
    if (!is_dir($dir)) return null;

    $files = glob($dir . '/*.job');
    if (!$files) return null;

    // Sort by creation time (oldest first)
    usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

    foreach ($files as $file) {
        $job = @unserialize(file_get_contents($file));
        if (!$job) continue;

        // Skip jobs not yet available (delayed)
        if ($job['available_at'] > time()) continue;

        return array_merge($job, ['_file' => $file]);
    }

    return null;
}

/**
 * Mark a job as complete (delete it).
 */
function queue_complete(array $job): void
{
    if (isset($job['_file']) && file_exists($job['_file'])) {
        @unlink($job['_file']);
    }
    log_debug("Job completed: {$job['handler']} [{$job['id']}]");
}

/**
 * Mark a job as failed — move to failed queue or delete after max attempts.
 */
function queue_fail(array $job, string $error = ''): void
{
    $job['attempts']++;
    $job['last_error'] = $error;
    $job['failed_at']  = date('Y-m-d H:i:s');

    if (isset($job['_file']) && file_exists($job['_file'])) {
        @unlink($job['_file']);
    }

    if ($job['attempts'] < $job['max_attempts']) {
        // Re-queue with exponential backoff
        $delay = (int) pow(2, $job['attempts']) * 10;
        $job['available_at'] = time() + $delay;

        $dir  = QUEUE_PATH . '/' . $job['queue'];
        $file = $dir . '/' . $job['id'] . '.job';
        file_put_contents($file, serialize($job), LOCK_EX);

        log_warning("Job re-queued (attempt {$job['attempts']}): {$job['handler']} [{$job['id']}]");
    } else {
        // Move to failed storage
        $failedDir = QUEUE_PATH . '/failed';
        if (!is_dir($failedDir)) mkdir($failedDir, 0755, true);

        file_put_contents($failedDir . '/' . $job['id'] . '.failed', serialize($job), LOCK_EX);
        log_error("Job permanently failed: {$job['handler']} [{$job['id']}]", ['error' => $error]);
    }
}

/**
 * Process a job by calling its handler function.
 */
function queue_process(array $job): bool
{
    $handler = $job['handler'];
    $payload = $job['payload'];

    try {
        if (function_exists($handler)) {
            $handler($payload);
        } else {
            throw new \RuntimeException("Queue handler not found: {$handler}");
        }

        queue_complete($job);
        return true;

    } catch (\Throwable $e) {
        queue_fail($job, $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────
//  QUEUE STATS
// ─────────────────────────────────────────────────────

/**
 * Get job counts per queue.
 */
function queue_stats(): array
{
    $stats = [];
    $dirs  = glob(QUEUE_PATH . '/*', GLOB_ONLYDIR) ?: [];

    foreach ($dirs as $dir) {
        $name          = basename($dir);
        $ext           = $name === 'failed' ? '.failed' : '.job';
        $stats[$name]  = count(glob($dir . '/*' . $ext) ?: []);
    }

    return $stats;
}

/**
 * Get all failed jobs.
 */
function queue_failed(): array
{
    $dir   = QUEUE_PATH . '/failed';
    $jobs  = [];

    if (!is_dir($dir)) return [];

    foreach (glob($dir . '/*.failed') ?: [] as $file) {
        $job = @unserialize(file_get_contents($file));
        if ($job) $jobs[] = $job;
    }

    return $jobs;
}

/**
 * Flush all failed jobs.
 */
function queue_flush_failed(): int
{
    $dir   = QUEUE_PATH . '/failed';
    $count = 0;

    if (!is_dir($dir)) return 0;

    foreach (glob($dir . '/*.failed') ?: [] as $file) {
        @unlink($file);
        $count++;
    }

    return $count;
}
