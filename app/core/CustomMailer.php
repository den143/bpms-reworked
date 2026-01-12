<?php
// app/core/CustomMailer.php
// Purpose: A lightweight, dependency-free SMTP mailer using raw PHP sockets.

require_once __DIR__ . '/../config/env.php';

// Helper to read server response
function get_smtp_response($socket) {
    $data = "";
    while($str = fgets($socket, 515)) {
        $data .= $str;
        // SMTP response codes are 3 digits followed by a space (e.g., "250 OK")
        // Continuation lines have a hyphen (e.g., "250-AUTH")
        if(substr($str, 3, 1) == " ") break; 
    }
    return $data;
}

function sendCustomEmail($to, $subject, $body) {
    // 1. CONNECT
    // Opens a raw network socket to the SMTP server (e.g., smtp.gmail.com:587)
    $socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) {
        error_log("CustomMailer Error: Connect failed - $errstr ($errno)");
        return false;
    }
    get_smtp_response($socket); // Consume the initial greeting banner

    // 2. HANDSHAKE (EHLO)
    // Identify ourselves to the server
    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    get_smtp_response($socket);

    // 3. STARTTLS (Security)
    // Tell server we want to switch to an encrypted channel
    fwrite($socket, "STARTTLS\r\n");
    $tlsResponse = get_smtp_response($socket);
    if (strpos($tlsResponse, '220') === false) { 
        error_log("CustomMailer Error: STARTTLS failed. Response: $tlsResponse");
        fclose($socket); return false; 
    }

    // 4. ENCRYPT
    // Enable SSL/TLS encryption on the existing socket
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("CustomMailer Error: Crypto enable failed.");
        fclose($socket); return false;
    }

    // 5. AUTHENTICATE
    // Re-introduce ourselves over the encrypted channel
    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    get_smtp_response($socket);

    fwrite($socket, "AUTH LOGIN\r\n");
    get_smtp_response($socket);

    // Send Username (Base64 encoded)
    fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
    get_smtp_response($socket);

    // Send Password (Base64 encoded)
    fwrite($socket, base64_encode(SMTP_PASS) . "\r\n");
    $authResult = get_smtp_response($socket);
    
    // Expecting "235 Authentication successful"
    if (strpos($authResult, '235') === false) { 
        error_log("CustomMailer Error: Authentication failed. Response: $authResult");
        fclose($socket); return false; 
    }

    // 6. SEND EMAIL ENVELOPE
    fwrite($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
    get_smtp_response($socket);

    fwrite($socket, "RCPT TO: <$to>\r\n");
    get_smtp_response($socket);

    // 7. SEND CONTENT
    fwrite($socket, "DATA\r\n");
    get_smtp_response($socket);

    // Construct MIME Headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";

    // Send Body (Ends with a single dot on a new line)
    fwrite($socket, "$headers\r\n$body\r\n.\r\n");
    $result = get_smtp_response($socket);

    // 8. DISCONNECT
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    // Check if the final transmission was accepted (Code 250)
    if (strpos($result, '250') !== false) {
        return true;
    } else {
        error_log("CustomMailer Error: Email rejected by server. Response: $result");
        return false;
    }
}

function queueEmail($to, $subject, $body) {
    global $conn; // Ensure you have the DB connection available

    // Escape strings to prevent SQL errors
    $to = $conn->real_escape_string($to);
    $subject = $conn->real_escape_string($subject);
    $body = $conn->real_escape_string($body);

    $sql = "INSERT INTO email_queue (recipient_email, subject, body, status) 
            VALUES ('$to', '$subject', '$body', 'pending')";
    
    return $conn->query($sql);
}
?>