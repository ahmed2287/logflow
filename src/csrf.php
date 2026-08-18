<?php
declare(strict_types=1);

/** CSRF: one token per session, compared in constant time. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.');
    }
}
