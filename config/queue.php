<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Queue Config
// ─────────────────────────────────────────

return [
    // Default queue name
    'default' => env('QUEUE_DEFAULT', 'default'),

    // Storage path for file-based queue
    'path'    => FLUX_ROOT . '/storage/queue',

    // Default max retry attempts per job
    'retries' => 3,

    // Worker sleep time in seconds when queue is empty
    'sleep'   => 1,
];
