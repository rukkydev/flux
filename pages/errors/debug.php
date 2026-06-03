<?php
/**
 * FluxPHP — Biro Blue Debug Error Page
 * Only shown when APP_DEBUG=true (local environment)
 * Output buffers are cleared before this file is required.
 */

$type    = isset($exception) ? get_class($exception) : 'ErrorException';
$message = $message ?? ($exception?->getMessage() ?? 'Unknown error');
$file    = $file    ?? ($exception?->getFile()    ?? '');
$line    = $line    ?? ($exception?->getLine()    ?? 0);
$trace   = $trace   ?? ($exception?->getTrace()   ?? []);

$relFile = str_replace([FLUX_ROOT . '/', FLUX_ROOT . '\\', FLUX_ROOT], '', $file);
$relFile = str_replace('\\', '/', $relFile);

function debug_snippet(string $file, int $line, int $ctx = 8): array
{
    if (!file_exists($file)) return [];
    $lines = file($file);
    if (!$lines) return [];
    $start = max(0, $line - $ctx - 1);
    $end   = min(count($lines) - 1, $line + $ctx - 1);
    $out   = [];
    for ($i = $start; $i <= $end; $i++) {
        $out[$i + 1] = $lines[$i];
    }
    return $out;
}

function debug_suggestions(string $type, string $message): array
{
    $s = [];

    if (str_contains($message, 'Call to undefined function')) {
        preg_match('/undefined function (.+?)\(\)/', $message, $m);
        $fn = $m[1] ?? '';
        $s[] = "The function <code>{$fn}()</code> is not defined.";
        $s[] = "Check the module containing <code>{$fn}()</code> is enabled and its <code>functions.php</code> or <code>throttle.php</code> is loaded.";
        $s[] = "Run <code>php flux module:list</code> to see all loaded modules.";
    } elseif (str_contains($message, 'array offset on value of type null') || str_contains($message, 'Trying to access array')) {
        $s[] = "A variable is <code>null</code> when an array is expected.";
        $s[] = "Check the variable is set before accessing it — use <code>isset()</code> or <code>?? ''</code>.";
        $s[] = "If this is a user/auth variable, the session may have expired — try logging out and back in.";
    } elseif (str_contains($message, 'Undefined variable')) {
        $s[] = "The variable is used before it is defined.";
        $s[] = "Check spelling or ensure the variable is passed to the view correctly.";
    } elseif (str_contains($message, 'SQLSTATE') || str_contains($message, 'PDO')) {
        $s[] = "Database error — check your <code>.env</code> credentials.";
        $s[] = "Run <code>php flux migrate</code> if tables are missing.";
        $s[] = "Run <code>php flux db:status</code> to check migration status.";
    } elseif (str_contains($message, 'not found') && str_contains($type, 'RuntimeException')) {
        $s[] = "Check the file/layout/component path is correct.";
        $s[] = "Verify the layout name passed to <code>layout()</code>.";
    } elseif (str_contains($message, 'Permission denied') || str_contains($message, 'failed to open stream')) {
        $s[] = "Check directory permissions — <code>storage/</code> must be writable.";
        $s[] = "On Windows: run your editor/server as administrator.";
    }

    if (empty($s)) {
        $s[] = "Check the stack trace and source snippet below for the exact cause.";
        $s[] = "Search the error message online for common solutions.";
        $s[] = "Enable debug logging: <code>LOG_LEVEL=debug</code> in <code>.env</code>.";
    }

    return $s;
}

$snippet     = debug_snippet($file, $line);
$suggestions = debug_suggestions($type, $message);
$shortType   = class_exists($type) ? (new \ReflectionClass($type))->getShortName() : $type;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shortType) ?> — FluxPHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts: Segoe UI and Cascadia Code -->
    <link href="https://fonts.googleapis.com/css2?family=Cascadia+Code&family=Google+Sans:wght@400;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Google Sans',  sans-serif;
        }
        :root {
            --biro:        #1B3F8B;
            --biro-dark:   #122B63;
            --biro-deeper: #0D1F4A;
            --biro-mid:    #2E5AC8;
            --biro-light:  #EEF2FB;
            --biro-tint:   #F5F7FD;
            --ink:         #111827;
            --muted:       #6B7280;
            --border:      #E5E7EB;
            --danger:      #B91C1C;
            --danger-bg:   #FEF2F2;
            --success:     #15803D;
            --code-bg:     #0D1117;
            --font:        'Google Sans',  sans-serif;
            --mono:        'Cascadia Code', 'JetBrains Mono', 'Fira Code', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--biro-tint);
            color: var(--ink);
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── Top bar ── */
        .topbar {
            background: var(--biro-deeper);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--biro-mid);
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,.9);
            font-size: .875rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .topbar-brand .fx {
            width: 26px;
            height: 26px;
            background: var(--biro-mid);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .6875rem;
            font-weight: 900;
            color: #fff;
        }

        .topbar-meta {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-env {
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.8);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .badge-env.local { background: rgba(255,193,7,.15); color: #ffc107; border-color: rgba(255,193,7,.3); }

        /* ── Error header ── */
        .err-header {
            background: var(--biro);
            padding: 36px 32px 32px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .err-type-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .err-type {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.7);
        }

        .err-message {
            font-size: clamp(1.25rem, 2.5vw, 1.75rem);
            font-weight: 700;
            color: #fff;
            line-height: 1.3;
            letter-spacing: -0.02em;
            word-break: break-word;
            margin-bottom: 12px;
        }

        .err-location {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--mono);
            font-size: .8125rem;
            color: rgba(255,255,255,.6);
        }

        .err-location .sep { opacity: .4; }

        .err-location a {
            color: rgba(255,255,255,.8);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        /* ── Body ── */
        .err-body {
            max-width: 1000px;
            margin: 0 auto;
            padding: 28px 32px 64px;
        }

        /* ── Section label ── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
            margin: 28px 0 10px;
        }

        /* ── Suggestions ── */
        .fix-card {
            background: #fff;
            border: 1px solid var(--biro-light);
            border-left: 4px solid var(--biro);
            border-radius: 8px;
            padding: 16px 20px;
        }

        .fix-card h6 {
            font-size: .8125rem;
            font-weight: 700;
            color: var(--biro);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fix-card ul {
            padding-left: 18px;
            margin: 0;
        }

        .fix-card li {
            font-size: .8125rem;
            color: var(--ink);
            margin-bottom: 5px;
            line-height: 1.55;
        }

        .fix-card li:last-child { margin-bottom: 0; }

        .fix-card code {
            background: var(--biro-light);
            color: var(--biro);
            padding: 1px 5px;
            border-radius: 3px;
            font-family: var(--mono);
            font-size: .8125rem;
        }

        /* ── Code snippet ── */
        .snippet-card {
            background: var(--code-bg);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.04);
        }

        .snippet-bar {
            background: #161b22;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .snippet-bar .fname {
            font-family: var(--mono);
            font-size: .75rem;
            color: rgba(255,255,255,.5);
        }

        .snippet-bar .lineno {
            font-family: var(--mono);
            font-size: .6875rem;
            color: rgba(255,255,255,.3);
        }

        .snippet-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--mono);
            font-size: .8125rem;
            overflow-x: auto;
            display: block;
        }

        .snippet-table td { padding: 1px 0; white-space: pre; }

        .snippet-table .ln {
            width: 52px;
            min-width: 52px;
            text-align: right;
            padding-right: 20px;
            color: rgba(255,255,255,.2);
            user-select: none;
            font-size: .75rem;
        }

        .snippet-table .code {
            color: #cdd6f4;
            padding-left: 8px;
            padding-right: 24px;
        }

        .snippet-table .error-line { background: rgba(27,63,139,.25); }
        .snippet-table .error-line .ln { color: var(--biro-mid); font-weight: 700; }
        .snippet-table .error-line .code { color: #fff; }

        /* Error line marker */
        .snippet-table .error-line .ln::after {
            content: ' →';
            color: var(--biro-mid);
        }

        /* ── Stack trace ── */
        .trace-list {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .trace-item {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: 28px 1fr;
            gap: 10px;
            align-items: start;
        }

        .trace-item:last-child { border-bottom: none; }

        .trace-item:hover { background: var(--biro-tint); }

        .trace-num {
            font-size: .6875rem;
            font-weight: 700;
            color: var(--biro-mid);
            background: var(--biro-light);
            width: 22px;
            height: 22px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .trace-body {}

        .trace-file {
            font-family: var(--mono);
            font-size: .75rem;
            color: var(--biro);
            margin-bottom: 2px;
        }

        .trace-fn {
            font-family: var(--mono);
            font-size: .8125rem;
            color: var(--ink);
        }

        .trace-fn .fn-name { color: #7c3aed; }
        .trace-fn .fn-args { color: var(--muted); font-size: .75rem; }

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }

        .info-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
        }

        .info-card h6 {
            font-size: .6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            gap: 8px;
            font-size: .8125rem;
            margin-bottom: 4px;
            line-height: 1.5;
        }

        .info-key {
            color: var(--muted);
            min-width: 110px;
            flex-shrink: 0;
            font-size: .75rem;
        }

        .info-val {
            color: var(--ink);
            font-family: var(--mono);
            font-size: .75rem;
            word-break: break-all;
        }

        /* ── No stack message ── */
        .empty-state {
            text-align: center;
            padding: 24px;
            color: var(--muted);
            font-size: .875rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="fx">fx</div>
        FluxPHP Debug
    </div>
    <div class="topbar-meta">
        <span class="badge-env local"><?= htmlspecialchars(config('app.env', 'local')) ?></span>
        <span class="badge-env">PHP <?= PHP_VERSION ?></span>
        <span class="badge-env">v<?= htmlspecialchars(config('app.version', '1.0.0')) ?></span>
    </div>
</header>

<!-- Error header -->
<div class="err-header">
    <div class="err-type-row">
        <i class="bi bi-bug" style="color:rgba(255,255,255,.6);font-size:1rem"></i>
        <span class="err-type"><?= htmlspecialchars($shortType) ?></span>
    </div>
    <div class="err-message"><?= htmlspecialchars($message) ?></div>
    <div class="err-location">
        <i class="bi bi-file-earmark-code" style="font-size:.875rem"></i>
        <span><?= htmlspecialchars($relFile) ?></span>
        <span class="sep">·</span>
        <span>line <?= (int) $line ?></span>
    </div>
</div>

<!-- Body -->
<div class="err-body">

    <!-- Possible Fix -->
    <div class="section-label">
        <i class="bi bi-lightbulb"></i> Possible Fix
    </div>
    <div class="fix-card">
        <h6><i class="bi bi-lightbulb-fill"></i> Suggested fix</h6>
        <ul>
            <?php foreach ($suggestions as $s): ?>
                <li><?= $s ?></li>
            <?php endforeach ?>
        </ul>
    </div>

    <!-- Source -->
    <?php if (!empty($snippet)): ?>
    <div class="section-label">
        <i class="bi bi-code-square"></i> Source
    </div>
    <div class="snippet-card">
        <div class="snippet-bar">
            <span class="fname"><?= htmlspecialchars($relFile) ?></span>
            <span class="lineno">Line <?= (int) $line ?></span>
        </div>
        <div style="overflow-x:auto">
            <table class="snippet-table">
                <tbody>
                <?php foreach ($snippet as $ln => $code): ?>
                    <tr class="<?= $ln === $line ? 'error-line' : '' ?>">
                        <td class="ln"><?= $ln ?></td>
                        <td class="code"><?= htmlspecialchars(rtrim($code)) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif ?>

    <!-- Stack Trace -->
    <div class="section-label">
        <i class="bi bi-layers"></i> Stack Trace
    </div>
    <?php if (!empty($trace)): ?>
    <div class="trace-list">
        <?php foreach ($trace as $i => $frame):
            $frameFile = str_replace([FLUX_ROOT . '/', FLUX_ROOT . '\\', FLUX_ROOT], '', $frame['file'] ?? '{closure}');
            $frameFile = str_replace('\\', '/', $frameFile);
            $frameLine = $frame['line'] ?? '—';
            $fn = isset($frame['class'])
                ? $frame['class'] . ($frame['type'] ?? '::') . $frame['function']
                : ($frame['function'] ?? '{closure}');
            $args = array_map(function($a) {
                if (is_string($a))  return '"' . htmlspecialchars(substr($a, 0, 40)) . '"';
                if (is_array($a))   return 'array(' . count($a) . ')';
                if (is_object($a))  return get_class($a);
                if (is_null($a))    return 'null';
                if (is_bool($a))    return $a ? 'true' : 'false';
                return htmlspecialchars((string) $a);
            }, $frame['args'] ?? []);
        ?>
            <div class="trace-item">
                <div class="trace-num"><?= $i ?></div>
                <div class="trace-body">
                    <div class="trace-file"><?= htmlspecialchars($frameFile) ?>:<?= $frameLine ?></div>
                    <div class="trace-fn">
                        <span class="fn-name"><?= htmlspecialchars($fn) ?></span><!--
                        --><span class="fn-args">(<?= implode(', ', $args) ?>)</span>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="bi bi-info-circle me-2"></i>No stack trace available.</div>
    <?php endif ?>

    <!-- Request Info -->
    <div class="section-label">
        <i class="bi bi-info-circle"></i> Request Context
    </div>
    <div class="info-grid">
        <div class="info-card">
            <h6>Request</h6>
            <div class="info-row">
                <span class="info-key">Method</span>
                <span class="info-val"><?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'CLI') ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">URL</span>
                <span class="info-val"><?= htmlspecialchars(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">IP</span>
                <span class="info-val"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">User Agent</span>
                <span class="info-val"><?= htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? '—', 0, 60)) ?></span>
            </div>
        </div>
        <div class="info-card">
            <h6>Application</h6>
            <div class="info-row">
                <span class="info-key">PHP</span>
                <span class="info-val"><?= PHP_VERSION ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">FluxPHP</span>
                <span class="info-val"><?= htmlspecialchars(config('app.version', '1.0.0')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">Environment</span>
                <span class="info-val"><?= htmlspecialchars(config('app.env', 'local')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">Session</span>
                <span class="info-val">
                    <?php
                    $authId   = $_SESSION['auth.id'] ?? null;
                    $authType = $_SESSION['auth.type'] ?? 'guest';
                    echo $authId ? "#{$authId} ({$authType})" : 'Guest';
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-key">Memory</span>
                <span class="info-val"><?= round(memory_get_peak_usage(true) / 1048576, 1) ?> MB peak</span>
            </div>
        </div>
    </div>

    <?php if (!empty($_POST)): ?>
    <div class="section-label"><i class="bi bi-send"></i> POST Data</div>
    <div class="info-card">
        <?php foreach ($_POST as $k => $v): ?>
            <?php if ($k === 'password' || $k === '_csrf_token') continue; ?>
            <div class="info-row">
                <span class="info-key"><?= htmlspecialchars($k) ?></span>
                <span class="info-val"><?= htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v) ?></span>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

</div>
</body>
</html>
