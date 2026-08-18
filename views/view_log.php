<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1 class="mono ltr trunc-title"><?= e(basename($rel)) ?></h1>
    <p class="muted mono ltr"><?= e($rel) ?> · <?= bytes_html($size) ?> · <?= date('Y-m-d H:i', $mtime) ?></p>
  </div>
  <div class="head-actions">
    <a class="btn btn-ghost" href="?page=analyze&amp;file=<?= urlencode($rel) ?><?= e(src_qs()) ?>">📊 <?= __('الأكثر تكرارًا') ?></a>
    <a class="btn btn-ghost" href="?page=download&amp;file=<?= urlencode($rel) ?><?= e(src_qs()) ?>">⬇ <?= __('تحميل') ?></a>
    <a class="btn btn-ghost" href="?page=dashboard<?= e(src_qs()) ?>">← <?= __('رجوع') ?></a>
  </div>
</div>

<?php if ($tooBig): ?>
  <div class="alert alert-warn">
    <?= sprintf(__('الملف أكبر من %d ميجابايت — يتم عرض آخر أسطر فقط. حمّل الملف لمراجعته كاملًا.'), (int)$maxMb) ?>
  </div>
<?php endif; ?>

<form class="toolbar" method="get" action="">
  <input type="hidden" name="page" value="view">
  <input type="hidden" name="file" value="<?= e($rel) ?>">
  <?php if (log_active_source()): ?><input type="hidden" name="src" value="<?= e(log_active_source()['name']) ?>"><?php endif; ?>
  <label class="toolbar-field">
    <span><?= __('آخر') ?></span>
    <input type="number" name="lines" min="10" max="20000" value="<?= (int)$lines ?>" style="width:6rem">
    <span><?= __('سطر') ?></span>
  </label>
  <input class="input-search" type="search" name="find" value="<?= e($needle) ?>" placeholder="<?= e(__('تلوين نص داخل اللوج…')) ?>">
  <button class="btn btn-ghost" type="submit"><?= __('تطبيق') ?></button>
  <?php foreach ([200, 500, 2000, 5000] as $preset): ?>
    <a class="chip-day <?= $lines === $preset ? 'is-active' : '' ?>"
       href="?page=view&amp;file=<?= urlencode($rel) ?>&amp;lines=<?= $preset ?>&amp;find=<?= urlencode($needle) ?><?= e(src_qs()) ?>"><?= $preset ?></a>
  <?php endforeach; ?>
</form>

<?php
$content = $tail['content'];
if ($content === '') {
    echo '<div class="empty"><div class="empty-mark">📄</div><h2>' . __('الملف فارغ') . '</h2></div>';
} else {
    // Escape first, then wrap matches — so the needle can never inject markup.
    $safe = e($content);
    if ($needle !== '') {
        $quoted = preg_quote(e($needle), '/');
        $safe = preg_replace('/(' . $quoted . ')/iu', '<mark>$1</mark>', $safe) ?? $safe;
    }
    $lineList = explode("\n", $safe);
    $startNo  = 1;
    echo '<div class="logview"><table class="logtable"><tbody>';
    foreach ($lineList as $i => $line) {
        $cls = '';
        $plain = strtolower(strip_tags($line));
        if (str_contains($plain, 'error') || str_contains($plain, 'fatal') || str_contains($plain, 'exception') || str_contains($plain, 'critical')) {
            $cls = 'line-error';
        } elseif (str_contains($plain, 'warn')) {
            $cls = 'line-warn';
        } elseif (str_contains($plain, 'notice') || str_contains($plain, 'info')) {
            $cls = 'line-info';
        }
        printf(
            '<tr class="%s"><td class="lineno">%d</td><td class="linetext">%s</td></tr>',
            $cls,
            $startNo + $i,
            $line === '' ? '&nbsp;' : $line
        );
    }
    echo '</tbody></table></div>';
    if ($tail['truncated']) {
        echo '<p class="muted small">↑ ' . sprintf(__('معروض آخر %d سطر فقط من الملف.'), (int)$lines) . '</p>';
    }
}
?>

<?php layout_end(__('عرض') . ' ' . basename($rel), $flashes); ?>
