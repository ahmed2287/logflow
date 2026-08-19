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
                flash('error', __('كلمتا المرور غير متطابقتين.'));
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
            'sources'  => log_sources(),
            'active'   => log_active_source(),
            // Non-admins only ever see their own trail — on the dashboard too.
            'stats'    => is_admin() ? audit_delete_stats() : [],
            'recent'   => audit_read(['user' => is_admin() ? '' : current_user()['username']], 1, 6)['rows'],
        ]);
        break;

    /* ------------------------------------------------------- view file */
    case 'view':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        if ($path === null) {
            http_response_code(404);
            flash('error', __('الملف غير موجود أو غير مسموح بالوصول إليه.'));
            redirect('?page=dashboard');
        }

        $lines   = max(10, min(20000, (int)($_GET['lines'] ?? config('tail_lines', 500))));
        $maxMb   = (int)config('max_view_mb', 20);
        $size    = (int)filesize($path);
        $tooBig  = $size > $maxMb * 1024 * 1024;
        $tail    = $tooBig ? log_tail($path, min($lines, 2000)) : log_tail($path, $lines);
        $needle  = (string)($_GET['find'] ?? '');

        audit_log('view', ['file' => $rel, 'lines' => $lines, 'src' => log_active_source()['name'] ?? '']);

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

    /* ------------------------------------------- real-time stream (tail -f) */
    case 'stream_log':
        require_login();
        header('Content-Type: application/json; charset=UTF-8');
        $rel    = (string)($_GET['file'] ?? '');
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $path   = log_resolve($rel);

        if ($path === null || !file_exists($path)) {
            echo json_encode(['ok' => false, 'error' => __('الملف غير موجود.')]);
            exit;
        }

        clearstatcache(true, $path);
        $currentSize = (int)filesize($path);
        $newLines    = [];

        // If file was truncated/rotated, rewind offset
        if ($currentSize < $offset) {
            $offset = max(0, $currentSize - 4096);
        }

        $newOffset = $offset;
        if ($currentSize > $offset) {
            $fp = @fopen($path, 'rb');
            if ($fp !== false) {
                fseek($fp, $offset, SEEK_SET);
                $bytesToRead = min(2 * 1024 * 1024, $currentSize - $offset);
                $newBytes    = fread($fp, $bytesToRead);
                $newOffset   = ftell($fp);
                fclose($fp);

                if ($newBytes !== false && $newBytes !== '') {
                    $rawLines = explode("\n", $newBytes);
                    // Filter empty trailing lines
                    foreach ($rawLines as $line) {
                        if ($line !== '') {
                            $newLines[] = e($line);
                        }
                    }
                }
            }
        }

        echo json_encode([
            'ok'        => true,
            'rel'       => $rel,
            'offset'    => $newOffset,
            'size'      => $currentSize,
            'new_lines' => $newLines,
            'count'     => count($newLines),
            'mtime'     => (int)filemtime($path),
        ]);
        exit;

    /* ------------------------------------------- repeat analysis (file) */
    case 'analyze':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        if ($path === null) {
            http_response_code(404);
            flash('error', __('الملف غير موجود أو غير مسموح بالوصول إليه.'));
            redirect('?page=dashboard' . src_qs());
        }

        // Full-file mode: the scan runs server-side in a detached worker (a
        // multi-GB file can't be scanned inside one web request); this page
        // only reads the job JSON — progress first, results when done.
        if ((string)($_GET['full'] ?? '') === '1') {
            $jobId = job_id('analyze', $path);
            if (isset($_GET['restart'])) {
                job_delete($jobId);
                redirect('?page=analyze&file=' . urlencode($rel) . '&full=1' . src_qs());
            }
            $job = job_read($jobId);
            if ($job === null) {
                job_start([
                    'id'          => $jobId,
                    'type'        => 'analyze',
                    'path'        => $path,
                    'rel'         => $rel,
                    'src'         => log_active_source()['name'] ?? '',
                    'total_bytes' => (int)filesize($path),
                ]);
                audit_log('analyze', ['file' => $rel, 'full' => true, 'src' => log_active_source()['name'] ?? '']);
                $job = job_read($jobId);
            }
            if (($job['status'] ?? '') === 'done') {
                view('analyze', [
                    'flashes' => flash_take(),
                    'rel'     => $rel,
                    'size'    => (int)filesize($path),
                    'mtime'   => (int)filemtime($path),
                    'mb'      => 50,
                    'report'  => $job['result'],
                    'full'    => true,
                    'job'     => $job,
                ]);
                break;
            }
            view('job_progress', [
                'flashes'  => flash_take(),
                'rel'      => $rel,
                'job'      => $job ?? [],
                'stale'    => $job ? job_is_stale($job) : false,
                'selfUrl'  => '?page=analyze&file=' . urlencode($rel) . '&full=1' . src_qs(),
                'backUrl'  => '?page=analyze&file=' . urlencode($rel) . src_qs(),
            ]);
            break;
        }

        // Quick mode: scan the newest window of the file inside the request.
        set_time_limit(300);
        $mb     = max(10, min(2000, (int)($_GET['mb'] ?? 50)));
        $report = log_top_repeats($path, 15, $mb * 1024 * 1024);
        audit_log('analyze', ['file' => $rel, 'lines' => $report['total'], 'src' => log_active_source()['name'] ?? '']);

        view('analyze', [
            'flashes' => flash_take(),
            'rel'     => $rel,
            'size'    => (int)filesize($path),
            'mtime'   => (int)filemtime($path),
            'mb'      => $mb,
            'report'  => $report,
            'full'    => false,
            'job'     => null,
        ]);
        break;

    /* --------------------------------- one repeated message, in detail */
    case 'repeat':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        $key  = (string)($_GET['k'] ?? '');
        if ($path === null || !preg_match('/^[0-9a-f]{32}$/', $key)) {
            http_response_code(404);
            flash('error', __('الملف غير موجود أو غير مسموح بالوصول إليه.'));
            redirect('?page=dashboard' . src_qs());
        }

        $full = (string)($_GET['full'] ?? '') === '1';
        $mb   = max(10, min(2000, (int)($_GET['mb'] ?? 50)));
        $job  = null;

        if ($full) {
            // Full-file scan runs server-side in a detached worker.
            $jobId = job_id('repeat', $path, $key);
            if (isset($_GET['restart'])) {
                job_delete($jobId);
                redirect('?page=repeat&file=' . urlencode($rel) . '&k=' . $key . '&full=1' . src_qs());
            }
            $job = job_read($jobId);
            if ($job === null) {
                job_start([
                    'id'          => $jobId,
                    'type'        => 'repeat',
                    'path'        => $path,
                    'rel'         => $rel,
                    'key'         => $key,
                    'src'         => log_active_source()['name'] ?? '',
                    'total_bytes' => (int)filesize($path),
                ]);
                $job = job_read($jobId);
            }
            if (($job['status'] ?? '') !== 'done') {
                view('job_progress', [
                    'flashes'  => flash_take(),
                    'rel'      => $rel,
                    'job'      => $job ?? [],
                    'stale'    => $job ? job_is_stale($job) : false,
                    'selfUrl'  => '?page=repeat&file=' . urlencode($rel) . '&k=' . $key . '&full=1' . src_qs(),
                    'backUrl'  => '?page=repeat&file=' . urlencode($rel) . '&k=' . $key . src_qs(),
                ]);
                break;
            }
            $detail = $job['result'];
        } else {
            set_time_limit(300);
            $detail = log_repeat_detail($path, $key, $mb * 1024 * 1024);
        }

        // Optional date range → occurrences inside it, from the per-day counts.
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? ''))   ? (string)$_GET['to']   : '';
        $rangeCount = null;
        if ($from !== '' || $to !== '') {
            $rangeCount = 0;
            foreach ($detail['days'] as $day => $n) {
                if (($from === '' || $day >= $from) && ($to === '' || $day <= $to)) {
                    $rangeCount += $n;
                }
            }
        }

        view('repeat', [
            'flashes' => flash_take(),
            'rel'     => $rel,
            'size'    => (int)filesize($path),
            'mb'      => $mb,
            'key'     => $key,
            'detail'  => $detail,
            'from'    => $from,
            'to'      => $to,
            'rangeCount' => $rangeCount,
            'full'    => $full,
            'job'     => $job,
        ]);
        break;

    /* --------------------------------------------------------- download */
    case 'download':
        require_login();
        $rel  = (string)($_GET['file'] ?? '');
        $path = log_resolve($rel);
        if ($path === null) {
            http_response_code(404);
            exit(__('الملف غير موجود.'));
        }
        audit_log('download', ['file' => $rel, 'bytes' => (int)filesize($path), 'src' => log_active_source()['name'] ?? '']);

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
            flash('error', __('لم تختر أي ملف.'));
            redirect('?page=dashboard');
        }

        $result = log_delete($selected);
        if ($result['deleted']) {
            audit_log('delete_file', [
                'count'  => count($result['deleted']),
                'bytes'  => $result['bytes'],
                'files'  => array_column($result['deleted'], 'rel'),
                'mode'   => 'manual',
                'src'    => log_active_source()['name'] ?? '',
            ]);
            flash('success', sprintf(
                __('تم حذف %d ملف (%s).'),
                count($result['deleted']),
                human_bytes($result['bytes'])
            ));
        }
        if ($result['failed']) {
            flash('error', sprintf(__('فشل حذف %d ملف: %s'), count($result['failed']),
                implode('، ', array_map(fn($f) => $f['rel'] . ' (' . $f['reason'] . ')', $result['failed']))));
        }
        redirect('?page=dashboard');

    /* ---------------------------------- cleanup: strip lines before date */
    case 'cleanup':
        // Viewers run the same cycle; their confirmed action becomes a
        // pending request that an admin approves (executes) or rejects.
        require_login();

        /* -- admin decides on a viewer's request -- */
        if ($method === 'POST' && isset($_POST['request_action'])) {
            require_admin();
            csrf_check();
            $reqId  = (string)($_POST['request_id'] ?? '');
            $reqRow = request_find($reqId);
            if ($reqRow === null || ($reqRow['status'] ?? '') !== 'pending') {
                flash('error', __('الطلب غير موجود أو تم البتّ فيه بالفعل.'));
                redirect('?page=cleanup' . src_qs());
            }
            $me = current_user()['username'];

            if ($_POST['request_action'] === 'reject') {
                request_update($reqId, ['status' => 'rejected', 'decided_by' => $me, 'decided_at' => date('c')]);
                audit_log('cleanup_reject', [
                    'request' => $reqId,
                    'user'    => $reqRow['user'],
                    'before'  => $reqRow['before'],
                    'target'  => $reqRow['target'],
                    'src'     => $reqRow['src'],
                ]);
                flash('info', sprintf(__('تم رفض طلب التنظيف المقدم من %s.'), $reqRow['user']));
                redirect('?page=cleanup' . src_qs());
            }

            // Approve: pin the request's source and execute with its parameters.
            log_source_select((string)$reqRow['src'] ?: null, true);
            $reqStatus = log_dir_status();
            if (!$reqStatus['ok']) {
                flash('error', $reqStatus['message']);
                redirect('?page=cleanup' . src_qs());
            }
            set_time_limit(0);
            $reqFiles   = log_list(['sort' => 'name', 'dir' => 'asc']);
            $reqTargets = $reqRow['target'] === '*'
                ? $reqFiles
                : array_values(array_filter($reqFiles, fn($f) => $f['rel'] === $reqRow['target']));

            $removedLines = 0;
            $removedBytes = 0;
            $touched      = [];
            $failed       = [];
            foreach ($reqTargets as $file) {
                $result = log_strip_before($file['rel'], (string)$reqRow['before']);
                if (!$result['ok']) {
                    $failed[] = $file['rel'] . ' (' . $result['reason'] . ')';
                    continue;
                }
                if ($result['removed'] === 0) {
                    continue;
                }
                $removedLines += $result['removed'];
                $removedBytes += $result['bytes'];
                $touched[]     = $file['rel'];
            }

            request_update($reqId, [
                'status'     => 'approved',
                'decided_by' => $me,
                'decided_at' => date('c'),
                'result'     => ['lines' => $removedLines, 'bytes' => $removedBytes, 'files' => count($touched)],
            ]);
            audit_log('cleanup', [
                'before'       => $reqRow['before'],
                'target'       => $reqRow['target'] === '*' ? 'all' : $reqRow['target'],
                'lines'        => $removedLines,
                'bytes'        => $removedBytes,
                'files'        => $touched,
                'failed'       => count($failed),
                'mode'         => 'lines-before-date',
                'src'          => $reqRow['src'],
                'requested_by' => $reqRow['user'],
                'approved_by'  => $me,
            ]);
            flash(
                $removedLines ? 'success' : 'info',
                sprintf(
                    __('تمت الموافقة على طلب %s: حذف %s سطر (%s) من %d ملف.%s'),
                    $reqRow['user'],
                    number_format($removedLines),
                    human_bytes($removedBytes),
                    count($touched),
                    $failed ? ' ' . __('فشل:') . ' ' . implode('، ', $failed) : ''
                )
            );
            log_source_select(null, true);
            redirect('?page=cleanup' . src_qs());
        }

        $status = log_dir_status();
        if (!$status['ok']) {
            flash('error', $status['message']);
            redirect('?page=dashboard');
        }

        $before    = trim((string)($_REQUEST['before'] ?? ''));   // Y-m-d from <input type=date>
        $target    = trim((string)($_REQUEST['file'] ?? ''));     // '*' = every file, else a rel path
        $confirmed = $method === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
        $skipped   = [];
        $files     = log_list(['sort' => 'name', 'dir' => 'asc'], $skipped);

        if ($before !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
            flash('error', __('صيغة التاريخ غير صحيحة.'));
            redirect('?page=cleanup' . src_qs());
        }

        $preview = [];
        $targets = [];
        if ($before !== '' && $target !== '') {
            // Scanning (and later rewriting) multi-GB logs outlasts the 30s default.
            set_time_limit($confirmed ? 0 : 600);
            $targets = $target === '*'
                ? $files
                : array_values(array_filter($files, fn($f) => $f['rel'] === $target));
            if (!$targets) {
                flash('error', __('الملف المطلوب غير موجود أو خارج مجلد اللوجات.'));
                redirect('?page=cleanup' . src_qs());
            }
            foreach ($targets as $file) {
                $preview[] = $file + ['scan' => log_scan_before($file['path'], $before)];
            }
        }

        if ($confirmed && $before !== '' && $targets) {
            csrf_check();
            // Typing the exact date again guards against a stray click on a
            // destructive, non-recoverable action.
            if (trim((string)($_POST['before_confirm'] ?? '')) !== $before) {
                flash('error', __('تأكيد التاريخ غير مطابق. أعد المحاولة.'));
                redirect('?page=cleanup&before=' . urlencode($before) . '&file=' . urlencode($target) . src_qs());
            }

            // Viewers don't execute — their confirmed cycle becomes a request.
            if (!is_admin()) {
                $totalRemove = array_sum(array_map(fn($f) => $f['scan']['remove'], $preview));
                $totalBytes  = array_sum(array_map(fn($f) => $f['scan']['bytes'], $preview));
                request_add([
                    'user'    => current_user()['username'],
                    'src'     => log_active_source()['name'] ?? '',
                    'before'  => $before,
                    'target'  => $target,
                    'preview' => [
                        'lines' => $totalRemove,
                        'bytes' => $totalBytes,
                        'files' => count(array_filter($preview, fn($f) => $f['scan']['remove'] > 0)),
                    ],
                ]);
                audit_log('cleanup_request', [
                    'before' => $before,
                    'target' => $target === '*' ? 'all' : $target,
                    'lines'  => $totalRemove,
                    'bytes'  => $totalBytes,
                    'src'    => log_active_source()['name'] ?? '',
                ]);
                flash('success', __('تم إرسال طلب التنظيف للمدير — سيتم التنفيذ بعد موافقته.'));
                redirect('?page=cleanup' . src_qs());
            }

            $removedLines = 0;
            $removedBytes = 0;
            $touched      = [];
            $failed       = [];
            foreach ($preview as $file) {
                // Nothing to strip → don't rewrite the file at all.
                if ($file['scan']['remove'] === 0) {
                    continue;
                }
                $result = log_strip_before($file['rel'], $before);
                if (!$result['ok']) {
                    $failed[] = $file['rel'] . ' (' . $result['reason'] . ')';
                    continue;
                }
                $removedLines += $result['removed'];
                $removedBytes += $result['bytes'];
                $touched[]     = ['rel' => $file['rel'], 'lines' => $result['removed'], 'bytes' => $result['bytes']];
            }

            audit_log('cleanup', [
                'before' => $before,
                'target' => $target === '*' ? 'all' : $target,
                'lines'  => $removedLines,
                'bytes'  => $removedBytes,
                'files'  => array_column($touched, 'rel'),
                'failed' => count($failed),
                'mode'   => 'lines-before-date',
                'src'    => log_active_source()['name'] ?? '',
            ]);

            flash(
                $removedLines ? 'success' : 'info',
                sprintf(
                    __('حذف السطور الأقدم من %s: تم حذف %s سطر (%s) من %d ملف.%s'),
                    date('d/m/Y', strtotime($before)),
                    number_format($removedLines),
                    human_bytes($removedBytes),
                    count($touched),
                    $failed ? ' ' . __('فشل:') . ' ' . implode('، ', $failed) : ''
                )
            );
            redirect('?page=cleanup' . src_qs());
        }

        view('cleanup', [
            'flashes'    => flash_take(),
            'status'     => $status,
            'skipped'    => $skipped,
            'before'     => $before,
            'target'     => $target,
            'files'      => $files,
            'preview'    => $preview,
            'sources'    => log_sources(),
            'active'     => log_active_source(),
            'pending'    => is_admin() ? requests_pending() : [],
            'myRequests' => is_admin() ? [] : requests_by_user(current_user()['username']),
        ]);
        break;

    /* -------------------------------------------------------- audit log */
    case 'audit':
        require_login();
        // Two audit views: "mine" (any user, own trail only) and "all"
        // (dashboard-wide, admins only). Admins land on "all" by default.
        $scope = (string)($_GET['scope'] ?? (is_admin() ? 'all' : 'mine'));
        $scope = $scope === 'all' ? 'all' : 'mine';
        if ($scope === 'all') {
            require_admin();
        }

        $filters = [
            'user'   => (string)($_GET['user'] ?? ''),
            'action' => (string)($_GET['action'] ?? ''),
            'from'   => (string)($_GET['from'] ?? ''),
            'to'     => (string)($_GET['to'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
        ];
        if ($scope === 'mine') {
            $filters['user'] = current_user()['username'];
        }

        $pageNum = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 100;
        $result  = audit_read($filters, $pageNum, $perPage);

        view('audit', [
            'flashes' => flash_take(),
            'scope'   => $scope,
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $pageNum,
            'perPage' => $perPage,
            'filters' => $filters,
            'users'   => $scope === 'all' ? audit_users() : [current_user()['username']],
            'stats'   => $scope === 'all' ? audit_delete_stats() : [],
        ]);
        break;

    /* --------------------------------------------------------- settings */
    case 'settings':
        require_admin();
        if ($method === 'POST') {
            csrf_check();
            $before  = config_load();
            $patterns = array_values(array_filter(array_map(
                'trim',
                explode(',', (string)($_POST['patterns'] ?? ''))
            )));

            $errors  = [];
            $sources = [];
            $seen    = [];
            foreach ((array)($_POST['sources'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string)($row['name'] ?? ''));
                $path = trim((string)($row['path'] ?? ''));
                $type = trim((string)($row['type'] ?? 'dir'));
                if (!in_array($type, ['dir', 'docker'], true)) {
                    $type = 'dir';
                }

                // A checked "remove" box or a fully blank row drops the source.
                if (!empty($row['remove']) || ($name === '' && $path === '')) {
                    continue;
                }
                if ($name === '' || $path === '') {
                    $errors[] = __('كل مسار لازم يكون له اسم ومسار معًا:') . ' ' . ($name !== '' ? $name : $path);
                    continue;
                }
                if (mb_strlen($name) > 40) {
                    $errors[] = __('الاسم أطول من 40 حرفًا:') . ' ' . $name;
                    continue;
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    $errors[] = __('الاسم مكرر:') . ' ' . $name;
                    continue;
                }

                if ($type === 'docker') {
                    $real = realpath($path);
                    $seen[$key] = true;
                    $sources[]  = ['name' => $name, 'path' => $real ?: $path, 'type' => 'docker'];
                } else {
                    $real = realpath($path);
                    if ($real === false || !is_dir($real)) {
                        $errors[] = __('المسار غير موجود أو ليس مجلدًا:') . ' ' . $path;
                        continue;
                    }
                    if (!is_readable($real)) {
                        $errors[] = __('لا توجد صلاحية قراءة على:') . ' ' . $real;
                        continue;
                    }
                    $seen[$key] = true;
                    $sources[]  = ['name' => $name, 'path' => $real, 'type' => 'dir'];
                }
            }

            if ($errors) {
                foreach ($errors as $error) {
                    flash('error', $error);
                }
                redirect('?page=settings');
            }

            $new = [
                'sources'     => $sources,
                'patterns'    => $patterns ?: DEFAULT_CONFIG['patterns'],
                'recursive'   => isset($_POST['recursive']),
                'tail_lines'  => max(10, min(20000, (int)($_POST['tail_lines'] ?? 500))),
                'max_view_mb' => max(1, min(500, (int)($_POST['max_view_mb'] ?? 20))),
            ];

            if (config_save($new)) {
                audit_log('settings', ['before' => $before, 'after' => $new]);
                flash('success', __('تم حفظ الإعدادات.'));
            } else {
                flash('error', __('تعذّر كتابة ملف الإعدادات. تأكد من صلاحيات مجلد data/.'));
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
                    flash('error', __('لا يمكنك حذف حسابك الحالي.'));
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
                    flash('error', __('لا يمكنك تغيير صلاحية حسابك الحالي.'));
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
                flash('error', __('كلمة المرور الحالية غير صحيحة.'));
            } elseif ($new !== $confirm) {
                flash('error', __('كلمتا المرور الجديدتان غير متطابقتين.'));
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

    /* --------------------------------------------------- server monitor */
    case 'server':
        require_login();
        $psort = (string)($_GET['psort'] ?? 'cpu') === 'mem' ? 'mem' : 'cpu';
        view('server', [
            'flashes'  => flash_take(),
            'hostname' => php_uname('n'),
            'load'     => sys_load(),
            'cpu'      => sys_cpu_usage(),
            'memory'   => sys_memory(),
            'uptime'   => sys_uptime(),
            'disks'    => sys_disks(),
            'procs'    => sys_processes($psort),
            'nproc'    => sys_process_count(),
            'psort'    => $psort,
            'auto'     => (string)($_GET['auto'] ?? '') === '1',
        ]);
        break;

    /* ------------------------------------------- real-time server stream (1s) */
    case 'stream_server':
        require_login();
        header('Content-Type: application/json; charset=UTF-8');
        $psort = (string)($_GET['psort'] ?? 'cpu') === 'mem' ? 'mem' : 'cpu';

        $load   = sys_load();
        $cpu    = sys_cpu_usage();
        $memory = sys_memory();
        $uptime = sys_uptime();
        $procs  = sys_processes($psort);
        $nproc  = sys_process_count();
        $cores  = array_filter($cpu, fn($k) => $k !== 'all', ARRAY_FILTER_USE_KEY);
        $coreCount = count($cores);
        $memPct = $memory['total'] ? round($memory['used'] * 100 / $memory['total'], 1) : 0;
        $swapPct = $memory['swap_total'] ? round($memory['swap_used'] * 100 / $memory['swap_total'], 1) : 0;

        $coreList = [];
        foreach ($cores as $idx => $pct) {
            $coreList[] = [
                'id'  => (int)$idx,
                'pct' => round((float)$pct, 1),
            ];
        }

        $procList = [];
        foreach ($procs as $p) {
            $procList[] = [
                'pid'   => (int)$p['pid'],
                'user'  => e($p['user']),
                'cpu'   => number_format((float)$p['cpu'], 1),
                'mem'   => number_format((float)$p['mem'], 1),
                'rss'   => bytes_html((int)$p['rss']),
                'stat'  => e($p['stat']),
                'etime' => e($p['etime']),
                'cmd'   => e($p['cmd']),
            ];
        }

        echo json_encode([
            'ok'        => true,
            'cpu_all'   => number_format((float)($cpu['all'] ?? 0), 1),
            'cpu_bar'   => min(100, (float)($cpu['all'] ?? 0)),
            'load1'     => number_format((float)$load['1'], 2),
            'load5'     => number_format((float)$load['5'], 2),
            'load15'    => number_format((float)$load['15'], 2),
            'load_pct'  => $coreCount ? round($load['1'] * 100 / $coreCount) : 0,
            'mem_used'  => bytes_html((int)$memory['used']),
            'mem_total' => bytes_html((int)$memory['total']),
            'mem_pct'   => $memPct,
            'swap_used' => bytes_html((int)$memory['swap_used']),
            'swap_total'=> bytes_html((int)$memory['swap_total']),
            'swap_pct'  => $swapPct,
            'uptime'    => sys_uptime_human($uptime),
            'nproc'     => number_format($nproc),
            'cores'     => $coreList,
            'procs'     => $procList,
            'timestamp' => date('H:i:s'),
        ]);
        exit;

    /* --------------------------------------------------------- language */
    case 'lang':
        $to = (string)($_GET['to'] ?? '');
        if (in_array($to, APP_LANGS, true)) {
            setcookie('logflow_lang', $to, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
        // Bounce back to where the switch was clicked (same-app query only).
        $backQuery = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY);
        redirect($backQuery !== '' ? '?' . $backQuery : '?page=dashboard');

    default:
        http_response_code(404);
        require_login();
        flash('error', __('الصفحة غير موجودة.'));
        redirect('?page=dashboard');
}
