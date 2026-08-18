<?php
declare(strict_types=1);

/**
 * Server resource snapshot for the monitoring page (admin-only):
 * CPU / memory from /proc, processes from ps, partitions from df.
 */

function sys_load(): array
{
    $load = sys_getloadavg() ?: [0.0, 0.0, 0.0];
    return ['1' => $load[0], '5' => $load[1], '15' => $load[2]];
}

function sys_uptime(): int
{
    $raw = (string)@file_get_contents('/proc/uptime');
    return (int)floatval(explode(' ', $raw)[0] ?? '0');
}

/**
 * Busy % overall and per core: /proc/stat sampled twice, 200ms apart.
 * Keys: 'all' plus 0..N-1.
 */
function sys_cpu_usage(): array
{
    $read = function (): array {
        $out = [];
        foreach (@file('/proc/stat') ?: [] as $line) {
            if (!preg_match('/^cpu(\d*)\s+(.+)/', $line, $m)) {
                continue;
            }
            $v     = array_map('intval', preg_split('/\s+/', trim($m[2])));
            $idle  = ($v[3] ?? 0) + ($v[4] ?? 0);           // idle + iowait
            $out[$m[1] === '' ? 'all' : (int)$m[1]] = [$idle, array_sum($v)];
        }
        return $out;
    };

    $a = $read();
    usleep(200000);
    $b = $read();

    $usage = [];
    foreach ($b as $core => [$idle2, $total2]) {
        [$idle1, $total1] = $a[$core] ?? [0, 0];
        $dt = $total2 - $total1;
        $usage[$core] = $dt > 0 ? round((1 - ($idle2 - $idle1) / $dt) * 100, 1) : 0.0;
    }
    return $usage;
}

function sys_memory(): array
{
    $info = [];
    foreach (@file('/proc/meminfo') ?: [] as $line) {
        if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
            $info[$m[1]] = (int)$m[2] * 1024;
        }
    }
    $total = $info['MemTotal'] ?? 0;
    $avail = $info['MemAvailable'] ?? 0;
    return [
        'total'      => $total,
        'available'  => $avail,
        'used'       => max(0, $total - $avail),
        'buffers'    => $info['Buffers'] ?? 0,
        'cached'     => ($info['Cached'] ?? 0) + ($info['SReclaimable'] ?? 0),
        'swap_total' => $info['SwapTotal'] ?? 0,
        'swap_used'  => max(0, ($info['SwapTotal'] ?? 0) - ($info['SwapFree'] ?? 0)),
    ];
}

/** Real partitions only — pseudo filesystems are noise here. */
function sys_disks(): array
{
    $raw  = (string)shell_exec(
        'df -B1 --output=source,fstype,size,used,avail,pcent,target'
        . ' -x tmpfs -x devtmpfs -x squashfs -x overlay -x efivarfs 2>/dev/null'
    );
    $rows = [];
    foreach (array_slice(explode("\n", trim($raw)), 1) as $line) {
        $parts = preg_split('/\s+/', trim($line), 7);
        if (count($parts) < 7) {
            continue;
        }
        $rows[] = [
            'source' => $parts[0],
            'fstype' => $parts[1],
            'size'   => (int)$parts[2],
            'used'   => (int)$parts[3],
            'avail'  => (int)$parts[4],
            'pcent'  => (int)rtrim($parts[5], '%'),
            'mount'  => $parts[6],
        ];
    }
    return $rows;
}

/** Top processes, htop-style, by CPU or memory. */
function sys_processes(string $sort = 'cpu', int $limit = 30): array
{
    $flag = $sort === 'mem' ? '-pmem' : '-pcpu';
    $raw  = (string)shell_exec('ps axo pid,user:16,pcpu,pmem,rss,stat,etime,args --sort=' . $flag . ' 2>/dev/null');
    $rows = [];
    foreach (array_slice(explode("\n", trim($raw)), 1) as $line) {
        $parts = preg_split('/\s+/', trim($line), 8);
        if (count($parts) < 8) {
            continue;
        }
        $rows[] = [
            'pid'   => (int)$parts[0],
            'user'  => $parts[1],
            'cpu'   => (float)$parts[2],
            'mem'   => (float)$parts[3],
            'rss'   => (int)$parts[4] * 1024,
            'stat'  => $parts[5],
            'etime' => $parts[6],
            'cmd'   => $parts[7],
        ];
        if (count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}

function sys_process_count(): int
{
    return (int)trim((string)shell_exec('ps ax --no-headers 2>/dev/null | wc -l'));
}

function sys_uptime_human(int $seconds): string
{
    $days  = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $mins  = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days)  $parts[] = $days . ' ' . __('يوم');
    if ($hours) $parts[] = $hours . ' ' . __('ساعة');
    $parts[] = $mins . ' ' . __('دقيقة');
    return implode(' · ', $parts);
}
