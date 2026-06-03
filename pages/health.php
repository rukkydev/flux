<?php

no_layout();
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'status'  => 'ok',
    'app'     => config('app.name', 'FluxPHP'),
    'time'    => date(DATE_ATOM),
], JSON_UNESCAPED_SLASHES);
