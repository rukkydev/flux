<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  FluxPHP — Response
//  Helpers to send HTTP responses
// ─────────────────────────────────────────

function response_json(mixed $data, int $status = 200, array $headers = []): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    foreach ($headers as $key => $value) {
        header("{$key}: {$value}");
    }

    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function response_success(mixed $data = null, string $message = 'Success', int $status = 200): never
{
    response_json([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], $status);
}

function response_error(string $message = 'Error', int $status = 400, mixed $errors = null): never
{
    response_json([
        'success' => false,
        'message' => $message,
        'errors'  => $errors,
    ], $status);
}

function response_not_found(string $message = 'Not found'): never
{
    response_error($message, 404);
}

function response_unauthorized(string $message = 'Unauthorized'): never
{
    response_error($message, 401);
}

function response_forbidden(string $message = 'Forbidden'): never
{
    response_error($message, 403);
}

function response_validation_error(array $errors, string $message = 'Validation failed'): never
{
    response_error($message, 422, $errors);
}

function response_created(mixed $data = null, string $message = 'Created'): never
{
    response_success($data, $message, 201);
}

function response_no_content(): never
{
    http_response_code(204);
    exit;
}

function response_html(string $html, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function response_redirect(string $url, int $status = 302): never
{
    header("Location: {$url}", true, $status);
    exit;
}

function response_download(string $filePath, string $filename = ''): never
{
    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }

    $filename = $filename ?: basename($filePath);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: public');

    readfile($filePath);
    exit;
}
