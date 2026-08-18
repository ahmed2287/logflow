<?php
declare(strict_types=1);

/**
 * Background analysis jobs. A full-file scan of a multi-GB log can't finish
 * inside one web request (proxies cut the connection long before), so the
 * scan runs as a detached CLI worker on the server; spec, live progress, and
 * the final result all live in one JSON under data/jobs/. The web page only
 * ever reads that JSON — nothing is streamed to the browser.
 */

define('JOBS_PATH', DATA_PATH . '/jobs');

function job_id(string $type, string $path, string $extra = ''): string
{
    return md5($type . '|' . $path . '|' . $extra);
}

function job_file(string $id): string
{
    return JOBS_PATH . '/' . $id . '.json';
}

function job_read(string $id): ?array
{
    if (!is_file(job_file($id))) {
        return null;
    }
    $data = json_read(job_file($id), []);
    return $data ?: null;
}

/** Persist the spec and detach the CLI worker. */
function job_start(array $spec): bool
{
    if (!is_dir(JOBS_PATH)) {
        mkdir(JOBS_PATH, 0750, true);
    }
    $spec += [
        'status'     => 'running',
        'bytes_done' => 0,
        'started_at' => date('c'),
        'updated_at' => date('c'),
        'result'     => null,
    ];
    if (!json_write(job_file((string)$spec['id']), $spec)) {
        return false;
    }
    // Under FPM, PHP_BINARY is the FPM binary — the worker needs the CLI one.
    $php    = is_file('/usr/bin/php') ? '/usr/bin/php' : 'php';
    $worker = BASE_PATH . '/cli/analyze_worker.php';
    shell_exec(
        'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
        . escapeshellarg((string)$spec['id']) . ' >/dev/null 2>&1 &'
    );
    return true;
}

/** A "running" job whose worker stopped updating (killed, crashed…). */
function job_is_stale(array $job): bool
{
    return ($job['status'] ?? '') === 'running'
        && (time() - (int)strtotime((string)($job['updated_at'] ?? ''))) > 120;
}

function job_delete(string $id): void
{
    @unlink(job_file($id));
}
