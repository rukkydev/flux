<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Filesystem & Upload Helper (Fix #8)
//  Secure file upload validation and storage
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────
//  UPLOAD VALIDATION
// ─────────────────────────────────────────────────────

/**
 * Validate an uploaded file against security rules.
 *
 * upload_validate($_FILES['photo'], [
 *     'max_size'   => 2 * 1024 * 1024,
 *     'mimes'      => ['image/jpeg', 'image/png'],
 *     'extensions' => ['jpg', 'jpeg', 'png'],
 * ]);
 *
 * Returns array of errors or empty array if valid.
 */
function upload_validate(array $file, array $rules = []): array
{
    $errors = [];

    // Check upload error
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE);
        return $errors;
    }

    // Must be an actual uploaded file
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        $errors[] = 'Invalid file upload.';
        return $errors;
    }

    $maxSize    = $rules['max_size']   ?? config('security.upload.max_size', 5 * 1024 * 1024);
    $allowedMimes = $rules['mimes']    ?? config('security.upload.allowed_mimes', ['image/jpeg', 'image/png']);
    $allowedExts  = $rules['extensions'] ?? config('security.upload.allowed_extensions', ['jpg', 'jpeg', 'png']);
    $blockedExts  = config('security.upload.blocked_extensions', ['php', 'exe', 'sh', 'bat']);

    // File size
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds limit of ' . upload_format_size($maxSize) . '.';
    }

    // Extension — never allow blocked extensions regardless of rules
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (in_array($ext, $blockedExts, true)) {
        $errors[] = "File type .{$ext} is not allowed.";
        return $errors; // Stop here — dangerous extension
    }

    if (!in_array($ext, $allowedExts, true)) {
        $errors[] = 'File extension .' . $ext . ' is not allowed. Allowed: ' . implode(', ', $allowedExts);
    }

    // Actual MIME type via finfo (not $_FILES['type'] which is user-supplied)
    if (function_exists('finfo_open')) {
        $finfo      = finfo_open(FILEINFO_MIME_TYPE);
        $actualMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($actualMime, $allowedMimes, true)) {
            $errors[] = 'File type ' . $actualMime . ' is not allowed.';
        }
    }

    // Check for PHP code in file content (extra protection)
    if (upload_contains_php($file['tmp_name'])) {
        $errors[] = 'File contains disallowed content.';
    }

    return $errors;
}

/**
 * Store an uploaded file securely.
 * Returns the stored file path relative to storage root.
 *
 * $path = upload_store($_FILES['photo'], 'avatars');
 * // → storage/uploads/avatars/a3f9b2c1d4e5.jpg
 */
function upload_store(array $file, string $directory = 'uploads'): string
{
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new \RuntimeException('Invalid file upload.');
    }

    $ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $filename = generate_token(16) . ($ext ? '.' . $ext : '');
    $dir      = storage_path('uploads/' . trim($directory, '/'));

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Protect directory from direct execution
    upload_ensure_htaccess($dir);

    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new \RuntimeException('Failed to store uploaded file.');
    }

    log_info('File uploaded', ['file' => $filename, 'dir' => $directory]);

    return 'uploads/' . $directory . '/' . $filename;
}

/**
 * Validate and store in one call.
 * Returns path on success, throws on failure.
 */
function upload_handle(array $file, string $directory = 'uploads', array $rules = []): string
{
    $errors = upload_validate($file, $rules);

    if (!empty($errors)) {
        throw new \RuntimeException(implode(' ', $errors));
    }

    return upload_store($file, $directory);
}

/**
 * Delete an uploaded file.
 */
function upload_delete(string $relativePath): bool
{
    $fullPath = storage_path($relativePath);

    if (!file_exists($fullPath)) {
        return false;
    }

    // Only allow deletion from uploads directory
    $uploadsDir = storage_path('uploads');
    if (!str_starts_with(realpath($fullPath), realpath($uploadsDir))) {
        log_warning('Upload delete blocked — path outside uploads dir', ['path' => $relativePath]);
        return false;
    }

    return @unlink($fullPath);
}

/**
 * Get a public URL for an uploaded file.
 */
function upload_url(string $relativePath): string
{
    return url('/storage/' . ltrim($relativePath, '/'));
}

// ─────────────────────────────────────────────────────
//  GENERAL FILESYSTEM
// ─────────────────────────────────────────────────────

/**
 * Read a file safely — only within allowed paths.
 */
function fs_read(string $path): string
{
    fs_assert_safe($path);
    return file_get_contents($path);
}

/**
 * Write a file safely.
 */
function fs_write(string $path, string $content): bool
{
    fs_assert_safe($path);
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($path, $content, LOCK_EX) !== false;
}

/**
 * Delete a file safely.
 */
function fs_delete(string $path): bool
{
    fs_assert_safe($path);
    return file_exists($path) && @unlink($path);
}

/**
 * Assert a path is within the project root (prevent path traversal).
 */
function fs_assert_safe(string $path): void
{
    $real    = realpath($path) ?: $path;
    $root    = realpath(FLUX_ROOT);

    if (!str_starts_with($real, $root)) {
        log_warning('Path traversal attempt blocked', ['path' => $path]);
        abort(403, 'Access denied.');
    }
}

// ─────────────────────────────────────────────────────
//  INTERNAL HELPERS
// ─────────────────────────────────────────────────────

function upload_contains_php(string $tmpPath): bool
{
    if (!file_exists($tmpPath)) return false;

    // Read first 4KB to check for PHP tags
    $content = file_get_contents($tmpPath, false, null, 0, 4096);
    return str_contains($content, '<?php')
        || str_contains($content, '<?=')
        || str_contains($content, '<%')
        || preg_match('/<script\s+language\s*=\s*["\']?php/i', $content) === 1;
}

function upload_ensure_htaccess(string $dir): void
{
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
    }
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE  => 'File is too large.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'File upload blocked by extension.',
        default               => 'Unknown upload error.',
    };
}

function upload_format_size(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
