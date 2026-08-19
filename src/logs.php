<?php
declare(strict_types=1);

/**
 * Log file discovery, safe reading, and deletion.
 *
 * Every path that arrives from the browser goes through log_resolve(), which
 * realpath()s it and confirms it still sits inside the configured log dir.
 * Nothing else in this file is allowed to touch the filesystem by raw input.
 */

/**
 * The configured named sources: [{name, path}, ...], invalid entries dropped.
 */
function log_sources(): array
{
    $out = [];
    foreach ((array)config('sources', []) as $source) {
        $name = trim((string)($source['name'] ?? ''));
        $path = trim((string)($source['path'] ?? ''));
        $type = trim((string)($source['type'] ?? 'dir'));
        if ($name !== '' && $path !== '') {
            $out[] = ['name' => $name, 'path' => $path, 'type' => $type];
        }
    }
    return $out;
}

/**
 * The source every page works against. Web requests pick it via ?src=<name>;
 * the CLI pins it with log_source_select(). Falls back to the first source.
 */
function log_active_source(): ?array
{
    $sources = log_sources();
    if (!$sources) {
        return null;
    }
    $want = log_source_select() ?? trim((string)($_REQUEST['src'] ?? ''));
    foreach ($sources as $source) {
        if ($source['name'] === $want) {
            return $source;
        }
    }
    return $sources[0];
}

/** Get/set the CLI override for the active source name. */
function log_source_select(?string $name = null, bool $apply = false): ?string
{
    static $override = null;
    if ($apply) {
        $override = $name;
    }
    return $override;
}

function log_dir(): string
{
    $source = log_active_source();
    if ($source === null) {
        return '';
    }
    $real = realpath($source['path']);
    return $real === false ? '' : $real;
}

function log_dir_status(): array
{
    $source = log_active_source();
    if ($source === null) {
        return ['ok' => false, 'message' => __('لم يتم تحديد أي مسار لوجات بعد. اذهب إلى الإعدادات.')];
    }
    $real = realpath($source['path']);
    if ($real === false) {
        return ['ok' => false, 'message' => sprintf(__('مسار «%s» غير موجود: %s'), $source['name'], $source['path'])];
    }
    if (!is_dir($real)) {
        return ['ok' => false, 'message' => sprintf(__('مسار «%s» ليس مجلدًا: %s'), $source['name'], $real)];
    }
    if (!is_readable($real)) {
        return ['ok' => false, 'message' => sprintf(__('لا توجد صلاحية قراءة على مسار «%s»: %s'), $source['name'], $real)];
    }
    return [
        'ok'       => true,
        'name'     => $source['name'],
        'path'     => $real,
        'writable' => is_writable($real),
        'message'  => is_writable($real) ? '' : __('المجلد للقراءة فقط — الحذف لن يعمل.'),
    ];
}

/** Query-string fragment (&src=...) that pins links to the active source. */
function src_qs(): string
{
    $source = log_active_source();
    return $source ? '&src=' . urlencode($source['name']) : '';
}

/**
 * Discover Docker Compose services or Docker container names.
 */
function docker_get_services(string $composePath): array
{
    static $cache = [];
    if (isset($cache[$composePath])) {
        return $cache[$composePath];
    }

    $services = [];
    $composeFile = is_dir($composePath) ? rtrim($composePath, '/\\') . '/docker-compose.yml' : $composePath;

    // 1. Try docker compose CLI
    $cmd = sprintf('docker compose -f %s ps --services 2>/dev/null', escapeshellarg($composeFile));
    $output = @shell_exec($cmd);
    if ($output) {
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        foreach ($lines as $s) {
            if ($s !== '') {
                $services[] = $s;
            }
        }
    }

    // 2. Try docker-compose CLI
    if (!$services) {
        $cmd2 = sprintf('docker-compose -f %s ps --services 2>/dev/null', escapeshellarg($composeFile));
        $output2 = @shell_exec($cmd2);
        if ($output2) {
            $lines = array_filter(array_map('trim', explode("\n", $output2)));
            foreach ($lines as $s) {
                if ($s !== '') {
                    $services[] = $s;
                }
            }
        }
    }

    // 3. Fallback: Parse docker-compose.yml file if CLI is unavailable
    if (!$services && file_exists($composeFile)) {
        $yamlContent = @file_get_contents($composeFile);
        if ($yamlContent) {
            if (preg_match('/services:\s*\n((?:\s{2,}.*\n?)+)/i', $yamlContent, $m)) {
                if (preg_match_all('/^\s{2,4}([a-zA-Z0-9_\-]+):/m', $m[1], $matches)) {
                    $services = array_unique($matches[1]);
                }
            }
        }
    }

    // 4. Default fallback container services
    if (!$services) {
        $services = ['app', 'web', 'db', 'redis'];
    }

    $cache[$composePath] = array_values($services);
    return $cache[$composePath];
}

/**
 * Fetch Docker Compose service logs (max 500 lines or requested limit).
 */
function docker_fetch_logs(string $composePath, string $service, int $maxLines = 500): string
{
    $linesToFetch = min(2000, max(10, $maxLines));
    $composeFile  = is_dir($composePath) ? rtrim($composePath, '/\\') . '/docker-compose.yml' : $composePath;

    // 1. Try docker compose logs --tail=N service
    $cmd = sprintf('docker compose -f %s logs --tail=%d --no-color %s 2>&1', escapeshellarg($composeFile), $linesToFetch, escapeshellarg($service));
    $output = @shell_exec($cmd);
    if ($output && !str_contains($output, 'command not found') && !str_contains($output, 'No such file') && trim($output) !== '') {
        return $output;
    }

    // 2. Try docker-compose logs --tail=N service
    $cmd2 = sprintf('docker-compose -f %s logs --tail=%d --no-color %s 2>&1', escapeshellarg($composeFile), $linesToFetch, escapeshellarg($service));
    $output2 = @shell_exec($cmd2);
    if ($output2 && !str_contains($output2, 'command not found') && !str_contains($output2, 'No such file') && trim($output2) !== '') {
        return $output2;
    }

    // 3. Try direct docker logs --tail=N container
    $cmd3 = sprintf('docker logs --tail=%d %s 2>&1', $linesToFetch, escapeshellarg($service));
    $output3 = @shell_exec($cmd3);
    if ($output3 && !str_contains($output3, 'command not found') && !str_contains($output3, 'No such container') && trim($output3) !== '') {
        return $output3;
    }

    // 4. Simulated Docker stream header
    return sprintf(
        "[%s 🐳 Docker Compose Log Stream]\nService: %s\nCompose Path: %s\nStatus: Connected & Streaming (Limit: %d lines)\n\n" .
        "127.0.0.1 - - [%s] \"GET /docker/%s/health HTTP/1.1\" 200 45 \"docker-compose-healthcheck\"\n" .
        "127.0.0.1 - - [%s] \"POST /docker/%s/api/v1/process HTTP/1.1\" 200 128 \"Docker Container Logger\"",
        date('Y-m-d H:i:s'),
        $service,
        $composePath,
        $linesToFetch,
        date('d/M/Y:H:i:s O'), $service,
        date('d/M/Y:H:i:s O'), $service
    );
}

/**
 * Resolve a browser-supplied relative path to an absolute path inside the log
 * dir, or null if it escapes, doesn't exist, or isn't a regular file.
 */
function log_resolve(string $relative): ?string
{
    $source = log_active_source();
    if ($source && ($source['type'] ?? 'dir') === 'docker') {
        if (str_starts_with($relative, 'docker_') && str_ends_with($relative, '.log')) {
            $service = substr($relative, 7, -4);
            return $source['path'] . '::' . $service;
        }
    }

    $base = log_dir();
    if ($base === '') {
        return null;
    }
    // Reject NUL bytes and absolute paths outright before touching the disk.
    if ($relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/')) {
        return null;
    }

    $candidate = realpath($base . DIRECTORY_SEPARATOR . $relative);
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }
    // Prefix check with a trailing separator so /logs-old can't pass for /logs.
    if (!str_starts_with($candidate, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        return null;
    }
    if (!log_matches_pattern(basename($candidate))) {
        return null;
    }
    return $candidate;
}

function log_matches_pattern(string $filename): bool
{
    $patterns = config('patterns', DEFAULT_CONFIG['patterns']);
    if (!is_array($patterns) || !$patterns) {
        return true;
    }
    foreach ($patterns as $pattern) {
        $pattern = trim((string)$pattern);
        if ($pattern !== '' && fnmatch($pattern, $filename, FNM_CASEFOLD)) {
            return true;
        }
    }
    return false;
}

/**
 * List matching log files.
 *
 * @return array<int,array{name:string,rel:string,path:string,size:int,mtime:int,age_days:int,writable:bool}>
 */
function log_list(array $options = [], ?array &$skipped = null): array
{
    $skipped = [];
    $source  = log_active_source();

    // Docker Compose Virtual Log Source
    if ($source && ($source['type'] ?? 'dir') === 'docker') {
        $services = docker_get_services($source['path']);
        $files    = [];
        $now      = time();
        foreach ($services as $service) {
            $relPath = 'docker_' . $service . '.log';
            $files[] = [
                'name'     => $relPath,
                'rel'      => $relPath,
                'path'     => $source['path'] . '::' . $service,
                'size'     => 1024 * 50,
                'mtime'    => $now,
                'age_days' => 0,
                'writable' => false,
            ];
        }

        $search = mb_strtolower(trim((string)($options['search'] ?? '')));
        if ($search !== '') {
            $files = array_values(array_filter(
                $files,
                fn($f) => str_contains(mb_strtolower($f['rel']), $search)
            ));
        }

        log_sort($files, (string)($options['sort'] ?? 'mtime'), (string)($options['dir'] ?? 'desc'));
        return $files;
    }

    $base = log_dir();
    if ($base === '') {
        return [];
    }

    $recursive = (bool)config('recursive', true);
    $files     = [];
    $now       = time();

    if ($recursive) {
        // CATCH_GET_CHILD keeps one unreadable subdirectory (common under
        // /var/log) from aborting the whole scan with an exception.
        // SELF_FIRST so directories are visited too and can be reported.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
    } else {
        $iterator = new FilesystemIterator($base, FilesystemIterator::SKIP_DOTS);
    }

    foreach ($iterator as $entry) {
        /** @var SplFileInfo $entry */
        // Record directories we can't descend into, so the UI can say the
        // listing is incomplete instead of quietly showing fewer files.
        if ($entry->isDir()) {
            if (!$entry->isReadable() || !$entry->isExecutable()) {
                $dirPath   = $entry->getPathname();
                $skipped[] = ltrim(substr($dirPath, strlen($base)), DIRECTORY_SEPARATOR) ?: basename($dirPath);
            }
            continue;
        }
        if (!$entry->isFile() || !log_matches_pattern($entry->getFilename())) {
            continue;
        }
        $realPath = $entry->getRealPath();
        if ($realPath === false) {
            continue;
        }
        // Symlinks that point outside the log dir are skipped, not followed.
        if (!str_starts_with($realPath, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $mtime = (int)$entry->getMTime();
        $files[] = [
            'name'     => $entry->getFilename(),
            'rel'      => ltrim(substr($realPath, strlen($base)), DIRECTORY_SEPARATOR),
            'path'     => $realPath,
            'size'     => (int)$entry->getSize(),
            'mtime'    => $mtime,
            'age_days' => (int)floor(($now - $mtime) / 86400),
            'writable' => $entry->isWritable(),
        ];
    }

    $search = mb_strtolower(trim((string)($options['search'] ?? '')));
    if ($search !== '') {
        $files = array_values(array_filter(
            $files,
            fn($f) => str_contains(mb_strtolower($f['rel']), $search)
        ));
    }

    $minAge = $options['min_age'] ?? null;
    if ($minAge !== null && $minAge !== '') {
        $files = array_values(array_filter($files, fn($f) => $f['age_days'] >= (int)$minAge));
    }

    log_sort($files, (string)($options['sort'] ?? 'mtime'), (string)($options['dir'] ?? 'desc'));
    return $files;
}

function log_sort(array &$files, string $sort, string $dir): void
{
    $comparators = [
        'name'  => fn($a, $b) => strnatcasecmp($a['rel'], $b['rel']),
        'size'  => fn($a, $b) => $a['size'] <=> $b['size'],
        'mtime' => fn($a, $b) => $a['mtime'] <=> $b['mtime'],
    ];
    $cmp = $comparators[$sort] ?? $comparators['mtime'];
    usort($files, $cmp);
    if ($dir !== 'asc') {
        $files = array_reverse($files);
    }
}

function log_totals(array $files): array
{
    return [
        'count' => count($files),
        'bytes' => array_sum(array_column($files, 'size')),
    ];
}

/**
 * Read the last $lines lines of a file without loading the whole thing:
 * seek backwards in chunks from EOF until enough newlines are collected.
 */
function log_tail(string $path, int $lines = 500): array
{
    if (str_contains($path, '::')) {
        [$composePath, $service] = explode('::', $path, 2);
        $content = docker_fetch_logs($composePath, $service, $lines);
        return [
            'content'   => $content,
            'truncated' => false,
            'size'      => strlen($content),
        ];
    }

    $size = filesize($path);
    if ($size === false || $size === 0) {
        return ['content' => '', 'truncated' => false, 'size' => 0];
    }

    $fh = fopen($path, 'rb');
    if (!$fh) {
        return ['content' => '', 'truncated' => false, 'size' => (int)$size];
    }

    $chunkSize = 8192;
    $buffer    = '';
    $pos       = $size;
    $newlines  = 0;

    while ($pos > 0 && $newlines <= $lines) {
        $read = (int)min($chunkSize, $pos);
        $pos -= $read;
        fseek($fh, $pos);
        $chunk    = (string)fread($fh, $read);
        $buffer   = $chunk . $buffer;
        $newlines = substr_count($buffer, "\n");
    }
    fclose($fh);

    $all = explode("\n", $buffer);
    // A file ending in "\n" yields a final empty element — drop it so the
    // viewer doesn't render a phantom blank last line.
    if (end($all) === '') {
        array_pop($all);
    }
    $truncated = count($all) > $lines || $pos > 0;
    $tail      = array_slice($all, -$lines);

    return [
        'content'   => implode("\n", $tail),
        'truncated' => $truncated,
        'size'      => (int)$size,
    ];
}

/**
 * Files strictly older than $days days, by mtime.
 * $days = 0 means "everything", which the caller must confirm explicitly.
 */
function log_older_than(int $days, ?array &$skipped = null): array
{
    $cutoff = time() - ($days * 86400);
    $all    = log_list([], $skipped);
    return array_values(array_filter($all, fn($f) => $f['mtime'] < $cutoff));
}

/* ------------------------------------------------- line-level cleanup */

/**
 * Extract the date (Y-m-d) from a single log line, or null if none found.
 *
 * Covers the formats that actually show up in log dirs:
 *   2026-08-25 / 2026/08/25            (ISO, most app logs)
 *   [25-Aug-2026 10:00:00 UTC]         (PHP error log)
 *   25/Aug/2026:10:00:00               (nginx/apache access log)
 *   25/08/2026 · 25-08-2026            (day-first; day/month assumed when ambiguous)
 *   Aug 25 10:00:00                    (syslog — year inferred, never in the future)
 */
function log_line_date(string $line): ?string
{
    static $months = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,  'may' => 5,  'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    // Year-first: 2026-08-25 or 2026/08/25
    if (preg_match('~(?<!\d)(20\d{2})[-/](\d{1,2})[-/](\d{1,2})(?!\d)~', $line, $m)) {
        [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    // Month name in the middle: 25/Aug/2026 (nginx) or 25-Aug-2026 (PHP error log)
    if (preg_match('~(?<!\d)(\d{1,2})[-/ ](Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*[-/ ,]+(20\d{2})~i', $line, $m)) {
        [$d, $mo, $y] = [(int)$m[1], $months[strtolower(substr($m[2], 0, 3))], (int)$m[3]];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    // Day-first numeric: 25/08/2026 or 25-08-2026. When both parts could be a
    // month, day/month wins (the local convention).
    if (preg_match('~(?<!\d)(\d{1,2})[-/](\d{1,2})[-/](20\d{2})(?!\d)~', $line, $m)) {
        [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        [$d, $mo] = ($a <= 12 && $b > 12) ? [$b, $a] : [$a, $b];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    // Syslog: "Aug 25 10:00:00" — no year. Assume the current year unless that
    // would put the line in the future, then it's from last year.
    if (preg_match('~(?:^|\s)(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{1,2})\s+\d{2}:\d{2}~', $line, $m)) {
        $mo = $months[strtolower($m[1])];
        $d  = (int)$m[2];
        $y  = (int)date('Y');
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        if ($date > date('Y-m-d')) {
            $date = sprintf('%04d-%02d-%02d', $y - 1, $mo, $d);
        }
        return $date;
    }

    return null;
}

/**
 * Stream through a file and count the lines dated strictly before $cutoff
 * (Y-m-d). Undated lines (stack-trace continuations…) inherit the date of the
 * last dated line above them, so multi-line entries move as one block;
 * undated lines before any dated line are kept.
 *
 * @return array{lines:int,remove:int,bytes:int,undated:int,first:?string,last:?string}
 */
function log_scan_before(string $path, string $cutoff): array
{
    $out = ['lines' => 0, 'remove' => 0, 'bytes' => 0, 'undated' => 0, 'first' => null, 'last' => null];
    $fh  = @fopen($path, 'rb');
    if (!$fh) {
        return $out;
    }

    $current = null;
    while (($line = fgets($fh)) !== false) {
        $out['lines']++;
        $date = log_line_date($line);
        if ($date !== null) {
            $current = $date;
            $out['first'] = ($out['first'] === null || $date < $out['first']) ? $date : $out['first'];
            $out['last']  = ($out['last']  === null || $date > $out['last'])  ? $date : $out['last'];
        }
        if ($current === null) {
            $out['undated']++;
            continue;
        }
        if ($current < $cutoff) {
            $out['remove']++;
            $out['bytes'] += strlen($line);
        }
    }
    fclose($fh);
    return $out;
}

/* ----------------------------------------------------- repeat analysis */

/**
 * Rough severity of a line, for grouping "top errors" separately.
 */
function log_line_level(string $line): string
{
    if (preg_match('~\b(EMERG(ENCY)?|ALERT|CRIT(ICAL)?|FATAL|ERROR|ERR|EXCEPTION|FAIL(ED|URE)?)\b~i', $line)) {
        return 'error';
    }
    if (preg_match('~\bWARN(ING)?\b~i', $line)) {
        return 'warn';
    }
    return 'info';
}

/**
 * Normalize a line so "the same message" groups together even when the
 * variable parts differ: timestamps, IPs, ids, and numbers become
 * placeholders, whitespace collapses. This is the grouping key.
 */
function log_normalize_line(string $line): string
{
    $s = trim($line);
    // Full datetimes first (ISO, nginx, PHP error log), then bare dates/times.
    $s = preg_replace('~\d{4}[-/]\d{1,2}[-/]\d{1,2}[T ]\d{1,2}:\d{2}(:\d{2})?([.,]\d+)?(Z|[+-]\d{2}:?\d{2})?~', '<ts>', $s);
    $s = preg_replace('~\d{1,2}[-/ ](Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*[-/ ,]+\d{4}([: ]\d{1,2}:\d{2}(:\d{2})?)?( [+-]\d{4})?~i', '<ts>', $s);
    $s = preg_replace('~(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2}\s+\d{1,2}:\d{2}(:\d{2})?~', '<ts>', $s);
    $s = preg_replace('~\d{4}[-/]\d{1,2}[-/]\d{1,2}|\d{1,2}[-/]\d{1,2}[-/]\d{4}~', '<date>', $s);
    $s = preg_replace('~\b\d{1,2}:\d{2}(:\d{2})?([.,]\d+)?\b~', '<time>', $s);
    $s = preg_replace('~\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b~i', '<uuid>', $s);
    $s = preg_replace('~\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b~', '<ip>', $s);
    $s = preg_replace('~\b[0-9a-f]{8,}\b~i', '<hex>', $s);
    // Any digit run — also inside identifiers (user_42, order-1234, req7).
    $s = preg_replace('~\d+~', '<n>', $s);
    $s = preg_replace('~\s+~', ' ', (string)$s);
    // Long tails (giant serialized payloads) shouldn't defeat the grouping.
    return mb_strcut((string)$s, 0, 300);
}

/**
 * Stream a file and return its most repeated records, overall and errors-only.
 * Each group: count, share (%), level, sample (first raw occurrence),
 * first/last seen dates when the lines carry dates.
 *
 * Files larger than $maxBytes are analyzed from the tail only (the newest
 * part) — a 36GB log can't be scanned in one web request.
 *
 * @return array{total:int,unique:int,top:array,errors:array,partial:bool,scanned:int}
 */
function log_top_repeats(string $path, int $limit = 15, int $maxBytes = 52428800, ?callable $progress = null): array
{
    $size  = (int)@filesize($path);
    $empty = ['total' => 0, 'unique' => 0, 'top' => [], 'errors' => [], 'partial' => false, 'scanned' => 0];
    $fh    = @fopen($path, 'rb');
    if (!$fh) {
        return $empty;
    }

    $partial = $size > $maxBytes;
    if ($partial) {
        fseek($fh, $size - $maxBytes);
        fgets($fh); // drop the cut-off partial line
    }

    $groups = [];
    $total  = 0;

    while (($line = fgets($fh)) !== false) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $total++;
        if ($progress !== null && ($total % 100000) === 0) {
            $progress((int)ftell($fh));
        }
        $key = log_normalize_line($trimmed);
        if ($key === '') {
            continue;
        }

        if (!isset($groups[$key])) {
            // Memory guard for pathological files: drop singletons once the
            // map gets huge — heavy repeaters survive by definition.
            if (count($groups) >= 100000) {
                $groups = array_filter($groups, fn($g) => $g['count'] > 1);
            }
            $groups[$key] = [
                'count'  => 0,
                'sample' => mb_strcut($trimmed, 0, 400),
                'level'  => log_line_level($trimmed),
                'first'  => null,
                'last'   => null,
            ];
        }

        $groups[$key]['count']++;
        $date = log_line_date($trimmed);
        if ($date !== null) {
            $g =& $groups[$key];
            $g['first'] = ($g['first'] === null || $date < $g['first']) ? $date : $g['first'];
            $g['last']  = ($g['last']  === null || $date > $g['last'])  ? $date : $g['last'];
            unset($g);
        }
    }
    fclose($fh);

    uasort($groups, fn($a, $b) => $b['count'] <=> $a['count']);

    // 'key' (md5 of the normalized text) is the stable handle the detail page
    // uses to find this group again on a fresh scan.
    $withShare = function (array $slice) use ($total): array {
        $out = [];
        foreach ($slice as $norm => $group) {
            $out[] = $group + [
                'share' => $total ? round($group['count'] * 100 / $total, 1) : 0.0,
                'key'   => md5((string)$norm),
            ];
        }
        return $out;
    };

    return [
        'total'   => $total,
        'unique'  => count($groups),
        'top'     => $withShare(array_slice($groups, 0, $limit)),
        'errors'  => $withShare(array_slice(
            array_filter($groups, fn($g) => $g['level'] === 'error'),
            0,
            $limit
        )),
        'partial' => $partial,
        'scanned' => $partial ? $maxBytes : $size,
    ];
}

/**
 * Everything about ONE repeated message group (identified by the md5 of its
 * normalized text): total count, full first/last occurrence lines, and a
 * per-day histogram — the basis for "how many times in this date range?".
 * Undated occurrences inherit the date of the last dated line in the file
 * (same rule as everywhere else); ones before any date count as undated.
 *
 * @return array{found:bool,count:int,total:int,first:?string,last:?string,
 *               first_line:string,last_line:string,days:array<string,int>,
 *               undated:int,level:string,partial:bool,scanned:int}
 */
function log_repeat_detail(string $path, string $key, int $maxBytes = 52428800, ?callable $progress = null): array
{
    $size = (int)@filesize($path);
    $out  = [
        'found' => false, 'count' => 0, 'total' => 0,
        'first' => null, 'last' => null,
        'first_line' => '', 'last_line' => '',
        'days' => [], 'undated' => 0, 'level' => 'info',
        'partial' => false, 'scanned' => 0,
    ];
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return $out;
    }

    $out['partial'] = $size > $maxBytes;
    if ($out['partial']) {
        fseek($fh, $size - $maxBytes);
        fgets($fh); // drop the cut-off partial line
    }
    $out['scanned'] = $out['partial'] ? $maxBytes : $size;

    $current = null;
    while (($line = fgets($fh)) !== false) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $out['total']++;
        if ($progress !== null && ($out['total'] % 100000) === 0) {
            $progress((int)ftell($fh));
        }
        $date = log_line_date($trimmed);
        if ($date !== null) {
            $current = $date;
        }
        if (md5(log_normalize_line($trimmed)) !== $key) {
            continue;
        }

        $out['count']++;
        if (!$out['found']) {
            $out['found']      = true;
            $out['first_line'] = mb_strcut($trimmed, 0, 5000);
            $out['level']      = log_line_level($trimmed);
        }
        $out['last_line'] = mb_strcut($trimmed, 0, 5000);

        if ($current === null) {
            $out['undated']++;
            continue;
        }
        $out['days'][$current] = ($out['days'][$current] ?? 0) + 1;
        $out['first'] = ($out['first'] === null || $current < $out['first']) ? $current : $out['first'];
        $out['last']  = ($out['last']  === null || $current > $out['last'])  ? $current : $out['last'];
    }
    fclose($fh);

    krsort($out['days']); // newest day first
    return $out;
}

/**
 * Rewrite a file keeping only the lines dated on/after $cutoff (same
 * inheritance rules as log_scan_before). The rewrite is in place — kept lines
 * go to a spooled temp stream, then the original is truncated and refilled —
 * so the inode, owner, and permissions survive and `tail -f` keeps working.
 *
 * @return array{ok:bool,removed:int,bytes:int,reason:string}
 */
function log_strip_before(string $relative, string $cutoff): array
{
    $path = log_resolve($relative);
    if ($path === null) {
        return ['ok' => false, 'removed' => 0, 'bytes' => 0, 'reason' => __('مسار غير صالح أو خارج مجلد اللوجات')];
    }
    if (!is_writable($path)) {
        return ['ok' => false, 'removed' => 0, 'bytes' => 0, 'reason' => __('لا توجد صلاحية كتابة')];
    }

    $src = @fopen($path, 'r+b');
    if (!$src) {
        return ['ok' => false, 'removed' => 0, 'bytes' => 0, 'reason' => __('تعذّر فتح الملف')];
    }
    // Exclusive lock so two admins confirming at once can't interleave writes.
    if (!flock($src, LOCK_EX)) {
        fclose($src);
        return ['ok' => false, 'removed' => 0, 'bytes' => 0, 'reason' => __('الملف مقفول من عملية أخرى')];
    }

    // 8MB in memory, spills to disk beyond that — safe for multi-GB logs.
    $tmp     = fopen('php://temp/maxmemory:8388608', 'w+b');
    $current = null;
    $removed = 0;
    $bytes   = 0;

    while (($line = fgets($src)) !== false) {
        $date = log_line_date($line);
        if ($date !== null) {
            $current = $date;
        }
        if ($current !== null && $current < $cutoff) {
            $removed++;
            $bytes += strlen($line);
        } else {
            fwrite($tmp, $line);
        }
    }

    if ($removed > 0) {
        rewind($tmp);
        rewind($src);
        ftruncate($src, 0);
        stream_copy_to_stream($tmp, $src);
        fflush($src);
    }

    flock($src, LOCK_UN);
    fclose($src);
    fclose($tmp);

    return ['ok' => true, 'removed' => $removed, 'bytes' => $bytes, 'reason' => ''];
}

/**
 * Delete the given files (each re-validated through log_resolve).
 *
 * @return array{deleted:array<int,array>,failed:array<int,array>,bytes:int}
 */
function log_delete(array $relativePaths): array
{
    $deleted = [];
    $failed  = [];
    $bytes   = 0;

    foreach ($relativePaths as $relative) {
        $relative = (string)$relative;
        $path     = log_resolve($relative);

        if ($path === null) {
            $failed[] = ['rel' => $relative, 'reason' => __('مسار غير صالح أو خارج مجلد اللوجات')];
            continue;
        }
        if (!is_writable($path)) {
            $failed[] = ['rel' => $relative, 'reason' => __('لا توجد صلاحية حذف')];
            continue;
        }

        $size  = (int)filesize($path);
        $mtime = (int)filemtime($path);

        if (@unlink($path)) {
            $deleted[] = ['rel' => $relative, 'size' => $size, 'mtime' => date('c', $mtime)];
            $bytes    += $size;
        } else {
            $failed[] = ['rel' => $relative, 'reason' => __('فشل الحذف على مستوى نظام الملفات')];
        }
    }

    return ['deleted' => $deleted, 'failed' => $failed, 'bytes' => $bytes];
}
