<?php
/**
 * FluxPHP — Developer Error Page
 * Only shown when APP_DEBUG=true
 * Variables: $exception (Throwable), $severity, $message, $file, $line, $trace
 */

$type    = isset($exception) ? get_class($exception) : 'Error';
$message = $message ?? ($exception?->getMessage() ?? 'Unknown error');
$file    = $file    ?? ($exception?->getFile()    ?? '');
$line    = $line    ?? ($exception?->getLine()    ?? 0);
$trace   = $trace   ?? ($exception?->getTrace()   ?? []);

// Try to read file context around error line
function debug_file_snippet(string $file, int $line, int $context = 8): array
{
    if (!file_exists($file)) return [];
    $lines  = file($file);
    $start  = max(0, $line - $context - 1);
    $end    = min(count($lines) - 1, $line + $context - 1);
    $result = [];
    for ($i = $start; $i <= $end; $i++) {
        $result[$i + 1] = $lines[$i];
    }
    return $result;
}

// Suggest possible fixes based on error type and message
function debug_suggest_fix(string $type, string $message): array
{
    $suggestions = [];

    if (str_contains($message, 'Call to undefined function')) {
        preg_match('/undefined function (.+?)\(\)/', $message, $m);
        $fn = $m[1] ?? '';
        $suggestions[] = "The function <code>{$fn}()</code> does not exist.";
        $suggestions[] = "Check if the module containing <code>{$fn}()</code> is enabled in <code>modules/</code>.";
        $suggestions[] = "Verify the module's <code>functions.php</code> or <code>throttle.php</code> is being loaded.";
        $suggestions[] = "Run <code>php flux module:list</code> to see loaded modules.";
    }

    if (str_contains($message, 'Class') && str_contains($message, 'not found')) {
        $suggestions[] = "Run <code>composer dump-autoload</code> to refresh the autoloader.";
    }

    if (str_contains($message, 'SQLSTATE') || str_contains($message, 'PDO')) {
        $suggestions[] = "Check your database credentials in <code>.env</code>.";
        $suggestions[] = "Make sure the database server is running.";
        $suggestions[] = "Run <code>php flux migrate</code> if tables are missing.";
        $suggestions[] = "Run <code>php flux db:status</code> to check migration status.";
    }

    if (str_contains($message, 'not found') && str_contains($type, 'RuntimeException')) {
        $suggestions[] = "Check the file path is correct and the file exists.";
        $suggestions[] = "Verify the layout/view/component name in your page file.";
    }

    if (str_contains($message, 'Undefined variable') || str_contains($message, 'Undefined array key')) {
        $suggestions[] = "Check the variable is defined before use.";
        $suggestions[] = "Use <code>isset()</code> or the null coalescing operator <code>??</code>.";
    }

    if (str_contains($message, 'session')) {
        $suggestions[] = "Ensure session storage path <code>storage/sessions/</code> is writable.";
        $suggestions[] = "Check <code>config/session.php</code> settings.";
    }

    if (str_contains($message, 'Permission denied') || str_contains($message, 'failed to open stream')) {
        $suggestions[] = "Check directory permissions for <code>storage/</code>.";
        $suggestions[] = "On Linux/Mac: <code>chmod -R 755 storage/</code>";
    }

    if (empty($suggestions)) {
        $suggestions[] = "Check the stack trace below for the source of the error.";
        $suggestions[] = "Search the error message online for common solutions.";
        $suggestions[] = "Enable logging in <code>.env</code>: <code>LOG_LEVEL=debug</code>";
    }

    return $suggestions;
}

$snippet     = debug_file_snippet($file, $line);
$suggestions = debug_suggest_fix($type, $message);
$relFile     = str_replace(FLUX_ROOT . DIRECTORY_SEPARATOR, '', $file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($type) ?> — FluxPHP Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --biro: #1B3F8B;
            --ink: #111827;
            --danger: #B91C1C;
            --surface: #F9FAFB;
            --border: #E5E7EB;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #FEF2F2; margin: 0; }
        .err-header { background: var(--danger); color: #fff; padding: 24px 32px; }
        .err-header .err-type { font-size: .875rem; font-weight: 600; opacity: .8; letter-spacing: .05em; text-transform: uppercase; }
        .err-header .err-message { font-size: 1.375rem; font-weight: 700; margin-top: 6px; word-break: break-word; }
        .err-header .err-location { font-size: .8125rem; opacity: .75; margin-top: 8px; font-family: monospace; }
        .err-body { max-width: 1100px; margin: 0 auto; padding: 24px 24px 48px; }

        /* Suggestions */
        .fix-box { background: #fff; border: 1px solid #FCA5A5; border-left: 4px solid var(--danger); border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; }
        .fix-box h6 { color: var(--danger); font-weight: 700; margin-bottom: 10px; }
        .fix-box li { font-size: .875rem; color: #374151; margin-bottom: 4px; }
        .fix-box code { background: #FEE2E2; color: var(--danger); padding: 1px 5px; border-radius: 3px; font-size: .8125rem; }

        /* Code snippet */
        .snippet-card { background: #1e1e2e; border-radius: 8px; overflow: hidden; margin-bottom: 24px; }
        .snippet-header { background: #2a2a3e; padding: 10px 16px; font-size: .8125rem; color: #a0a0b0; font-family: monospace; display: flex; justify-content: space-between; }
        .snippet-body { overflow-x: auto; }
        .snippet-body table { width: 100%; border-collapse: collapse; font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: .8125rem; }
        .snippet-body td { padding: 1px 0; white-space: pre; }
        .snippet-body .ln { width: 48px; text-align: right; padding-right: 16px; color: #555570; user-select: none; }
        .snippet-body .code { color: #cdd6f4; padding-left: 12px; }
        .snippet-body .active-row .ln { color: #f38ba8; font-weight: 700; }
        .snippet-body .active-row .code { background: rgba(243,139,168,.1); color: #fff; }
        .snippet-body .active-row { background: rgba(243,139,168,.08); }

        /* Stack trace */
        .trace-card { background: #fff; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 24px; }
        .trace-item { border-bottom: 1px solid var(--border); padding: 12px 20px; font-size: .8125rem; }
        .trace-item:last-child { border-bottom: none; }
        .trace-item .trace-num { color: #9CA3AF; font-weight: 600; margin-right: 8px; }
        .trace-item .trace-file { font-family: monospace; color: var(--biro); }
        .trace-item .trace-fn { color: var(--ink); font-family: monospace; margin-top: 2px; }
        .trace-item .trace-args { color: #6B7280; font-size: .75rem; margin-top: 2px; }

        /* Request info */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .info-card { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 16px 20px; }
        .info-card h6 { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #6B7280; margin-bottom: 10px; }
        .info-row { display: flex; gap: 8px; font-size: .8125rem; margin-bottom: 4px; }
        .info-key { color: #6B7280; min-width: 120px; flex-shrink: 0; }
        .info-val { color: var(--ink); font-family: monospace; word-break: break-all; }

        .section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #6B7280; margin: 24px 0 10px; }
        .badge-env { background: #FEE2E2; color: var(--danger); padding: 2px 8px; border-radius: 4px; font-size: .75rem; font-weight: 600; margin-left: 8px; }
    </style>
</head>
<body>

<div class="err-header">
    <div class="err-type">
        <i class="bi bi-bug"></i>
        <?= htmlspecialchars($type) ?>
        <span class="badge-env"><?= htmlspecialchars(config('app.env', 'local')) ?></span>
    </div>
    <div class="err-message"><?= htmlspecialchars($message) ?></div>
    <div class="err-location">
        <i class="bi bi-file-code"></i>
        <?= htmlspecialchars($relFile) ?> &nbsp;·&nbsp; line <?= (int)$line ?>
    </div>
</div>

<div class="err-body">

    <!-- Suggestions -->
    <div class="fix-box mt-4">
        <h6><i class="bi bi-lightbulb"></i> Possible Fix</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($suggestions as $s): ?>
                <li><?= $s ?></li>
            <?php endforeach ?>
        </ul>
    </div>

    <!-- Code Snippet -->
    <?php if (!empty($snippet)): ?>
    <div class="section-title"><i class="bi bi-code-square"></i> Source</div>
    <div class="snippet-card">
        <div class="snippet-header">
            <span><?= htmlspecialchars($relFile) ?></span>
            <span>Line <?= (int)$line ?></span>
        </div>
        <div class="snippet-body">
            <table>
                <?php foreach ($snippet as $ln => $code): ?>
                    <tr class="<?= $ln === $line ? 'active-row' : '' ?>">
                        <td class="ln"><?= $ln ?></td>
                        <td class="code"><?= htmlspecialchars(rtrim($code)) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
    </div>
    <?php endif ?>

    <!-- Stack Trace -->
    <?php if (!empty($trace)): ?>
    <div class="section-title"><i class="bi bi-stack"></i> Stack Trace</div>
    <div class="trace-card">
        <?php foreach ($trace as $i => $frame): ?>
            <?php
            $frameFile = str_replace(FLUX_ROOT . DIRECTORY_SEPARATOR, '', $frame['file'] ?? '{closure}');
            $frameLine = $frame['line'] ?? '—';
            $fn = isset($frame['class'])
                ? $frame['class'] . $frame['type'] . $frame['function'] . '()'
                : ($frame['function'] ?? '{closure}') . '()';
            $args = array_map(function($a) {
                if (is_string($a)) return '"' . substr($a, 0, 50) . '"';
                if (is_array($a))  return 'Array';
                if (is_object($a)) return get_class($a);
                return var_export($a, true);
            }, $frame['args'] ?? []);
            ?>
            <div class="trace-item">
                <span class="trace-num">#<?= $i ?></span>
                <span class="trace-file"><?= htmlspecialchars($frameFile) ?>:<?= $frameLine ?></span>
                <div class="trace-fn"><?= htmlspecialchars($fn) ?></div>
                <?php if (!empty($args)): ?>
                    <div class="trace-args"><?= htmlspecialchars(implode(', ', $args)) ?></div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <!-- Request Info -->
    <div class="section-title"><i class="bi bi-info-circle"></i> Request</div>
    <div class="info-grid">
        <div class="info-card">
            <h6>Request</h6>
            <div class="info-row"><span class="info-key">Method</span><span class="info-val"><?= htmlspecialchars(request_method()) ?></span></div>
            <div class="info-row"><span class="info-key">URL</span><span class="info-val"><?= htmlspecialchars(request_url()) ?></span></div>
            <div class="info-row"><span class="info-key">IP</span><span class="info-val"><?= htmlspecialchars(request_ip()) ?></span></div>
            <div class="info-row"><span class="info-key">User Agent</span><span class="info-val"><?= htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80)) ?></span></div>
        </div>
        <div class="info-card">
            <h6>Application</h6>
            <div class="info-row"><span class="info-key">PHP</span><span class="info-val"><?= PHP_VERSION ?></span></div>
            <div class="info-row"><span class="info-key">FluxPHP</span><span class="info-val"><?= config('app.version', '1.0.0') ?></span></div>
            <div class="info-row"><span class="info-key">Environment</span><span class="info-val"><?= htmlspecialchars(config('app.env', 'local')) ?></span></div>
            <div class="info-row"><span class="info-key">Auth</span><span class="info-val"><?= auth_check() ? 'User #' . auth_id() . ' (' . auth_role() . ')' : 'Guest' ?></span></div>
        </div>
    </div>

    <?php if (!empty($_GET) || !empty($_POST)): ?>
    <div class="section-title"><i class="bi bi-send"></i> Input</div>
    <div class="info-card mb-4">
        <?php foreach (array_merge($_GET, $_POST) as $k => $v): ?>
            <div class="info-row">
                <span class="info-key"><?= htmlspecialchars($k) ?></span>
                <span class="info-val"><?= htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v) ?></span>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
