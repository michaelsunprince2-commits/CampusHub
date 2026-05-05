<?php

require_once __DIR__ . '/env.php';

function mailSetting($key, $default = '')
{
    $value = trim(getenv($key) ?: '');
    return $value !== '' ? $value : $default;
}

function sendAppMail($to, $subject, $htmlBody, $textBody = '')
{
    $from = mailSetting('MAIL_FROM', 'no-reply@campusnest.local');
    $fromName = mailSetting('MAIL_FROM_NAME', 'CampusNest');
    $smtpHost = mailSetting('SMTP_HOST');

    if ($smtpHost !== '') {
        return sendSmtpMail($smtpHost, (int)mailSetting('SMTP_PORT', '587'), mailSetting('SMTP_USERNAME'), mailSetting('SMTP_PASSWORD'), mailSetting('SMTP_SECURE', 'tls'), $from, $fromName, $to, $subject, $htmlBody, $textBody);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . formatMailAddress($from, $fromName),
    ];

    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

function formatMailAddress($email, $name = '')
{
    return $name !== '' ? sprintf('"%s" <%s>', addcslashes($name, '"\\'), $email) : $email;
}

function sendSmtpMail($host, $port, $username, $password, $secure, $from, $fromName, $to, $subject, $htmlBody, $textBody = '')
{
    $remote = strtolower($secure) === 'ssl' ? 'ssl://' . $host : $host;
    $socket = fsockopen($remote, $port, $errno, $errstr, 20);

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!smtpExpect($socket, [220])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), [250])) {
        fclose($socket);
        return false;
    }

    if (strtolower($secure) === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', [220]) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        if (!smtpCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), [250])) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '' && $password !== '') {
        if (!smtpCommand($socket, 'AUTH LOGIN', [334]) ||
            !smtpCommand($socket, base64_encode($username), [334]) ||
            !smtpCommand($socket, base64_encode($password), [235])) {
            fclose($socket);
            return false;
        }
    }

    $boundary = 'campusnest_' . bin2hex(random_bytes(12));
    $textBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    $message = buildMimeMessage($from, $fromName, $to, $subject, $htmlBody, $textBody, $boundary);

    $ok = smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]) &&
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]) &&
        smtpCommand($socket, 'DATA', [354]);

    if ($ok) {
        fwrite($socket, $message . "\r\n.\r\n");
        $ok = smtpExpect($socket, [250]);
    }

    smtpCommand($socket, 'QUIT', [221]);
    fclose($socket);

    return $ok;
}

function buildMimeMessage($from, $fromName, $to, $subject, $htmlBody, $textBody, $boundary)
{
    $headers = [
        'From: ' . formatMailAddress($from, $fromName),
        'To: ' . $to,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    return implode("\r\n", $headers) . "\r\n\r\n" .
        '--' . $boundary . "\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
        $textBody . "\r\n\r\n" .
        '--' . $boundary . "\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
        $htmlBody . "\r\n\r\n" .
        '--' . $boundary . '--';
}

function smtpCommand($socket, $command, $expectedCodes)
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

function smtpExpect($socket, $expectedCodes)
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int)substr($response, 0, 3);
    return in_array($code, $expectedCodes, true);
}
