<?php
declare(strict_types=1);

/**
 * LogFlow Notification & SMTP Mailer Handler
 */

/**
 * Send an email via direct SMTP connection (Sockets) with TLS/SSL support.
 */
function smtp_send_email(
    string $host,
    int $port,
    string $crypto,
    string $user,
    string $pass,
    string $to,
    string $subject,
    string $bodyHtml
): array {
    if (empty($host) || empty($to)) {
        return ['ok' => false, 'message' => __('عنوان خادم الـ SMTP وإيميل المستلم مطلوبين.')];
    }

    $socketHost = ($crypto === 'ssl' ? 'ssl://' : '') . $host;
    $context    = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ]
    ]);

    $timeout = 10;
    $socket  = @stream_socket_client($socketHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['ok' => false, 'message' => sprintf(__('تعذر الاتصال بخادم الـ SMTP (%s:%d): %s'), $host, $port, $errstr)];
    }

    stream_set_timeout($socket, $timeout);

    $read = function () use ($socket): string {
        $res = '';
        while ($line = fgets($socket, 512)) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $res;
    };

    $write = function (string $cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $welcome = $read();
    if (empty($welcome) || !str_starts_with($welcome, '220')) {
        fclose($socket);
        return ['ok' => false, 'message' => __('استجابة خادم الـ SMTP غير صالحة: ') . $welcome];
    }

    // EHLO
    $write('EHLO ' . gethostname());
    $ehlo = $read();

    // STARTTLS if crypto is tls
    if ($crypto === 'tls') {
        $write('STARTTLS');
        $starttls = $read();
        if (!str_starts_with($starttls, '220')) {
            fclose($socket);
            return ['ok' => false, 'message' => __('فشل بدء تشفير STARTTLS: ') . $starttls];
        }
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);
            return ['ok' => false, 'message' => __('تعذر تفعيل تشفير TLS على الاتصال.')];
        }
        // EHLO again after TLS
        $write('EHLO ' . gethostname());
        $read();
    }

    // AUTH LOGIN
    if (!empty($user) && !empty($pass)) {
        $write('AUTH LOGIN');
        $authRes = $read();
        if (str_starts_with($authRes, '334')) {
            $write(base64_encode($user));
            $userRes = $read();
            if (!str_starts_with($userRes, '334')) {
                fclose($socket);
                return ['ok' => false, 'message' => __('فشل اسم المستخدم في الـ SMTP: ') . $userRes];
            }
            $write(base64_encode($pass));
            $passRes = $read();
            if (!str_starts_with($passRes, '235')) {
                fclose($socket);
                return ['ok' => false, 'message' => __('فشل تسجيل الدخول بالـ SMTP (كلمة المرور غير صحيحة): ') . $passRes];
            }
        }
    }

    // MAIL FROM & RCPT TO
    $senderEmail = !empty($user) ? $user : 'noreply@' . (gethostname() ?: 'localhost');
    $write('MAIL FROM: <' . $senderEmail . '>');
    $fromRes = $read();
    if (!str_starts_with($fromRes, '250')) {
        fclose($socket);
        return ['ok' => false, 'message' => __('خطأ في عنوان المرسل: ') . $fromRes];
    }

    $write('RCPT TO: <' . $to . '>');
    $toRes = $read();
    if (!str_starts_with($toRes, '250') && !str_starts_with($toRes, '251')) {
        fclose($socket);
        return ['ok' => false, 'message' => __('خطأ في عنوان المستلم: ') . $toRes];
    }

    // DATA
    $write('DATA');
    $dataRes = $read();
    if (!str_starts_with($dataRes, '354')) {
        fclose($socket);
        return ['ok' => false, 'message' => __('خطأ في بدء إرسال البيانات: ') . $dataRes];
    }

    // MIME Headers & Content
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: LogFlow Alerts <' . $senderEmail . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'X-Mailer: LogFlow Log Manager (viber-solutions)',
    ];

    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $bodyHtml . "\r\n.";
    $write($emailData);
    $sendRes = $read();

    $write('QUIT');
    fclose($socket);

    if (!str_starts_with($sendRes, '250')) {
        return ['ok' => false, 'message' => __('فشل تسليم الرسالة: ') . $sendRes];
    }

    return ['ok' => true, 'message' => __('تم إرسال البريد الإلكتروني بنجاح!')];
}

/**
 * Render HTML Email Template with Viber Solutions Branding
 */
function render_alert_email_html(string $title, string $detailsHtml, ?string $fileLink = null): string
{
    $domain = 'viber-solutions.com';
    $year   = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; direction: rtl; }
    .container { max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
    .header { background: linear-gradient(135deg, #ff6b00 0%, #ff8800 100%); padding: 20px 25px; text-align: right; }
    .header h2 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 800; }
    .content { padding: 25px; }
    .badge-error { display: inline-block; background-color: #ef4444; color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 15px; }
    .code-block { background-color: #0f172a; border-radius: 8px; border: 1px solid #334155; padding: 15px; font-family: monospace; color: #f87171; font-size: 13px; overflow-x: auto; margin: 15px 0; word-break: break-all; white-space: pre-wrap; }
    .btn { display: inline-block; background: linear-gradient(135deg, #ff6b00 0%, #ff8800 100%); color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 14px; margin-top: 15px; }
    .footer { background-color: #0f172a; padding: 15px 25px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #334155; }
    .footer a { color: #ff6b00; text-decoration: none; font-weight: bold; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2>🔥 LogFlow Notification Alert</h2>
    </div>
    <div class="content">
      <span class="badge-error">🚨 تنبيه خطأ في اللوجات</span>
      <h3 style="margin-top: 0; color: #ffffff;">{$title}</h3>
      <div class="code-block">{$detailsHtml}</div>
      <p style="color: #94a3b8; font-size: 13px;">تم رصد هذا الخطأ في نظام السجلات بتاريخ: <strong>" . date('Y-m-d H:i:s') . "</strong></p>
      {$fileLink}
    </div>
    <div class="footer">
      made by <a href="https://{$domain}" target="_blank">viber-solutions</a> &copy; {$year}
    </div>
  </div>
</body>
</html>
HTML;
}

/**
 * Send Test Email Handler
 */
function notify_send_test_email(): array
{
    $host   = (string)config('smtp_host', '');
    $port   = (int)config('smtp_port', 587);
    $crypto = (string)config('smtp_crypto', 'tls');
    $user   = (string)config('smtp_user', '');
    $pass   = (string)config('smtp_pass', '');
    $to     = (string)config('alert_recipient', '');

    if (empty($to)) {
        return ['ok' => false, 'message' => __('يرجى تحديد إيميل المستلم أولًا في الإعدادات.')];
    }

    $subject     = '🧪 إيميل تجريبي — LogFlow Notification Test';
    $detailsHtml = "تهانينا! نظام الإشعارات والبريد الإلكتروني يعمل بنجاح 100% على السيرفر.\n\n" .
                   "Host: {$host}:{$port} ({$crypto})\n" .
                   "Sender: {$user}\n" .
                   "Recipient: {$to}\n" .
                   "Timestamp: " . date('Y-m-d H:i:s');

    $bodyHtml = render_alert_email_html('اختبار ربط البريد الإلكتروني بنجاح', nl2br(e($detailsHtml)));

    return smtp_send_email($host, $port, $crypto, $user, $pass, $to, $subject, $bodyHtml);
}

/**
 * Helper to send HTTP JSON POST payload via curl or stream_socket
 */
function send_http_json_post(string $url, array $payload, array $headers = ['Content-Type: application/json']): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'message' => __('تم إرسال التنبيه التجريبي بنجاح!')];
        }
        return ['ok' => false, 'message' => sprintf(__('فشل الإرسال (كود HTTP %d): %s'), $httpCode, $curlError ?: substr((string)$response, 0, 150))];
    }

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($payload),
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ];
    $context = stream_context_create($opts);
    $res     = @file_get_contents($url, false, $context);
    if ($res !== false) {
        return ['ok' => true, 'message' => __('تم إرسال التنبيه التجريبي بنجاح!')];
    }

    return ['ok' => false, 'message' => __('فشل الاتصال برابط الـ Webhook. تأكد من صحة الرابط وعمل الخادم.')];
}

/**
 * Send Test Mattermost Alert Handler
 */
function notify_send_test_mattermost(): array
{
    $url = (string)config('mattermost_webhook_url', '');
    if (empty($url)) {
        return ['ok' => false, 'message' => __('يرجى تزويد رابط الـ Webhook الخاص بـ Mattermost أولاً.')];
    }
    $username = (string)config('mattermost_username', 'LogFlow Alert');
    $channel  = (string)config('mattermost_channel', '');

    $payload = [
        'text'     => "🧪 **[LogFlow Test Alert]** \n\nتهانينا! تم اختبار وتفعيل ربط إشعارات **Mattermost** بنجاح 🚀\n\n- **التاريخ:** " . date('Y-m-d H:i:s') . "\n- **المستخدم:** " . (current_user()['username'] ?? 'admin'),
        'username' => $username,
    ];
    if (!empty($channel)) {
        $payload['channel'] = $channel;
    }

    return send_http_json_post($url, $payload);
}

/**
 * Send Test Telegram Alert Handler
 */
function notify_send_test_telegram(): array
{
    $token  = (string)config('telegram_bot_token', '');
    $chatId = (string)config('telegram_chat_id', '');
    if (empty($token) || empty($chatId)) {
        return ['ok' => false, 'message' => __('يرجى تزويد توكن البوت ومعرف المحادثة (Chat ID) لـ Telegram أولاً.')];
    }

    $url     = 'https://api.telegram.org/bot' . urlencode($token) . '/sendMessage';
    $text    = "🧪 <b>[LogFlow Test Alert]</b>\n\nتهانينا! تم اختبار وتفعيل إشعارات <b>Telegram</b> بنجاح 🚀\n\n📅 <b>التوقيت:</b> " . date('Y-m-d H:i:s');
    $payload = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];

    return send_http_json_post($url, $payload);
}

/**
 * Send Test Generic Webhook Alert Handler
 */
function notify_send_test_webhook(): array
{
    $url = (string)config('webhook_url', '');
    if (empty($url)) {
        return ['ok' => false, 'message' => __('يرجى تزويد رابط الـ Webhook Endpoint أولاً.')];
    }
    $secret = (string)config('webhook_secret', '');

    $payload = [
        'event'     => 'test_ping',
        'message'   => 'LogFlow Generic Webhook Connection Test Successful',
        'timestamp' => date('Y-m-d H:i:s'),
        'user'      => current_user()['username'] ?? 'admin',
    ];

    $headers = ['Content-Type: application/json'];
    if (!empty($secret)) {
        $headers[] = 'X-LogFlow-Secret: ' . $secret;
    }

    return send_http_json_post($url, $payload, $headers);
}

/**
 * Rate-limited Alert Trigger on New Error Detection across Email, Mattermost, Telegram & Generic Webhooks
 */
function notify_on_log_error(string $errorSnippet, string $filename): void
{
    $emailEnabled      = (bool)config('email_enabled', false);
    $mattermostEnabled = (bool)config('mattermost_enabled', false);
    $telegramEnabled   = (bool)config('telegram_enabled', false);
    $webhookEnabled    = (bool)config('webhook_enabled', false);

    if (!$emailEnabled && !$mattermostEnabled && !$telegramEnabled && !$webhookEnabled) {
        return; // No notification channel enabled
    }

    $cooldownMinutes = (int)config('cooldown_minutes', 5);
    $stateFile       = DATA_PATH . '/notifications_state.json';
    $state           = file_exists($stateFile) ? json_decode((string)file_get_contents($stateFile), true) : [];
    if (!is_array($state)) {
        $state = [];
    }

    $key      = md5($filename . '_' . substr($errorSnippet, 0, 100));
    $lastSent = (int)($state[$key] ?? 0);

    if (time() - $lastSent < ($cooldownMinutes * 60)) {
        return; // Throttled / Cooldown active
    }

    $state[$key] = time();
    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

    // 1. Send Email Alert
    if ($emailEnabled) {
        $to = (string)config('alert_recipient', '');
        if (!empty($to)) {
            $host     = (string)config('smtp_host', '');
            $port     = (int)config('smtp_port', 587);
            $crypto   = (string)config('smtp_crypto', 'tls');
            $user     = (string)config('smtp_user', '');
            $pass     = (string)config('smtp_pass', '');
            $subject  = '🚨 [LogFlow Alert] خطأ جديد في الملف: ' . $filename;
            $fileLink = '<a href="?page=view&file=' . urlencode($filename) . '" class="btn">👁️ فتح وعرض الخطأ في النظام</a>';
            $bodyHtml = render_alert_email_html(
                'خطأ في ملف: ' . e($filename),
                e(substr($errorSnippet, 0, 1000)),
                $fileLink
            );
            smtp_send_email($host, $port, $crypto, $user, $pass, $to, $subject, $bodyHtml);
        }
    }

    // 2. Send Mattermost Alert
    if ($mattermostEnabled) {
        $mmUrl = (string)config('mattermost_webhook_url', '');
        if (!empty($mmUrl)) {
            $username = (string)config('mattermost_username', 'LogFlow Alert');
            $channel  = (string)config('mattermost_channel', '');
            $payload  = [
                'text'     => "🚨 **[LogFlow Error Alert]**\n\n**الملف:** `" . $filename . "`\n**الخطأ:**\n```\n" . substr($errorSnippet, 0, 800) . "\n```\n📅 **التوقيت:** " . date('Y-m-d H:i:s'),
                'username' => $username,
            ];
            if (!empty($channel)) {
                $payload['channel'] = $channel;
            }
            send_http_json_post($mmUrl, $payload);
        }
    }

    // 3. Send Telegram Alert
    if ($telegramEnabled) {
        $token  = (string)config('telegram_bot_token', '');
        $chatId = (string)config('telegram_chat_id', '');
        if (!empty($token) && !empty($chatId)) {
            $tgUrl   = 'https://api.telegram.org/bot' . urlencode($token) . '/sendMessage';
            $text    = "🚨 <b>[LogFlow Error Alert]</b>\n\n📁 <b>الملف:</b> <code>" . e($filename) . "</code>\n\n<code>" . e(substr($errorSnippet, 0, 500)) . "</code>\n\n📅 <b>التوقيت:</b> " . date('Y-m-d H:i:s');
            $payload = [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ];
            send_http_json_post($tgUrl, $payload);
        }
    }

    // 4. Send Generic Webhook Payload
    if ($webhookEnabled) {
        $whUrl = (string)config('webhook_url', '');
        if (!empty($whUrl)) {
            $secret  = (string)config('webhook_secret', '');
            $payload = [
                'event'        => 'log_error',
                'filename'     => $filename,
                'snippet'      => substr($errorSnippet, 0, 1000),
                'timestamp'    => date('Y-m-d H:i:s'),
                'server_time'  => time(),
            ];
            $headers = ['Content-Type: application/json'];
            if (!empty($secret)) {
                $headers[] = 'X-LogFlow-Secret: ' . $secret;
            }
            send_http_json_post($whUrl, $payload, $headers);
        }
    }
}
