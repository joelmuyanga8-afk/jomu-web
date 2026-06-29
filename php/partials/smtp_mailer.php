<?php

function jomu_smtp_clean_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function jomu_smtp_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = jomu_smtp_clean_header_value($name);

    if ($name === '') {
        return $email;
    }

    $escapedName = addcslashes($name, "\\\"");
    return '"' . $escapedName . '" <' . $email . '>';
}

function jomu_smtp_parse_recipients(string $value): array
{
    $parts = preg_split('/[;,]+/', $value) ?: [];
    $recipients = [];

    foreach ($parts as $part) {
        $email = trim($part);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }

    return array_values(array_unique($recipients));
}

function jomu_smtp_read_response($socket, ?string &$response = null): int
{
    $response = '';
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^(\d{3})([\s-])/', $line, $matches) === 1) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    return $code;
}

function jomu_smtp_command($socket, string $command, array $expectedCodes, ?string &$reason = null): bool
{
    fwrite($socket, $command . "\r\n");
    $code = jomu_smtp_read_response($socket, $response);

    if (!in_array($code, $expectedCodes, true)) {
        $publicCommands = ['DATA', 'QUIT', 'STARTTLS'];
        $label = str_contains($command, ' ') ? strtok($command, ' ') : $command;
        if (!in_array(strtoupper((string) $label), $publicCommands, true) && !str_contains($command, ' ')) {
            $label = '[hidden]';
        }
        $reason = 'SMTP command failed: ' . $label . ' Response: ' . trim((string) $response);
        return false;
    }

    return true;
}

function jomu_smtp_enable_crypto_safely($socket, int $cryptoMethod): bool
{
    // Keep TLS warnings from dumping mail body arguments, including reset codes, into PHP logs.
    set_error_handler(static function (): bool {
        return true;
    });

    try {
        $enabled = stream_socket_enable_crypto($socket, true, $cryptoMethod);
    } finally {
        restore_error_handler();
    }

    return $enabled === true;
}

function jomu_smtp_build_body(string $textBody, string $htmlBody = ''): string
{
    $textBody = str_replace(["\r\n", "\r"], "\n", $textBody);
    $htmlBody = str_replace(["\r\n", "\r"], "\n", $htmlBody);

    if ($htmlBody === '') {
        return "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . str_replace("\n", "\r\n", $textBody);
    }

    $boundary = 'jomu_' . bin2hex(random_bytes(12));

    return "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . str_replace("\n", "\r\n", $textBody) . "\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . str_replace("\n", "\r\n", $htmlBody) . "\r\n\r\n"
        . "--{$boundary}--";
}

function jomu_smtp_dot_stuff(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $message = preg_replace('/^\./m', '..', $message) ?? $message;
    return str_replace("\n", "\r\n", $message);
}

function jomu_send_smtp_mail(string $to, string $subject, string $textBody, string $htmlBody = '', ?string &$reason = null, array $options = []): bool
{
    $host = env_value('SMTP_HOST');
    $encryption = strtolower((string) env_value('SMTP_ENCRYPTION', 'tls'));
    if ($encryption === 'starttls') {
        $encryption = 'tls';
    } elseif ($encryption === 'smtps') {
        $encryption = 'ssl';
    } elseif (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }
    $portDefault = $encryption === 'ssl' ? '465' : ($encryption === 'none' ? '25' : '587');
    $port = (int) env_value('SMTP_PORT', $portDefault);
    $username = env_value('SMTP_USERNAME');
    $password = env_value('SMTP_PASSWORD');
    $fromEmail = trim((string) ($options['from_email'] ?? env_value('SMTP_FROM_EMAIL', $username ?: '')));
    $fromName = (string) ($options['from_name'] ?? env_value('SMTP_FROM_NAME', 'JoMu'));
    $replyTo = trim((string) ($options['reply_to'] ?? ''));
    $timeout = (int) env_value('SMTP_TIMEOUT', '20');
    $recipients = jomu_smtp_parse_recipients($to);

    if (!$host || $port <= 0 || $fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $reason = 'SMTP host, port, or from email is missing/invalid in .env.';
        return false;
    }

    if ($recipients === []) {
        $reason = 'No valid email recipients were provided.';
        return false;
    }

    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $reason = 'Reply-To email address is invalid.';
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        $reason = 'SMTP connection failed: ' . $errstr;
        return false;
    }

    stream_set_timeout($socket, $timeout);
    $code = jomu_smtp_read_response($socket, $response);
    if ($code !== 220) {
        fclose($socket);
        $reason = 'SMTP server did not accept connection: ' . trim((string) $response);
        return false;
    }

    $serverName = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';

    if (!jomu_smtp_command($socket, 'EHLO ' . $serverName, [250], $reason)) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!jomu_smtp_command($socket, 'STARTTLS', [220], $reason)) {
            fclose($socket);
            return false;
        }

        if (!jomu_smtp_enable_crypto_safely($socket, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            $reason = 'SMTP STARTTLS negotiation failed.';
            return false;
        }

        if (!jomu_smtp_command($socket, 'EHLO ' . $serverName, [250], $reason)) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== null && $username !== '') {
        if ($password === null || $password === '') {
            fclose($socket);
            $reason = 'SMTP password is missing in .env.';
            return false;
        }

        if (!jomu_smtp_command($socket, 'AUTH LOGIN', [334], $reason)
            || !jomu_smtp_command($socket, base64_encode($username), [334], $reason)
            || !jomu_smtp_command($socket, base64_encode($password), [235], $reason)) {
            fclose($socket);
            return false;
        }
    }

    if (!jomu_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $reason)) {
        fclose($socket);
        return false;
    }

    foreach ($recipients as $recipient) {
        if (!jomu_smtp_command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251], $reason)) {
            fclose($socket);
            return false;
        }
    }

    if (!jomu_smtp_command($socket, 'DATA', [354], $reason)) {
        fclose($socket);
        return false;
    }

    $safeSubject = jomu_smtp_clean_header_value($subject);
    $toHeader = implode(', ', $recipients);
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . jomu_smtp_format_address($fromEmail, $fromName),
        'To: ' . $toHeader,
        'Subject: ' . $safeSubject,
        'MIME-Version: 1.0',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $serverName . '>',
    ];

    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $message = implode("\r\n", $headers) . "\r\n" . jomu_smtp_build_body($textBody, $htmlBody);
    fwrite($socket, jomu_smtp_dot_stuff($message) . "\r\n.\r\n");
    $code = jomu_smtp_read_response($socket, $response);

    if ($code !== 250) {
        fclose($socket);
        $reason = 'SMTP message send failed: ' . trim((string) $response);
        return false;
    }

    jomu_smtp_command($socket, 'QUIT', [221, 250], $quitReason);
    fclose($socket);

    return true;
}
