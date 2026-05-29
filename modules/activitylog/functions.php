<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: activitylog — Functions
// ─────────────────────────────────────────

/**
 * Parse user agent string into browser, OS, device.
 */
function activitylog_parse_ua(string $ua): array
{
    // Browser
    $browser = 'Unknown';
    $browsers = [
        'Edg'     => 'Edge',
        'OPR'     => 'Opera',
        'Chrome'  => 'Chrome',
        'Safari'  => 'Safari',
        'Firefox' => 'Firefox',
        'MSIE'    => 'IE',
        'Trident' => 'IE',
    ];
    foreach ($browsers as $key => $name) {
        if (str_contains($ua, $key)) { $browser = $name; break; }
    }

    // OS
    $os = 'Unknown';
    $systems = [
        'Windows NT 10' => 'Windows 10',
        'Windows NT 11' => 'Windows 11',
        'Windows NT 6'  => 'Windows',
        'Mac OS X'      => 'macOS',
        'Linux'         => 'Linux',
        'Android'       => 'Android',
        'iPhone'        => 'iOS',
        'iPad'          => 'iPadOS',
    ];
    foreach ($systems as $key => $name) {
        if (str_contains($ua, $key)) { $os = $name; break; }
    }

    // Device
    $device = 'desktop';
    if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone')) {
        $device = 'mobile';
    } elseif (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) {
        $device = 'tablet';
    }

    return compact('browser', 'os', 'device');
}

/**
 * Log an activity with full request context.
 */
function activity_log(
    string  $event,
    array   $context     = [],
    ?int    $userId      = null,
    ?string $description = null,
    ?string $subjectType = null,
    ?int    $subjectId   = null
): void {
    $userId    = $userId ?? auth_id();
    $actorType = 'guest';

    if ($userId) {
        $actorType = auth_is_admin() ? 'admin' : 'user';
    }

    $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $parsed  = activitylog_parse_ua($ua);

    db_insert('activity_logs', [
        'user_id'      => $userId,
        'actor_type'   => $actorType,
        'event'        => $event,
        'description'  => $description,
        'subject_type' => $subjectType,
        'subject_id'   => $subjectId,
        'context'      => json_encode($context),
        'ip_address'   => request_ip(),
        'user_agent'   => $ua,
        'browser'      => $parsed['browser'],
        'os'           => $parsed['os'],
        'device'       => $parsed['device'],
        'url'          => request_url(),
        'method'       => request_method(),
    ]);
}

function activity_for_user(int $userId, int $limit = 50): array
{
    return db_table('activity_logs')
        ->where('user_id', $userId)
        ->order('created_at', 'DESC')
        ->limit($limit)
        ->get();
}

function activity_recent(int $limit = 100): array
{
    return db_table('activity_logs')
        ->order('created_at', 'DESC')
        ->limit($limit)
        ->get();
}

function activity_by_event(string $event, int $limit = 50): array
{
    return db_table('activity_logs')
        ->where('event', $event)
        ->order('created_at', 'DESC')
        ->limit($limit)
        ->get();
}
