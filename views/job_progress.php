<?php
/** @var string $rel @var array $job @var bool $stale @var string $selfUrl @var string $backUrl */
$total   = max(1, (int)($job['total_bytes'] ?? 1));
$done    = min($total, (int)($job['bytes_done'] ?? 0));
$percent = (int)floor($done * 100 / $total);
$failed  = ($job['status'] ?? '') === 'failed';
$elapsed = max(0, time() - (int)strtotime((string)($job['started_at'] ?? 'now')));
layout_start();
?>

<div class="page-head">
  <div>
    <h1>🔬 <?= __('تحليل الملف بالكامل') ?></h1>
    <p class="muted mono ltr"><?= e($rel) ?> · <?= bytes_html($total) ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= e($backUrl) ?>">← <?= __('رجوع') ?></a>
</div>

<?php if ($failed): ?>
  <div class="alert alert-error">
    <?= __('فشل التحليل:') ?> <span class="mono ltr"><?= e((string)($job['error'] ?? '?')) ?></span>
  </div>
  <a class="btn btn-primary" href="<?= e($selfUrl) ?>&amp;restart=1">🔄 <?= __('إعادة المحاولة') ?></a>
<?php elseif ($stale): ?>
  <div class="alert alert-warn"><?= __('يبدو أن التحليل توقف (لا يوجد تقدم منذ أكثر من دقيقتين).') ?></div>
  <a class="btn btn-primary" href="<?= e($selfUrl) ?>&amp;restart=1">🔄 <?= __('إعادة المحاولة') ?></a>
<?php else: ?>
  <div class="panel">
    <h2><?= __('التحليل شغال على السيرفر…') ?></h2>
    <p class="muted"><?= __('الملف بيتفحص بالكامل على السيرفر في الخلفية — تقدر تسيب الصفحة وترجع لها، النتيجة بتتحفظ.') ?></p>
    <div class="job-bar"><span style="width:<?= $percent ?>%"></span></div>
    <p class="mono ltr">
      <?= $percent ?>% — <?= bytes_html($done) ?> / <?= bytes_html($total) ?>
      · <?= (int)floor($elapsed / 60) ?>m <?= $elapsed % 60 ?>s
    </p>
  </div>
  <script>setTimeout(function () { location.reload(); }, 3000);</script>
<?php endif; ?>

<?php layout_end('تحليل الملف بالكامل', $flashes); ?>
