<?php

// ═══════════════════════════════════════════════════════
//  FluxPHP — WebSocket Server (Ready-Structure)
//
//  This is a foundation for WebSocket support.
//  To activate, install Ratchet or Swoole:
//    composer require cboden/ratchet
//
//  Run with:
//    php websocket/server.php
// ═══════════════════════════════════════════════════════

define('FLUX_ROOT', dirname(__DIR__));
require FLUX_ROOT . '/bootstrap/app.php';

// ── Event channel registry ────────────────────────────
$_FLUX_WS_CHANNELS = [];

function ws_channel(string $name): array
{
    global $_FLUX_WS_CHANNELS;
    return $_FLUX_WS_CHANNELS[$name] ?? [];
}

function ws_broadcast(string $channel, string $event, array $data = []): void
{
    $payload = json_encode([
        'channel' => $channel,
        'event'   => $event,
        'data'    => $data,
        'time'    => time(),
    ]);

    // When Ratchet/Swoole is installed, broadcast to connections here
    log_info("WS broadcast [{$channel}]: {$event}");

    // Example with Ratchet:
    // foreach (ws_channel($channel) as $client) {
    //     $client->send($payload);
    // }
}

// ── Hook into FluxPHP events ──────────────────────────
event_listen('user.created', function($p) {
    ws_broadcast('admin', 'user.created', $p);
});

event_listen('auth.login', function($p) {
    ws_broadcast('admin', 'auth.login', $p);
});

echo "FluxPHP WebSocket server structure ready.\n";
echo "Install Ratchet (composer require cboden/ratchet) to activate.\n";
