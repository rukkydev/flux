<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Mail Engine
//  Sends HTML emails via SMTP (Mailhog-ready)
//  Uses PHP's built-in mail() or native SMTP socket
// ═══════════════════════════════════════════════════════

/**
 * Send an HTML email.
 *
 * mail_send(
 *     to:      'user@example.com',
 *     subject: 'Welcome!',
 *     view:    'welcome',
 *     data:    ['user' => $user]
 * );
 */
function mail_send(
    string       $to,
    string       $subject,
    string       $view,
    array        $data    = [],
    ?string      $toName  = null,
    array        $cc      = [],
    array        $bcc     = [],
): bool {
    $html = mail_render($view, $data);
    $text = mail_html_to_text($html);

    return mail_smtp_send(
        to:      $to,
        toName:  $toName ?? $to,
        subject: $subject,
        html:    $html,
        text:    $text,
        cc:      $cc,
        bcc:     $bcc,
    );
}

/**
 * Render a mail template to HTML string.
 */
function mail_render(string $view, array $data = []): string
{
    $path = base_path('resources/mail/' . $view . '.php');

    if (!file_exists($path)) {
        throw new \RuntimeException("Mail template not found: {$view}");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $path;
    $body = ob_get_clean();

    // Wrap in base layout
    return mail_wrap_layout($body, $data['subject'] ?? '');
}

/**
 * Wrap mail body in the base HTML email layout.
 */
function mail_wrap_layout(string $body, string $subject = ''): string
{
    $appName = config('app.name', 'FluxPHP');
    $year    = date('Y');
    $color   = '#1B3F8B'; // Biro Blue

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$subject}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Google Sans', 'Segoe UI', Arial, sans-serif; background: #f4f6fb; color: #111827; font-size: 15px; line-height: 1.6; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: {$color}; padding: 28px 32px; text-align: center; }
        .header .brand { color: #ffffff; font-size: 1.25rem; font-weight: 700; letter-spacing: -0.02em; text-decoration: none; }
        .header .brand-dot { display: inline-block; width: 6px; height: 6px; background: rgba(255,255,255,.5); border-radius: 50%; margin-right: 6px; vertical-align: middle; }
        .body { padding: 36px 32px; }
        .body h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin-bottom: 12px; letter-spacing: -0.02em; }
        .body h2 { font-size: 1.125rem; font-weight: 600; color: #111827; margin: 24px 0 8px; }
        .body p { color: #374151; margin-bottom: 16px; }
        .body p.muted { color: #6B7280; font-size: 0.875rem; }
        .btn { display: inline-block; background: {$color}; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 0.9375rem; margin: 8px 0 20px; }
        .btn:hover { background: #122B63; }
        .divider { border: none; border-top: 1px solid #E5E7EB; margin: 24px 0; }
        .code-box { background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 6px; padding: 16px 20px; font-family: monospace; font-size: 1.5rem; letter-spacing: 0.15em; text-align: center; color: {$color}; font-weight: 700; margin: 16px 0; }
        .footer { padding: 20px 32px; text-align: center; }
        .footer p { color: #9CA3AF; font-size: 0.8125rem; margin: 0; }
        .footer a { color: #6B7280; text-decoration: underline; }
        .alert-box { background: #FEF2F2; border-left: 4px solid #B91C1C; border-radius: 4px; padding: 12px 16px; margin: 16px 0; color: #B91C1C; font-size: 0.875rem; }
        .info-box { background: #EEF2FB; border-left: 4px solid {$color}; border-radius: 4px; padding: 12px 16px; margin: 16px 0; color: {$color}; font-size: 0.875rem; }
        @media (max-width: 600px) {
            .body { padding: 24px 20px; }
            .header { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <a href="#" class="brand">
                <span class="brand-dot"></span>{$appName}
            </a>
        </div>
        <div class="body">
            {$body}
        </div>
        <div class="footer">
            <p>&copy; {$year} {$appName}. All rights reserved.</p>
            <p style="margin-top:6px"><a href="#">Unsubscribe</a> &middot; <a href="#">Privacy Policy</a></p>
        </div>
    </div>
</div>
</body>
</html>
HTML;
}

/**
 * Strip HTML tags for plain text fallback.
 */
function mail_html_to_text(string $html): string
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

/**
 * Send via SMTP using native socket (no dependency needed).
 * Works with Mailhog out of the box.
 */
function mail_smtp_send(
    string $to,
    string $toName,
    string $subject,
    string $html,
    string $text    = '',
    array  $cc      = [],
    array  $bcc     = [],
): bool {
    $cfg      = config('mail');
    $host     = $cfg['host']     ?? '127.0.0.1';
    $port     = $cfg['port']     ?? 1025;
    $from     = $cfg['from']['address'] ?? 'hello@fluxphp.dev';
    $fromName = $cfg['from']['name']    ?? config('app.name', 'FluxPHP');
    $username = $cfg['username'] ?? '';
    $password = $cfg['password'] ?? '';
    $encrypt  = $cfg['encryption'] ?? '';

    $boundary = md5(uniqid((string) time()));
    $msgId    = '<' . uuid() . '@fluxphp>';

    // Build headers
    $headers  = "From: {$fromName} <{$from}>\r\n";
    $headers .= "To: {$toName} <{$to}>\r\n";

    if (!empty($cc)) {
        $headers .= "Cc: " . implode(', ', $cc) . "\r\n";
    }

    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Message-ID: {$msgId}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: FluxPHP\r\n";

    // Build body
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $text . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= "--{$boundary}--";

    // Connect to SMTP
    $errno  = 0;
    $errstr = '';
    $prefix = $encrypt === 'ssl' ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 5);

    if (!$socket) {
        log_error("Mail SMTP connection failed: {$errstr}", ['host' => $host, 'port' => $port]);
        return false;
    }

    try {
        mail_smtp_expect($socket, 220);
        mail_smtp_cmd($socket, "EHLO fluxphp", 250);

        if ($encrypt === 'tls') {
            mail_smtp_cmd($socket, "STARTTLS", 220);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            mail_smtp_cmd($socket, "EHLO fluxphp", 250);
        }

        if ($username && $password) {
            mail_smtp_cmd($socket, "AUTH LOGIN", 334);
            mail_smtp_cmd($socket, base64_encode($username), 334);
            mail_smtp_cmd($socket, base64_encode($password), 235);
        }

        mail_smtp_cmd($socket, "MAIL FROM:<{$from}>", 250);
        mail_smtp_cmd($socket, "RCPT TO:<{$to}>", 250);

        foreach ($cc as $ccAddr) {
            mail_smtp_cmd($socket, "RCPT TO:<{$ccAddr}>", 250);
        }
        foreach ($bcc as $bccAddr) {
            mail_smtp_cmd($socket, "RCPT TO:<{$bccAddr}>", 250);
        }

        mail_smtp_cmd($socket, "DATA", 354);

        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        mail_smtp_expect($socket, 250);

        mail_smtp_cmd($socket, "QUIT", 221);
        fclose($socket);

        log_info("Mail sent to {$to}: {$subject}");
        return true;

    } catch (\Throwable $e) {
        fclose($socket);
        log_error("Mail send failed: " . $e->getMessage());
        return false;
    }
}

function mail_smtp_cmd($socket, string $cmd, int $expect): string
{
    fwrite($socket, $cmd . "\r\n");
    return mail_smtp_expect($socket, $expect);
}

function mail_smtp_expect($socket, int $code): string
{
    $response = '';
    while ($line = fgets($socket, 512)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    $actual = (int) substr($response, 0, 3);
    if ($actual !== $code) {
        throw new \RuntimeException("SMTP expected {$code}, got {$actual}: {$response}");
    }
    return $response;
}
