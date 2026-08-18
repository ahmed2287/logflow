<?php
declare(strict_types=1);

/**
 * Log file discovery, safe reading, and deletion.
 *
 * Every path that arrives from the browser goes through log_resolve(), which
 * realpath()s it and confirms it still sits inside the configured log dir.
 * Nothing else in this file is allowed to touch the filesystem by raw input.
 */

function log_dir(): string
{
    $dir = trim((string)config('log_dir', ''));
    if ($dir === '') {
        return '';
    }
    $real = realpath($dir);
    return $real === false ? '' : $real;
}

function log_dir_status(): array
{
    $configured = trim((string)config('log_dir', ''));
    if ($configured === '') {
        return ['ok' => false, 'message' => 'لم يتم تحديد مسار اللوجات بعد. اذهب إلى الإعدادات.'];
    }
    $real = realpath($configured);
    if ($real === false) {
        return ['ok' => false, 'message' => 'المسار غير موجود: ' . $configured];
    }
    if (!is_dir($real)) {
        return ['ok' => false, 'message' => 'المسار ليس مجلدًا: ' . $real];
    }
    if (!is_readable($real)) {
        return ['ok' => false, 'message' => 'لا توجد صلاحية قراءة على المجلد: ' . $real];
    }
    return [
        'ok'       => true,
        'path'     => $real,
        'writable' => is_writable($real),
        'message'  => is_writable($real) ? '' : 'المجلد للقراءة فقط — الحذف لن يعمل.',
    ];
}

/**
 * Resolve a browser-supplied relative path to an absolute path inside the log
 * dir, or null if it escapes, doesn't exist, or isn't a regular file.
 */
function log_resolve(string $relative): ?string
{
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
    $base    = log_dir();
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
            $failed[] = ['rel' => $relative, 'reason' => 'مسار غير صالح أو خارج مجلد اللوجات'];
            continue;
        }
        if (!is_writable($path)) {
            $failed[] = ['rel' => $relative, 'reason' => 'لا توجد صلاحية حذف'];
            continue;
        }

        $size  = (int)filesize($path);
        $mtime = (int)filemtime($path);

        if (@unlink($path)) {
            $deleted[] = ['rel' => $relative, 'size' => $size, 'mtime' => date('c', $mtime)];
            $bytes    += $size;
        } else {
            $failed[] = ['rel' => $relative, 'reason' => 'فشل الحذف على مستوى نظام الملفات'];
        }
    }

    return ['deleted' => $deleted, 'failed' => $failed, 'bytes' => $bytes];
}
