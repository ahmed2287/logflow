<?php
declare(strict_types=1);

/**
 * Detached worker for background analysis jobs (see src/jobs.php).
 * Usage: php cli/analyze_worker.php <job-id>
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

$id  = $argv[1] ?? exit(1);
$job = job_read($id);
if ($job === null) {
    exit(1);
}

set_time_limit(0);
ini_set('memory_limit', '1024M');

$update = function (array $patch) use (&$job, $id): void {
    $job = array_merge($job, $patch, ['updated_at' => date('c')]);
    json_write(job_file($id), $job);
};

// Heartbeat every ~2s so the page can show live progress and detect a dead worker.
$lastTick = 0;
$progress = function (int $bytes) use ($update, &$lastTick): void {
    if (time() - $lastTick >= 2) {
        $lastTick = time();
        $update(['bytes_done' => $bytes]);
    }
};

try {
    $result = ($job['type'] ?? '') === 'repeat'
        ? log_repeat_detail((string)$job['path'], (string)$job['key'], PHP_INT_MAX, $progress)
        : log_top_repeats((string)$job['path'], 15, PHP_INT_MAX, $progress);

    $update([
        'status'      => 'done',
        'finished_at' => date('c'),
        'bytes_done'  => (int)($job['total_bytes'] ?? 0),
        'result'      => $result,
    ]);
} catch (Throwable $e) {
    $update(['status' => 'failed', 'error' => $e->getMessage()]);
    exit(1);
}
