<?php
require_once __DIR__ . '/../config/env.php';

function get_smtp_response($socket) {
    $data = "";
    while($str = fgets($socket, 515)) {
        $data .= $str;
        if(substr($str, 3, 1) == " ") break; 
    }
    return $data;
}

function sendCustomEmail($to, $subject, $body) {
    // 1. CONNECT
    $socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) return false;
    get_smtp_response($socket); // Eat the banner

    // 2. HANDSHAKE
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    get_smtp_response($socket);

    // 3. STARTTLS
    fwrite($socket, "STARTTLS\r\n");
    $tlsResponse = get_smtp_response($socket);
    if (strpos($tlsResponse, '220') === false) { fclose($socket); return false; }

    // 4. ENCRYPT
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket); return false;
    }

    // 5. AUTHENTICATE
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    get_smtp_response($socket);

    fwrite($socket, "AUTH LOGIN\r\n");
    get_smtp_response($socket);

    fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
    get_smtp_response($socket);

    fwrite($socket, base64_encode(SMTP_PASS) . "\r\n");
    $authResult = get_smtp_response($socket);
    if (strpos($authResult, '235') === false) { fclose($socket); return false; }

    // 6. SEND
    fwrite($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
    get_smtp_response($socket);

    fwrite($socket, "RCPT TO: <$to>\r\n");
    get_smtp_response($socket);

    fwrite($socket, "DATA\r\n");
    get_smtp_response($socket);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";

    fwrite($socket, "$headers\r\n$body\r\n.\r\n");
    $result = get_smtp_response($socket);

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return (strpos($result, '250') !== false);
}
?>