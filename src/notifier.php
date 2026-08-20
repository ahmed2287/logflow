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
 * Rate-limited Alert Trigger on New Error Detection
 */
function notify_on_log_error(string $errorSnippet, string $filename): void
{
    if (!config('email_enabled', false)) {
        return;
    }

    $to = (string)config('alert_recipient', '');
    if (empty($to)) {
        return;
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
        return; // Rate limited
    }

    $state[$key] = time();
    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

    $host   = (string)config('smtp_host', '');
    $port   = (int)config('smtp_port', 587);
    $crypto = (string)config('smtp_crypto', 'tls');
    $user   = (string)config('smtp_user', '');
    $pass   = (string)config('smtp_pass', '');

    $subject  = '🚨 [LogFlow Alert] خطأ جديد في الملف: ' . $filename;
    $fileLink = '<a href="?page=view&file=' . urlencode($filename) . '" class="btn">👁️ فتح وعرض الخطأ في النظام</a>';

    $bodyHtml = render_alert_email_html(
        'خطأ في ملف: ' . e($filename),
        e(substr($errorSnippet, 0, 1000)),
        $fileLink
    );

    smtp_send_email($host, $port, $crypto, $user, $pass, $to, $subject, $bodyHtml);
}
