<?php
/**
 * LogFlow Inbound REST API Handler
 */

function api_validate_token(): bool
{
    $validToken = 'logflow_live_' . md5('viber_solutions_' . (get_current_user() ?: 'admin'));

    // Check Authorization header (Bearer xxx)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        if (hash_equals($validToken, trim($matches[1]))) {
            return true;
        }
    }

    // Check X-LogFlow-Key header
    $customHeader = $_SERVER['HTTP_X_LOGFLOW_KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($customHeader) && hash_equals($validToken, trim($customHeader))) {
        return true;
    }

    // Check query string token
    $queryToken = $_REQUEST['token'] ?? $_REQUEST['api_key'] ?? '';
    if (!empty($queryToken) && hash_equals($validToken, trim($queryToken))) {
        return true;
    }

    return false;
}

function handle_api_request(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-LogFlow-Key, X-API-Key');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (!api_validate_token()) {
        http_response_code(401);
        echo json_encode([
            'ok'      => false,
            'error'   => 'Unauthorized',
            'message' => 'Invalid or missing REST API Key. Pass your token via Bearer header or ?token= query string.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = strtolower(trim((string)($_REQUEST['action'] ?? 'status')));

    switch ($action) {
        case 'status':
        case 'health':
            $sources = log_sources();
            $files   = log_list();
            $bytes   = 0;
            foreach ($files as $f) {
                $bytes += (int)($f['size'] ?? 0);
            }

            echo json_encode([
                'ok'             => true,
                'system'         => 'LogFlow Monitoring Engine',
                'version'        => '2.4.0',
                'status'         => 'online',
                'stability'      => '100% Stable',
                'server_time'    => date('Y-m-d H:i:s'),
                'server_ts'      => time(),
                'active_sources' => count($sources),
                'total_files'    => count($files),
                'total_bytes'    => $bytes,
                'human_size'     => human_bytes($bytes),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;

        case 'files':
            $files = log_list();
            $out   = [];
            foreach ($files as $f) {
                $out[] = [
                    'name'       => $f['rel'],
                    'source'     => $f['source']['name'] ?? 'Default',
                    'size_bytes' => $f['size'],
                    'human_size' => human_bytes($f['size']),
                    'last_mtime' => date('Y-m-d H:i:s', $f['mtime']),
                ];
            }

            echo json_encode([
                'ok'          => true,
                'total_files' => count($out),
                'files'       => $out,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;

        case 'logs':
        case 'read':
            $fileName = trim((string)($_REQUEST['file'] ?? ''));
            if (empty($fileName)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Bad Request', 'message' => 'Parameter "file" is required.'], JSON_PRETTY_PRINT);
                exit;
            }

            $absPath = log_resolve($fileName);
            if (!$absPath || !is_file($absPath)) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Not Found', 'message' => 'Log file not found or access denied.'], JSON_PRETTY_PRINT);
                exit;
            }

            $maxLines  = min(max((int)($_REQUEST['lines'] ?? 100), 1), 5000);
            $filterLvl = strtolower(trim((string)($_REQUEST['level'] ?? 'all')));
            $needle    = trim((string)($_REQUEST['find'] ?? ''));

            $tail = log_tail($absPath, $maxLines);
            if (!isset($tail['content'])) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Not Found', 'message' => 'Log file not found or access denied.'], JSON_PRETTY_PRINT);
                exit;
            }

            $rawLines = explode("\n", $tail['content']);
            $parsed   = [];
            $idx      = 1;

            foreach ($rawLines as $line) {
                if ($line === '') continue;

                $lvl = 'info';
                if (preg_match('/(error|fatal|fail|critical|exception)/i', $line)) {
                    $lvl = 'error';
                } elseif (preg_match('/(warn|warning|notice)/i', $line)) {
                    $lvl = 'warn';
                }

                if ($filterLvl !== 'all' && $filterLvl !== $lvl) {
                    continue;
                }

                if ($needle !== '' && mb_stripos($line, $needle) === false) {
                    continue;
                }

                $parsed[] = [
                    'index' => $idx++,
                    'level' => $lvl,
                    'line'  => $line,
                ];
            }

            echo json_encode([
                'ok'          => true,
                'file'        => $fileName,
                'lines_count' => count($parsed),
                'lines'       => array_slice($parsed, -$maxLines),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;

        case 'audit':
            $result = audit_read([], 1, 50);
            echo json_encode([
                'ok'     => true,
                'count'  => count($result['items'] ?? []),
                'events' => $result['items'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;

        default:
            http_response_code(400);
            echo json_encode([
                'ok'               => false,
                'error'            => 'Unknown action',
                'available_actions' => ['status', 'files', 'logs', 'audit']
            ], JSON_PRETTY_PRINT);
            exit;
    }
}
