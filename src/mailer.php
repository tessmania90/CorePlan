<?php
// /var/www/html/mailer.php

function sendSmtpMail($to, $subject, $message) {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM settings WHERE config_key IN ('SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM')");
    $s = []; while($r = $stmt->fetch()) $s[$r['config_key']] = $r['config_value'];

    $host = $s['SMTP_HOST'] ?? '';
    $port = $s['SMTP_PORT'] ?? 587;
    $user = $s['SMTP_USER'] ?? '';
    $pass = $s['SMTP_PASS'] ?? '';
    $from = $s['SMTP_FROM'] ?? '';

    if(!$host || !$from) return "SMTP nicht vollständig konfiguriert.";

    // SMTPS (Port 465) nutzt direkt SSL, Port 587 nutzt STARTTLS
    $isSSL = ($port == 465);
    $socketHost = $isSSL ? "ssl://$host" : $host;

    $socket = @fsockopen($socketHost, $port, $errno, $errstr, 10);
    if(!$socket) return "Verbindung fehlgeschlagen: $errstr ($errno)";

    $res = fread($socket, 1024);
    fputs($socket, "EHLO $host\r\n"); $res = fread($socket, 1024);

    if (!$isSSL && $port != 25) {
        fputs($socket, "STARTTLS\r\n"); $res = fread($socket, 1024);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO $host\r\n"); $res = fread($socket, 1024);
    }

    if($user && $pass) {
        fputs($socket, "AUTH LOGIN\r\n"); $res = fread($socket, 1024);
        fputs($socket, base64_encode($user)."\r\n"); $res = fread($socket, 1024);
        fputs($socket, base64_encode($pass)."\r\n"); $res = fread($socket, 1024);
        if(substr($res, 0, 3) !== '235') return "Auth fehlgeschlagen: $res";
    }

    fputs($socket, "MAIL FROM: <$from>\r\n"); fread($socket, 1024);
    fputs($socket, "RCPT TO: <$to>\r\n"); fread($socket, 1024);
    fputs($socket, "DATA\r\n"); fread($socket, 1024);

    $headers  = "From: CorePlan <$from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
    $res = fread($socket, 1024);
    fputs($socket, "QUIT\r\n"); fclose($socket);

    if(substr($res, 0, 3) !== '250') return "Senden fehlgeschlagen: $res";
    return true;
}

function sendGotify($title, $message) {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM settings WHERE config_key IN ('GOTIFY_URL', 'GOTIFY_TOKEN')");
    $s = []; while($r = $stmt->fetch()) $s[$r['config_key']] = $r['config_value'];
    if(empty($s['GOTIFY_URL']) || empty($s['GOTIFY_TOKEN'])) return "Gotify nicht konfiguriert.";

    $url = rtrim($s['GOTIFY_URL'], '/') . '/message?token=' . $s['GOTIFY_TOKEN'];
    $data = ['title' => $title, 'message' => $message, 'priority' => 5];
    $options = [ 'http' => [ 'header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($data) ] ];
    $context  = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);
    
    if ($res === false) return "Verbindung zu Gotify fehlgeschlagen.";
    return true;
}