<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1 class="mono ltr gradient-title" style="font-size: 1.6rem;"><?= e(basename($rel)) ?></h1>
    <p class="muted mono ltr" style="font-size: 0.85rem; margin-top: 0.2rem;"><?= e($rel) ?> · <?= bytes_html($size) ?> · <?= date('Y-m-d H:i', $mtime) ?></p>
  </div>
  <div class="head-actions">
    <a class="btn btn-primary" href="?page=analyze&amp;file=<?= urlencode($rel) ?><?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px;"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
      <span><?= __('الأكثر تكرارًا') ?></span>
    </a>
    <a class="btn btn-primary" href="?page=download&amp;file=<?= urlencode($rel) ?><?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span><?= __('تحميل') ?></span>
    </a>
    <a class="btn btn-primary" href="?page=dashboard<?= e(src_qs()) ?>">← <?= __('رجوع') ?></a>
  </div>
</div>

<?php if ($tooBig): ?>
  <div class="alert alert-warn">
    <?= sprintf(__('الملف أكبر من %d ميجابايت — يتم عرض آخر أسطر فقط. حمّل الملف لمراجعته كاملًا.'), (int)$maxMb) ?>
  </div>
<?php endif; ?>

<form class="saas-toolbar" method="get" action="">
  <input type="hidden" name="page" value="view">
  <input type="hidden" name="file" value="<?= e($rel) ?>">
  <?php if (log_active_source()): ?><input type="hidden" name="src" value="<?= e(log_active_source()['name']) ?>"><?php endif; ?>
  
  <div class="search-field" style="display: flex; align-items: center; position: relative; gap: 0.5rem; flex: 1; max-width: 460px;">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <input type="search" name="find" id="log-search-input" value="<?= e($needle) ?>" placeholder="<?= e(__('تلوين نص داخل اللوج…')) ?>" autocomplete="off" style="width: 100%; padding-inline-end: 7.5rem;">
    
    <div class="search-nav-controls" id="search-nav-controls" style="position: absolute; inset-inline-end: 0.5rem; display: flex; align-items: center; gap: 0.35rem; z-index: 5;">
      <span class="search-counter-badge" id="search-counter-badge" style="font-size: 0.78rem; font-weight: 700; color: var(--accent); background: var(--accent-soft); padding: 0.15rem 0.5rem; border-radius: 20px; white-space: nowrap; display: none;">0 / 0</span>
      <button type="button" class="btn-search-nav" id="btn-search-prev" title="<?= e(__('النتيجة السابقة (Shift + Enter)')) ?>" disabled style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text); border-radius: 6px; width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.75rem; transition: all 0.2s ease;">▲</button>
      <button type="button" class="btn-search-nav" id="btn-search-next" title="<?= e(__('النتيجة التالية (Enter)')) ?>" disabled style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text); border-radius: 6px; width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.75rem; transition: all 0.2s ease;">▼</button>
    </div>
  </div>

  <div style="display: flex; align-items: center; gap: 0.5rem;">
    <span class="small muted"><?= __('آخر') ?></span>
    <input type="number" name="lines" min="10" max="20000" value="<?= (int)$lines ?>" style="width: 6rem;">
    <span class="small muted"><?= __('سطر') ?></span>
  </div>

  <button class="btn btn-primary btn-sm" type="submit"><?= __('تطبيق') ?></button>

  <button type="button" class="btn btn-ghost btn-sm" id="btn-live-stream" data-file="<?= e($rel) ?>" data-offset="<?= $size ?>" title="<?= e(__('تشغيل بث اللوج المباشر')) ?>" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700; gap: 0.45rem; display: inline-flex; align-items: center; border-radius: var(--radius); transition: all 0.2s ease;">
    <span class="live-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block;"></span>
    <span class="live-label"><?= __('▶️ بث مباشر') ?></span>
  </button>

  <div style="display: flex; align-items: center; gap: 0.35rem; margin-inline-start: auto;">
    <?php foreach ([200, 500, 2000, 5000] as $preset): ?>
      <a class="btn btn-ghost btn-sm <?= $lines === $preset ? 'btn-primary' : '' ?>"
         href="?page=view&amp;file=<?= urlencode($rel) ?>&amp;lines=<?= $preset ?>&amp;find=<?= urlencode($needle) ?><?= e(src_qs()) ?>"><?= $preset ?></a>
    <?php endforeach; ?>
  </div>
</form>

<?php
$content = $tail['content'];
if ($content === '') {
    echo '<div class="empty"><div class="empty-mark">📄</div><h2>' . __('الملف فارغ') . '</h2></div>';
} else {
    $safe = e($content);
    if ($needle !== '') {
        $quoted = preg_quote(e($needle), '/');
        $safe = preg_replace('/(' . $quoted . ')/iu', '<mark>$1</mark>', $safe) ?? $safe;
    }
    $rawLines = explode("\n", $safe);
    $totalCount = count($rawLines);
    $reversedLines = array_reverse($rawLines, true);

    $errCount  = 0;
    $warnCount = 0;
    $infoCount = 0;
    $otherCount= 0;

    $preparedLines = [];
    foreach ($reversedLines as $origIdx => $line) {
        $cls   = 'line-default';
        $level = 'other';
        $plain = strtolower(strip_tags($line));
        if (
            str_contains($plain, 'error') || str_contains($plain, 'err') ||
            str_contains($plain, 'fatal') || str_contains($plain, 'exception') ||
            str_contains($plain, 'critical') || str_contains($plain, 'fail') ||
            str_contains($plain, 'denied') || str_contains($plain, 'panic') ||
            str_contains($plain, 'uncaught') || str_contains($plain, 'unhandled') ||
            str_contains($plain, 'crash') || str_contains($plain, 'killed') ||
            str_contains($plain, 'segfault') || str_contains($plain, 'timeout') ||
            preg_match('/\b(500|502|503|504|400|401|403|404)\b/', $plain)
        ) {
            $cls   = 'line-error';
            $level = 'error';
            $errCount++;
        } elseif (str_contains($plain, 'warn') || str_contains($plain, 'warning')) {
            $cls   = 'line-warn';
            $level = 'warn';
            $warnCount++;
        } elseif (str_contains($plain, 'notice') || str_contains($plain, 'info') || str_contains($plain, 'debug') || str_contains($plain, 'success') || str_contains($plain, '200 ok')) {
            $cls   = 'line-info';
            $level = 'info';
            $infoCount++;
        } else {
            $otherCount++;
        }
        $preparedLines[] = [
            'idx'   => $origIdx + 1,
            'line'  => $line === '' ? '&nbsp;' : $line,
            'cls'   => $cls,
            'level' => $level,
        ];
    }
    ?>

    <!-- Log Level Filters Toolbar -->
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
      <div class="log-level-toolbar" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
        <span class="muted small" style="font-weight: 700; font-size: 0.85rem; color: var(--text);"><?= __('تصفية المستويات:') ?></span>
        <button type="button" class="btn btn-sm btn-level-filter active" data-level="all" style="font-weight: 700;">
          <?= __('الكل') ?> (<span id="cnt-all"><?= $totalCount ?></span>)
        </button>
        <button type="button" class="btn btn-sm btn-level-filter" data-level="error" style="border: 1px solid rgba(239, 68, 68, 0.4); color: #ef4444; font-weight: 700;">
          🔴 <?= __('الأخطاء') ?> (<span id="cnt-error"><?= $errCount ?></span>)
        </button>
        <button type="button" class="btn btn-sm btn-level-filter" data-level="warn" style="border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; font-weight: 700;">
          🟡 <?= __('التحذيرات') ?> (<span id="cnt-warn"><?= $warnCount ?></span>)
        </button>
        <button type="button" class="btn btn-sm btn-level-filter" data-level="info" style="border: 1px solid rgba(59, 130, 246, 0.4); color: #3b82f6; font-weight: 700;">
          🔵 <?= __('المعلومات') ?> (<span id="cnt-info"><?= $infoCount ?></span>)
        </button>
      </div>

      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <input type="text" id="quick-line-search" class="form-control form-control-sm mono ltr" placeholder="🔍 <?= __('تصفية حية داخل السطور...') ?>" style="width: 250px; font-size: 0.82rem;">
      </div>
    </div>

    <div class="terminal-window">
      <div class="terminal-header">
        <div class="terminal-dots">
          <span class="dot dot-red"></span>
          <span class="dot dot-yellow"></span>
          <span class="dot dot-green"></span>
        </div>
        <div class="terminal-title"><?= e($rel) ?> — LogFlow Terminal Reader</div>
        <div class="muted small"><?= $totalCount ?> <?= __('سطر') ?></div>
      </div>
      <div class="logview">
        <table class="logtable">
          <tbody>
            <?php foreach ($preparedLines as $item): ?>
              <tr class="<?= $item['cls'] ?>" data-level="<?= $item['level'] ?>">
                <td class="lineno"><?= $item['idx'] ?></td>
                <td class="linetext"><?= $item['line'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($tail['truncated']): ?>
      <p class="muted small" style="margin-top: 0.75rem;">↑ <?= sprintf(__('معروض آخر %d سطر فقط من الملف.'), (int)$lines) ?></p>
    <?php endif; ?>
<?php } ?>

<?php layout_end(__('عرض') . ' ' . basename($rel), $flashes); ?>
