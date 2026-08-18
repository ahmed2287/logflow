<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

session_start_secure();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$page   = (string)($_GET['page'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Before any user exists, force the first-run setup screen.
if (needs_setup() && $page !== 'setup') {
    redirect('?page=setup');
}

switch ($page) {

    /* ------------------------------------------------------- first-run */
    case 'setup':
        if (!needs_setup()) {
            redirect('?page=dashboard');
        }
        if ($method === 'POST') {
            csrf_check();
            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $confirm  = (string)($_POST['confirm'] ?? '');

            if ($password !== $confirm) {
                flash('error', 'كلمتا المرور غير متطابقتين.');
                redirect('?page=setup');
            }
            [$ok, $message] = user_create($username, $password, ROLE_ADMIN);
            flash($ok ? 'success' : 'error', $message);
            if ($ok) {
                audit_log('user_created', ['username' => $username, 'role' => ROLE_ADMIN, 'first_run' => true], $username);
                redirect('?page=login');
            }
            redirect('?page=setup');
        }
        view('setup', ['flashes' => flash_take()]);
        break;

    /* ----------------------------------------------------------- login */
    case 'login':
        if (current_user()) {
            redirect('?page=dashboard');
        }
        if ($method === 'POST') {
            csrf_check();
            [$ok, $message] = login_attempt(
                (string)($_POST['username'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            flash($ok ? 'success' : 'error', $message);
            if ($ok) {
                $intended = $_SESSION['intended'] ?? '?page=dashboard';
                unset($_SESSION['intended']);
                redirect($intended);
            }
            redirect('?page=login');
        }
        view('login', ['flashes' => flash_take(), 'locked' => login_locked_out()]);
        break;

    case 'logout':
        if ($method === 'POST') {
            csrf_check();
            logout();
        }
        redirect('?page=login');

    /* ------------------------------------------------------- dashboard */
    case 'dashboard':
        require_login();
        $options = [
            'search'  => (string)($_GET['q'] ?? ''),
            'sort'    => (string)($_GET['sort'] ?? 'mtime'),
            'dir'     => (string)($_GET['dir'] ?? 'desc'),
            'min_age' => (string)($_GET['min_age'] ?? ''),
        ];
        $status  = log_dir_status();
        $skipped = [];
        $files   = $status['ok'] ? log_list($options, $skipped) : [];

        view('dashboard', [
            'flashes'  => flash_take(),
            'status'   => $status,
            'skipped'  => $skipped,
            'files'    => $files,
            'totals'   => log_totals($files),
            'options'  => $options,
            'stats'    => audit_delete_stats(),
            'recent'   => audit_read(['action' => ''], 1, 6)['rows'],
        ]);
        break;

    /* ------------------------------------------------------- view file */
    case 'view':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        if ($path === null) {
            http_response_code(404);
            flash('error', 'الملف غير موجود أو غير مسموح بالوصول إليه.');
            redirect('?page=dashboard');
        }

        $lines   = max(10, min(20000, (int)($_GET['lines'] ?? config('tail_lines', 500))));
        $maxMb   = (int)config('max_view_mb', 20);
        $size    = (int)filesize($path);
        $tooBig  = $size > $maxMb * 1024 * 1024;
        $tail    = $tooBig ? log_tail($path, min($lines, 2000)) : log_tail($path, $lines);
        $needle  = (string)($_GET['find'] ?? '');

        audit_log('view', ['file' => $rel, 'lines' => $lines]);

        view('view_log', [
            'flashes'  => flash_take(),
            'rel'      => $rel,
            'path'     => $path,
            'size'     => $size,
            'mtime'    => (int)filemtime($path),
            'lines'    => $lines,
            'tail'     => $tail,
            'needle'   => $needle,
            'tooBig'   => $tooBig,
            'maxMb'    => $maxMb,
        ]);
        break;

    /* --------------------------------------------------------- download */
    case 'download':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        if ($path === null) {
            http_response_code(404);
            exit('الملف غير موجود.');
        }
        audit_log('download', ['file' => $rel, 'bytes' => (int)filesize($path)]);

        // Force a download rather than letting the browser render log contents.
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        exit;

    /* ---------------------------------------------------- delete chosen */
    case 'delete':
        require_admin();
        if ($method !== 'POST') {
            redirect('?page=dashboard');
        }
        csrf_check();

        $selected = $_POST['files'] ?? [];
        if (!is_array($selected) || !$selected) {
            flash('error', 'لم تختر أي ملف.');
            redirect('?page=dashboard');
        }

        $result = log_delete($selected);
        if ($result['deleted']) {
            audit_log('delete_file', [
                'count'  => count($result['deleted']),
                'bytes'  => $result['bytes'],
                'files'  => array_column($result['deleted'], 'rel'),
                'mode'   => 'manual',
            ]);
            flash('success', sprintf(
                'تم حذف %d ملف (%s).',
                count($result['deleted']),
                human_bytes($result['bytes'])
            ));
        }
        if ($result['failed']) {
            flash('error', sprintf('فشل حذف %d ملف: %s', count($result['failed']),
                implode('، ', array_map(fn($f) => $f['rel'] . ' (' . $f['reason'] . ')', $result['failed']))));
        }
        redirect('?page=dashboard');

    /* ------------------------------------------- cleanup by age (days) */
    case 'cleanup':
        require_admin();
        $status = log_dir_status();
        if (!$status['ok']) {
            flash('error', $status['message']);
            redirect('?page=dashboard');
        }

        $days      = isset($_REQUEST['days']) && $_REQUEST['days'] !== '' ? (int)$_REQUEST['days'] : null;
        $confirmed = $method === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
        $candidates = [];
        $skipped    = [];

        if ($days !== null) {
            if ($days < 0) {
                flash('error', 'عدد الأيام لا يمكن أن يكون سالبًا.');
                redirect('?page=cleanup');
            }
            $candidates = log_older_than($days, $skipped);
        }

        if ($confirmed && $days !== null) {
            csrf_check();
            // Typing the exact day count again guards against a stray click on
            // a destructive, non-recoverable action.
            if ((string)($_POST['days_confirm'] ?? '') !== (string)$days) {
                flash('error', 'تأكيد عدد الأيام غير مطابق. أعد المحاولة.');
                redirect('?page=cleanup&days=' . $days);
            }

            $result = log_delete(array_column($candidates, 'rel'));
            audit_log('cleanup', [
                'days'   => $days,
                'count'  => count($result['deleted']),
                'bytes'  => $result['bytes'],
                'files'  => array_column($result['deleted'], 'rel'),
                'failed' => count($result['failed']),
                'mode'   => 'age',
            ]);

            flash(
                $result['deleted'] ? 'success' : 'info',
                sprintf(
                    'تنظيف الأقدم من %d يوم: تم حذف %d ملف (%s).%s',
                    $days,
                    count($result['deleted']),
                    human_bytes($result['bytes']),
                    $result['failed'] ? ' فشل ' . count($result['failed']) . ' ملف.' : ''
                )
            );
            redirect('?page=cleanup');
        }

        view('cleanup', [
            'flashes'    => flash_take(),
            'status'     => $status,
            'skipped'    => $skipped,
            'days'       => $days,
            'candidates' => $candidates,
            'totals'     => log_totals($candidates),
            'allCount'   => count(log_list()),
        ]);
        break;

    /* -------------------------------------------------------- audit log */
    case 'audit':
        require_login();
        $filters = [
            'user'   => (string)($_GET['user'] ?? ''),
            'action' => (string)($_GET['action'] ?? ''),
            'from'   => (string)($_GET['from'] ?? ''),
            'to'     => (string)($_GET['to'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
        ];
        // Viewers only ever see their own trail; admins see everyone's.
        if (!is_admin()) {
            $filters['user'] = current_user()['username'];
        }

        $pageNum = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 100;
        $result  = audit_read($filters, $pageNum, $perPage);

        view('audit', [
            'flashes' => flash_take(),
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $pageNum,
            'perPage' => $perPage,
            'filters' => $filters,
            'users'   => is_admin() ? audit_users() : [current_user()['username']],
            'stats'   => audit_delete_stats(),
        ]);
        break;

    /* --------------------------------------------------------- settings */
    case 'settings':
        require_admin();
        if ($method === 'POST') {
            csrf_check();
            $before  = config_load();
            $logDir  = trim((string)($_POST['log_dir'] ?? ''));
            $patterns = array_values(array_filter(array_map(
                'trim',
                explode(',', (string)($_POST['patterns'] ?? ''))
            )));

            $errors = [];
            if ($logDir === '') {
                $errors[] = 'المسار مطلوب.';
            } else {
                $real = realpath($logDir);
                if ($real === false || !is_dir($real)) {
                    $errors[] = 'المسار غير موجود أو ليس مجلدًا: ' . $logDir;
                } elseif (!is_readable($real)) {
                    $errors[] = 'لا توجد صلاحية قراءة على: ' . $real;
                } else {
                    $logDir = $real;
                }
            }

            if ($errors) {
                foreach ($errors as $error) {
                    flash('error', $error);
                }
                redirect('?page=settings');
            }

            $new = [
                'log_dir'     => $logDir,
                'patterns'    => $patterns ?: DEFAULT_CONFIG['patterns'],
                'recursive'   => isset($_POST['recursive']),
                'tail_lines'  => max(10, min(20000, (int)($_POST['tail_lines'] ?? 500))),
                'max_view_mb' => max(1, min(500, (int)($_POST['max_view_mb'] ?? 20))),
            ];

            if (config_save($new)) {
                audit_log('settings', ['before' => $before, 'after' => $new]);
                flash('success', 'تم حفظ الإعدادات.');
            } else {
                flash('error', 'تعذّر كتابة ملف الإعدادات. تأكد من صلاحيات مجلد data/.');
            }
            redirect('?page=settings');
        }

        view('settings', [
            'flashes' => flash_take(),
            'config'  => config_load(),
            'status'  => log_dir_status(),
        ]);
        break;

    /* ------------------------------------------------------------ users */
    case 'users':
        require_admin();
        if ($method === 'POST') {
            csrf_check();
            $action   = (string)($_POST['action'] ?? '');
            $username = trim((string)($_POST['username'] ?? ''));
            $me       = current_user()['username'];

            if ($action === 'create') {
                [$ok, $message] = user_create($username, (string)($_POST['password'] ?? ''), (string)($_POST['role'] ?? ROLE_VIEWER));
                if ($ok) {
                    audit_log('user_created', ['username' => $username, 'role' => $_POST['role'] ?? ROLE_VIEWER]);
                }
                flash($ok ? 'success' : 'error', $message);

            } elseif ($action === 'delete') {
                if (strcasecmp($username, $me) === 0) {
                    flash('error', 'لا يمكنك حذف حسابك الحالي.');
                } else {
                    [$ok, $message] = user_delete($username);
                    if ($ok) {
                        audit_log('user_deleted', ['username' => $username]);
                    }
                    flash($ok ? 'success' : 'error', $message);
                }

            } elseif ($action === 'password') {
                [$ok, $message] = user_set_password($username, (string)($_POST['password'] ?? ''));
                if ($ok) {
                    audit_log('password_change', ['username' => $username, 'by_admin' => true]);
                }
                flash($ok ? 'success' : 'error', $message);

            } elseif ($action === 'role') {
                if (strcasecmp($username, $me) === 0) {
                    flash('error', 'لا يمكنك تغيير صلاحية حسابك الحالي.');
                } else {
                    [$ok, $message] = user_set_role($username, (string)($_POST['role'] ?? ROLE_VIEWER));
                    if ($ok) {
                        audit_log('settings', ['user_role' => $username, 'role' => $_POST['role'] ?? '']);
                    }
                    flash($ok ? 'success' : 'error', $message);
                }
            }
            redirect('?page=users');
        }

        view('users', ['flashes' => flash_take(), 'users' => users_all()]);
        break;

    /* ---------------------------------------------------------- account */
    case 'account':
        require_login();
        if ($method === 'POST') {
            csrf_check();
            $me      = current_user()['username'];
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            $stored = user_find($me);
            if (!$stored || !password_verify($current, (string)$stored['password'])) {
                flash('error', 'كلمة المرور الحالية غير صحيحة.');
            } elseif ($new !== $confirm) {
                flash('error', 'كلمتا المرور الجديدتان غير متطابقتين.');
            } else {
                [$ok, $message] = user_set_password($me, $new);
                if ($ok) {
                    audit_log('password_change', ['username' => $me, 'by_admin' => false]);
                }
                flash($ok ? 'success' : 'error', $message);
            }
            redirect('?page=account');
        }
        view('account', ['flashes' => flash_take(), 'me' => current_user()]);
        break;

    default:
        http_response_code(404);
        require_login();
        flash('error', 'الصفحة غير موجودة.');
        redirect('?page=dashboard');
}
