<?php
// includes/mailer.php  (NEW) - sends emails with SMTP (no extra library needed)
// send_email() returns: 'sent'   = delivered to the mail server
//                       'logged' = email is not set up, saved in logs/emails.log.php instead
//                       'failed' = could not send

function mail_config() {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config/mail.php';
        $cfg = is_file($file) ? include $file : [];
        if (!is_array($cfg)) $cfg = [];

        // On Vercel the settings come from Environment Variables (no passwords in your files)
        $map = [
            'MAIL_ENABLED' => 'enabled', 'MAIL_HOST' => 'host', 'MAIL_PORT' => 'port',
            'MAIL_USERNAME' => 'username', 'MAIL_PASSWORD' => 'password',
            'MAIL_FROM' => 'from_email', 'MAIL_FROM_NAME' => 'from_name',
        ];
        foreach ($map as $env => $key) {
            $v = getenv($env);
            if ($v !== false && $v !== '') {
                $cfg[$key] = ($key === 'enabled') ? ($v === '1' || strtolower($v) === 'true') : $v;
            }
        }
    }
    return $cfg;
}

// The log file is a .php file that stops immediately, so nobody can read it from the browser
function log_email($status, $to, $subject, $body, $note = '') {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/emails.log.php';
    $text = '';
    if (!file_exists($file)) $text .= "<?php http_response_code(404); exit; ?>\n";
    $body = str_replace('<?', '< ?', $body);
    $text .= '[' . date('Y-m-d H:i:s') . "] $status to=$to subject=$subject $note\n$body\n----------------------------------------\n";
    // Vercel has no writable disk: if the file cannot be written, use the server log instead
    if (@file_put_contents($file, $text, FILE_APPEND | LOCK_EX) === false) {
        error_log($text);
    }
}

function smtp_send($cfg, $to, $subject, $body, &$err) {
    $port = (int)($cfg['port'] ?? 587);
    $host = $cfg['host'] ?? '';
    $verify = !empty($cfg['verify_ssl']);
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
    ]]);
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "Cannot connect to $host:$port ($errstr)"; return false; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function ($cmd) use ($fp, $read) { fwrite($fp, $cmd . "\r\n"); return $read(); };
    $fail = function ($resp) use (&$err, $fp) { $err = trim($resp); @fclose($fp); return false; };

    $r = $read();                         if (strpos($r, '220') !== 0) return $fail($r);
    $r = $send('EHLO localhost');         if (strpos($r, '250') !== 0) return $fail($r);
    if ($port !== 465) {
        $r = $send('STARTTLS');           if (strpos($r, '220') !== 0) return $fail($r);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail('TLS failed');
        $r = $send('EHLO localhost');     if (strpos($r, '250') !== 0) return $fail($r);
    }
    $r = $send('AUTH LOGIN');             if (strpos($r, '334') !== 0) return $fail($r);
    $r = $send(base64_encode($cfg['username']));  if (strpos($r, '334') !== 0) return $fail($r);
    $r = $send(base64_encode($cfg['password']));  if (strpos($r, '235') !== 0) return $fail($r);
    $r = $send('MAIL FROM:<' . $cfg['from_email'] . '>');  if (strpos($r, '250') !== 0) return $fail($r);
    $r = $send('RCPT TO:<' . $to . '>');  if (strpos($r, '250') !== 0 && strpos($r, '251') !== 0) return $fail($r);
    $r = $send('DATA');                   if (strpos($r, '354') !== 0) return $fail($r);

    $from_name = $cfg['from_name'] ?? 'Online Exam System';
    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($from_name) . '?= <' . $cfg['from_email'] . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
    fwrite($fp, $message . "\r\n.\r\n");
    $r = $read();                         if (strpos($r, '250') !== 0) return $fail($r);
    $send('QUIT');
    @fclose($fp);
    return true;
}

function send_email($to, $subject, $body) {
    $to = trim(str_replace(["\r", "\n"], '', $to));
    $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return 'failed';

    $cfg = mail_config();
    if (!empty($cfg['enabled'])) {
        $err = '';
        if (smtp_send($cfg, $to, $subject, $body, $err)) return 'sent';
        log_email('FAILED', $to, $subject, $body, 'error=' . $err);
        return 'failed';
    }
    // Not set up: try PHP mail(), otherwise just keep a copy in the log file
    if (@mail($to, $subject, $body, "From: no-reply@examsystem.local\r\nContent-Type: text/plain; charset=UTF-8")) {
        return 'sent';
    }
    log_email('LOGGED', $to, $subject, $body);
    return 'logged';
}
