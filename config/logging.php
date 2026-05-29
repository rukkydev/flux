<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Logging Config
// ─────────────────────────────────────────

return [
    // Minimum log level to write
    // Levels: debug, info, notice, warning, error, critical, alert, emergency
    'level'   => env('LOG_LEVEL', 'debug'),

    // Log file storage path
    'path'    => FLUX_ROOT . '/storage/logs',

    // Rotate logs daily (file per day)
    'daily'   => true,

    // Max log files to keep (0 = keep all)
    'max_files' => 30,
];
