<?php
/** Tiny output-buffer helpers so each view can fill the layout's $content slot. */

function layout_start(): void
{
    ob_start();
}

function layout_end(string $title, array $flashes = []): void
{
    $content = ob_get_clean();
    require VIEWS_PATH . '/layout.php';
}

/** Byte sizes are latin "12.3 MB" — isolate them so RTL doesn't flip to "MB 12.3". */
function bytes_html(int|float $bytes): string
{
    return '<span class="ltr">' . e(human_bytes($bytes)) . '</span>';
}

function ago(int $timestamp): string
{
    $diff = time() - $timestamp;
    if ($diff < 60)    return __('الآن');
    if ($diff < 3600)  return floor($diff / 60) . ' ' . __('دقيقة');
    if ($diff < 86400) return floor($diff / 3600) . ' ' . __('ساعة');
    $days = floor($diff / 86400);
    if ($days < 30)    return $days . ' ' . __('يوم');
    if ($days < 365)   return floor($days / 30) . ' ' . __('شهر');
    return floor($days / 365) . ' ' . __('سنة');
}

function age_class(int $days): string
{
    if ($days >= 90) return 'age-old';
    if ($days >= 30) return 'age-mid';
    return 'age-new';
}
