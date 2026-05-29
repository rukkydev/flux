<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Logger
//  Simple PSR-3-inspired file logger
// ─────────────────────────────────────────

define('LOG_PATH', FLUX_ROOT . '/storage/logs');

function log_write(string $level, string $message, array $context = []): void
{
    $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    $configLevel = env('LOG_LEVEL', 'debug');
    $configIndex = array_search($configLevel, $levels);
    $levelIndex  = array_search($level, $levels);

    // Suppress logs below configured level
    if ($levelIndex < $configIndex) {
        return;
    }

    $date    = date('Y-m-d');
    $time    = date('Y-m-d H:i:s');
    $logFile = LOG_PATH . "/{$date}.log";

    if (!empty($context)) {
        $message .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $line = "[{$time}] [{$level}] {$message}" . PHP_EOL;

    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function log_debug(string $message, array $context = []): void
{
    log_write('debug', $message, $context);
}

function log_info(string $message, array $context = []): void
{
    log_write('info', $message, $context);
}

function log_warning(string $message, array $context = []): void
{
    log_write('warning', $message, $context);
}

function log_error(string $message, array $context = []): void
{
    log_write('error', $message, $context);
}

function log_critical(string $message, array $context = []): void
{
    log_write('critical', $message, $context);
}
