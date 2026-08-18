<?php
/** @var string $rel @var int $size @var int $mtime @var int $mb @var array $report */
layout_start();

$renderRows = function (array $groups) use ($rel, $mb, $full): void {
    foreach ($groups as $group) {
        $tag = ['error' => 'tag-error', 'warn' => 'tag-warn', 'info' => ''][$group['level']] ?? '';
        ?>
        <tr>
          <td class="col-num mono"><strong><?= number_format($group['count']) ?></strong></td>
          <td class="col-num mono"><?= number_format($group['share'], 1) ?>%</td>
          <td><span class="tag <?= $tag ?>"><?= __(['error' => 'خطأ', 'warn' => 'تحذير', 'info' => 'عادي'][$group['level']]) ?></span></td>
          <td class="col-sample">
            <a class="sample-link" href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($group['key']) ?><?= $full ? '&amp;full=1' : '&amp;mb=' . (int)$mb ?><?= e(src_qs()) ?>"
               title="<?= e(__('اعرض التفاصيل الكاملة')) ?>">
              <code class="sample mono ltr"><?= e($group['sample']) ?></code>
            </a>
          </td>
          <td class="col-date mono ltr"><?= $group['first'] ? e($group['first']) : '—' ?></td>
          <td class="col-date mono ltr"><?= $group['last'] ? e($group['last']) : '—' ?></td>
        </tr>
        <?php
    }
};
?>

<div class="page-head">
  <div>
    <h1 class="trunc-title">📊 <?= __('الأكثر تكرارًا') ?> — <span class="mono ltr"><?= e(basename($rel)) ?></span></h1>
    <p class="muted mono ltr"><?= e($rel) ?> · <?= bytes_html($size) ?> · <?= date('Y-m-d H:i', $mtime) ?></p>
  </div>
  <div class="head-actions">
    <?php if (empty($full) && $report['partial']): ?>
      <a class="btn btn-primary" href="?page=analyze&amp;file=<?= urlencode($rel) ?>&amp;full=1<?= e(src_qs()) ?>">🔬 <?= __('تحليل الملف بالكامل') ?></a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="?page=view&amp;file=<?= urlencode($rel) ?><?= e(src_qs()) ?>">📄 <?= __('عرض اللوج') ?></a>
    <a class="btn btn-ghost" href="?page=dashboard<?= e(src_qs()) ?>">← <?= __('رجوع') ?></a>
  </div>
</div>

<p class="muted small"><?= __('السطور المتشابهة بتتجمّع مع بعض حتى لو التاريخ أو الأرقام أو الـ IP جواها مختلفين.') ?></p>

<?php if (!empty($full)): ?>
  <div class="alert alert-success">
    ✅ <?= __('نتيجة تحليل الملف بالكامل') ?> — <?= __('اكتمل في') ?>
    <span class="mono ltr"><?= e(date('Y-m-d H:i', strtotime((string)($job['finished_at'] ?? 'now')))) ?></span>
    (<?= bytes_html((int)($job['total_bytes'] ?? 0)) ?>).
    <?= __('لو الملف كبر بعدها، أعد التحليل.') ?>
    <a class="chip-day" href="?page=analyze&amp;file=<?= urlencode($rel) ?>&amp;full=1&amp;restart=1<?= e(src_qs()) ?>">🔄 <?= __('إعادة التحليل') ?></a>
  </div>
<?php elseif ($report['partial']): ?>
  <div class="alert alert-warn">
    <?= __('الملف كبير') ?> (<?= bytes_html($size) ?>) — <?= __('تم تحليل') ?> <strong><?= __('آخر') ?> <?= bytes_html($report['scanned']) ?></strong> <?= __('فقط (الأحدث). كل الفحص بيتم على السيرفر — لا يتم تحميل الملف عندك.') ?>
    <span class="quick-days" style="display:inline-flex">
      <a class="chip-day" href="?page=analyze&amp;file=<?= urlencode($rel) ?>&amp;full=1<?= e(src_qs()) ?>">🔬 <?= __('تحليل الملف بالكامل (في الخلفية)') ?></a>
      <?= __('أو وسّع النافذة السريعة:') ?>
      <?php foreach ([50, 200, 500, 1000] as $preset): ?>
        <a class="chip-day <?= $mb === $preset ? 'is-active' : '' ?>"
           href="?page=analyze&amp;file=<?= urlencode($rel) ?>&amp;mb=<?= $preset ?><?= e(src_qs()) ?>"><?= $preset ?> MB</a>
      <?php endforeach; ?>
    </span>
  </div>
<?php endif; ?>

<div class="cards">
  <div class="card">
    <div class="card-label"><?= __('إجمالي السطور') ?></div>
    <div class="card-value"><?= number_format($report['total']) ?></div>
  </div>
  <div class="card">
    <div class="card-label"><?= __('رسائل مختلفة (بعد التجميع)') ?></div>
    <div class="card-value"><?= number_format($report['unique']) ?></div>
  </div>
  <div class="card">
    <div class="card-label"><?= __('أكثر رسالة تكرارًا') ?></div>
    <div class="card-value"><?= $report['top'] ? number_format($report['top'][0]['count']) . ' <small>' . __('مرة') . '</small>' : '—' ?></div>
  </div>
</div>

<?php if ($report['total'] === 0): ?>
  <div class="empty"><div class="empty-mark">📄</div><h2><?= __('الملف فارغ') ?></h2></div>
<?php else: ?>

  <section class="section">
    <h2 class="section-title">🔴 <?= __('أكثر الأخطاء تكرارًا') ?></h2>
    <?php if (!$report['errors']): ?>
      <div class="empty"><div class="empty-mark">✅</div><h2><?= __('لا توجد سطور أخطاء في الملف') ?></h2></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th class="col-num"><?= __('التكرار') ?></th><th class="col-num"><?= __('النسبة') ?></th><th><?= __('النوع') ?></th><th><?= __('نموذج السطر') ?></th><th class="col-date"><?= __('أول ظهور') ?></th><th class="col-date"><?= __('آخر ظهور') ?></th></tr>
          </thead>
          <tbody><?php $renderRows($report['errors']); ?></tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="section">
    <h2 class="section-title">📈 <?= __('أكثر السطور تكرارًا (كل الأنواع)') ?></h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th class="col-num"><?= __('التكرار') ?></th><th class="col-num"><?= __('النسبة') ?></th><th><?= __('النوع') ?></th><th><?= __('نموذج السطر') ?></th><th class="col-date"><?= __('أول ظهور') ?></th><th class="col-date"><?= __('آخر ظهور') ?></th></tr>
        </thead>
        <tbody><?php $renderRows($report['top']); ?></tbody>
      </table>
    </div>
  </section>

<?php endif; ?>

<?php layout_end(__('الأكثر تكرارًا') . ' · ' . basename($rel), $flashes); ?>
