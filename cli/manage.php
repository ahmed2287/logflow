#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI admin helper — useful when you're locked out of the web UI.
 *
 *   php cli/manage.php users
 *   php cli/manage.php add <username> <password> [admin|viewer]
 *   php cli/manage.php passwd <username> <password>
 *   php cli/manage.php delete <username>
 *   php cli/manage.php set-dir /var/log/myapp [name]   (upsert a named source)
 *   php cli/manage.php sources
 *   php cli/manage.php cleanup <days> [--dry-run] [--src=<name>]
 *   php cli/manage.php audit [count]
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

$_SESSION = [];                 // audit_log() reads current_user() from here
$_SERVER['REMOTE_ADDR'] = 'cli';

$argv    = $_SERVER['argv'];
$command = $argv[1] ?? 'help';

function out(string $line = ''): void { fwrite(STDOUT, $line . PHP_EOL); }
function fail(string $line): never { fwrite(STDERR, $line . PHP_EOL); exit(1); }

switch ($command) {

    case 'users':
        $users = users_all();
        if (!$users) {
            out('No users yet. Add one:  php cli/manage.php add admin <password> admin');
            break;
        }
        out(str_pad('USERNAME', 24) . str_pad('ROLE', 10) . 'LAST LOGIN');
        foreach ($users as $user) {
            out(str_pad((string)$user['username'], 24)
                . str_pad((string)($user['role'] ?? '?'), 10)
                . (string)($user['last_login'] ?? '-'));
        }
        break;

    case 'add':
        $username = $argv[2] ?? fail('Usage: add <username> <password> [admin|viewer]');
        $password = $argv[3] ?? fail('Usage: add <username> <password> [admin|viewer]');
        $role     = $argv[4] ?? ROLE_VIEWER;
        [$ok, $message] = user_create($username, $password, $role);
        if (!$ok) {
            fail('✗ ' . $message);
        }
        audit_log('user_created', ['username' => $username, 'role' => $role, 'via' => 'cli'], 'cli');
        out('✓ ' . $message);
        break;

    case 'passwd':
        $username = $argv[2] ?? fail('Usage: passwd <username> <password>');
        $password = $argv[3] ?? fail('Usage: passwd <username> <password>');
        [$ok, $message] = user_set_password($username, $password);
        if (!$ok) {
            fail('✗ ' . $message);
        }
        audit_log('password_change', ['username' => $username, 'via' => 'cli'], 'cli');
        out('✓ ' . $message);
        break;

    case 'delete':
        $username = $argv[2] ?? fail('Usage: delete <username>');
        [$ok, $message] = user_delete($username);
        if (!$ok) {
            fail('✗ ' . $message);
        }
        audit_log('user_deleted', ['username' => $username, 'via' => 'cli'], 'cli');
        out('✓ ' . $message);
        break;

    case 'set-dir':
        $dir  = $argv[2] ?? fail('Usage: set-dir <absolute-path> [name]');
        $name = trim((string)($argv[3] ?? ''));
        $real = realpath($dir);
        if ($real === false || !is_dir($real)) {
            fail('✗ Not a directory: ' . $dir);
        }
        if ($name === '') {
            $name = basename($real) ?: 'اللوجات';
        }
        $config  = config_load();
        $before  = $config;
        // Upsert by name: same name updates its path, a new name is appended.
        $sources = array_values(array_filter(
            (array)$config['sources'],
            fn($s) => strcasecmp((string)($s['name'] ?? ''), $name) !== 0
        ));
        $sources[] = ['name' => $name, 'path' => $real];
        $config['sources'] = $sources;
        if (!config_save($config)) {
            fail('✗ Could not write ' . CONFIG_FILE);
        }
        audit_log('settings', ['before' => $before, 'after' => $config, 'via' => 'cli'], 'cli');
        out('✓ source "' . $name . '" = ' . $real . '  (' . count($sources) . ' source(s) total)');
        out('  readable: ' . (is_readable($real) ? 'yes' : 'NO') . ', writable: ' . (is_writable($real) ? 'yes' : 'NO'));
        break;

    case 'sources':
        $sources = log_sources();
        if (!$sources) {
            out('No sources configured. Add one:  php cli/manage.php set-dir /var/log/myapp myapp');
            break;
        }
        foreach ($sources as $source) {
            $real = realpath($source['path']);
            $ok   = $real !== false && is_dir($real) && is_readable($real);
            out(str_pad($source['name'], 24) . str_pad($ok ? 'ok' : 'INVALID', 10) . $source['path']);
        }
        break;

    case 'cleanup':
        $days = isset($argv[2]) ? (int)$argv[2] : fail('Usage: cleanup <days> [--dry-run] [--src=<name>]');
        $dry  = in_array('--dry-run', $argv, true);
        $only = null;
        foreach ($argv as $arg) {
            if (str_starts_with((string)$arg, '--src=')) {
                $only = substr((string)$arg, 6);
            }
        }

        $sources = log_sources();
        if (!$sources) {
            fail('✗ No sources configured.');
        }
        if ($only !== null) {
            $sources = array_values(array_filter($sources, fn($s) => $s['name'] === $only));
            if (!$sources) {
                fail('✗ Unknown source: ' . $only);
            }
        }

        foreach ($sources as $source) {
            log_source_select($source['name'], true);
            out('== ' . $source['name'] . ' (' . $source['path'] . ')');
            $status = log_dir_status();
            if (!$status['ok']) {
                out('  ✗ ' . $status['message']);
                continue;
            }

            $candidates = log_older_than($days);
            $totals     = log_totals($candidates);
            out(sprintf('  %s %d file(s), %s older than %d day(s):',
                $dry ? '[dry-run] would delete' : 'deleting', $totals['count'], human_bytes($totals['bytes']), $days));
            foreach ($candidates as $file) {
                out('    ' . str_pad(human_bytes($file['size']), 10) . $file['age_days'] . 'd  ' . $file['rel']);
            }
            if ($dry || !$candidates) {
                continue;
            }

            $result = log_delete(array_column($candidates, 'rel'));
            audit_log('cleanup', [
                'days'  => $days,
                'count' => count($result['deleted']),
                'bytes' => $result['bytes'],
                'files' => array_column($result['deleted'], 'rel'),
                'src'   => $source['name'],
                'via'   => 'cli',
            ], 'cli');
            out(sprintf('  ✓ deleted %d file(s), freed %s%s',
                count($result['deleted']), human_bytes($result['bytes']),
                $result['failed'] ? ', ' . count($result['failed']) . ' failed' : ''));
        }
        break;

    case 'audit':
        $limit = isset($argv[2]) ? (int)$argv[2] : 25;
        $rows  = audit_read([], 1, $limit)['rows'];
        foreach ($rows as $row) {
            $details = $row['details'] ?? [];
            $summary = isset($details['count'])
                ? $details['count'] . ' files, ' . human_bytes((int)($details['bytes'] ?? 0))
                : (string)($details['file'] ?? ($details['username'] ?? ''));
            out(str_pad((string)$row['ts'], 27)
                . str_pad((string)$row['user'], 16)
                . str_pad((string)$row['action'], 16)
                . $summary);
        }
        break;

    default:
        out(trim((string)file_get_contents(__FILE__, false, null, 0, 900)));
        out();
        out('Commands: users | add | passwd | delete | set-dir | sources | cleanup | audit');
}
