<?php
declare(strict_types=1);

/**
 * Append-only audit trail (JSON Lines) — who did what, when, from where.
 * Written with LOCK_EX so parallel requests append whole lines.
 */

const AUDIT_ACTIONS = [
    'login'          => 'تسجيل دخول',
    'login_failed'   => 'محاولة دخول فاشلة',
    'logout'         => 'تسجيل خروج',
    'view'           => 'عرض ملف',
    'analyze'        => 'تحليل تكرار',
    'download'       => 'تحميل ملف',
    'delete_file'    => 'حذف ملف',
    'cleanup'        => 'تنظيف اللوجات',
    'cleanup_request' => 'طلب تنظيف',
    'cleanup_reject' => 'رفض طلب تنظيف',
    'settings'       => 'تعديل الإعدادات',
    'user_created'   => 'إضافة مستخدم',
    'user_deleted'   => 'حذف مستخدم',
    'password_change' => 'تغيير كلمة المرور',
];

function audit_log(string $action, array $details = [], ?string $actor = null): void
{
    $entry = [
        'ts'      => date('c'),
        'user'    => $actor ?? (current_user()['username'] ?? 'anonymous'),
        'ip'      => client_ip(),
        'action'  => $action,
        'details' => $details,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    @file_put_contents(AUDIT_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
    @chmod(AUDIT_FILE, 0640);
}

/**
 * Read the trail newest-first. Reads the whole file — fine for an internal
 * dashboard; rotate audit.log if it ever grows past a few tens of MB.
 *
 * @return array{rows: array<int,array>, total: int}
 */
function audit_read(array $filters = [], int $page = 1, int $perPage = 100): array
{
    if (!is_file(AUDIT_FILE)) {
        return ['rows' => [], 'total' => 0];
    }
    $lines = file(AUDIT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $rows  = [];

    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) {
            continue;
        }
        if (!audit_matches($row, $filters)) {
            continue;
        }
        $rows[] = $row;
    }

    $total  = count($rows);
    $offset = max(0, ($page - 1) * $perPage);

    return ['rows' => array_slice($rows, $offset, $perPage), 'total' => $total];
}

function audit_matches(array $row, array $filters): bool
{
    $user = $filters['user'] ?? '';
    if ($user !== '' && ($row['user'] ?? '') !== $user) {
        return false;
    }
    $action = $filters['action'] ?? '';
    if ($action !== '' && ($row['action'] ?? '') !== $action) {
        return false;
    }
    $from = $filters['from'] ?? '';
    if ($from !== '' && substr((string)($row['ts'] ?? ''), 0, 10) < $from) {
        return false;
    }
    $to = $filters['to'] ?? '';
    if ($to !== '' && substr((string)($row['ts'] ?? ''), 0, 10) > $to) {
        return false;
    }
    $q = mb_strtolower(trim((string)($filters['q'] ?? '')));
    if ($q !== '') {
        $haystack = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE) ?: '');
        if (!str_contains($haystack, $q)) {
            return false;
        }
    }
    return true;
}

/** Distinct usernames seen in the trail, for the filter dropdown. */
function audit_users(): array
{
    if (!is_file(AUDIT_FILE)) {
        return [];
    }
    $users = [];
    $fh = fopen(AUDIT_FILE, 'rb');
    if (!$fh) {
        return [];
    }
    while (($line = fgets($fh)) !== false) {
        $row = json_decode($line, true);
        if (is_array($row) && !empty($row['user'])) {
            $users[$row['user']] = true;
        }
    }
    fclose($fh);
    $list = array_keys($users);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

/** Per-user deletion totals, for the dashboard summary cards. */
function audit_delete_stats(): array
{
    if (!is_file(AUDIT_FILE)) {
        return [];
    }
    $stats = [];
    $fh = fopen(AUDIT_FILE, 'rb');
    if (!$fh) {
        return [];
    }
    while (($line = fgets($fh)) !== false) {
        $row = json_decode($line, true);
        if (!is_array($row)) {
            continue;
        }
        $action = $row['action'] ?? '';
        if ($action !== 'delete_file' && $action !== 'cleanup') {
            continue;
        }
        $user = (string)($row['user'] ?? 'unknown');
        $stats[$user] ??= ['files' => 0, 'bytes' => 0, 'events' => 0, 'last' => null];
        $stats[$user]['events']++;
        $stats[$user]['files'] += (int)($row['details']['count'] ?? 1);
        $stats[$user]['bytes'] += (int)($row['details']['bytes'] ?? 0);
        $stats[$user]['last']   = $row['ts'] ?? $stats[$user]['last'];
    }
    fclose($fh);
    uasort($stats, fn($a, $b) => $b['files'] <=> $a['files']);
    return $stats;
}
