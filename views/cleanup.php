<?php
/** @var array $status @var array $skipped @var string $before @var string $target @var array $files @var array $preview @var array $sources @var ?array $active */
$totalRemove = array_sum(array_map(fn($f) => $f['scan']['remove'], $preview));
$totalBytes  = array_sum(array_map(fn($f) => $f['scan']['bytes'], $preview));
layout_start();
?>

<div class="page-head">
  <div>
    <h1><?= __('تنظيف اللوجات — حذف السطور القديمة') ?><?= $active ? ' — ' . e($active['name']) : '' ?></h1>
    <p class="muted mono ltr"><?= e($status['path']) ?></p>
  </div>
  <a class="btn btn-ghost" href="?page=dashboard<?= e(src_qs()) ?>">← <?= __('رجوع') ?></a>
</div>

<?php if (count($sources) > 1): ?>
  <nav class="src-tabs">
    <?php foreach ($sources as $source): ?>
      <a class="src-tab <?= $active && $active['name'] === $source['name'] ? 'is-active' : '' ?>"
         href="?page=cleanup&amp;src=<?= urlencode($source['name']) ?>">📁 <?= e($source['name']) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<form class="panel" method="get" action="">
  <input type="hidden" name="page" value="cleanup">
  <?php if ($active): ?><input type="hidden" name="src" value="<?= e($active['name']) ?>"><?php endif; ?>
  <h2><?= __('احذف كل السطور الأقدم من تاريخ') ?></h2>
  <p class="muted">
    <?= __('يتم حذف السطور التي تاريخها <strong>قبل</strong> اليوم المحدد (اليوم المحدد نفسه يبقى). السطور بدون تاريخ — مثل تكملة Stack Trace — تتبع آخر سطر مؤرَّخ قبلها.') ?>
  </p>

  <div class="days-row">
    <input class="days-input date-input" type="date" name="before" required dir="ltr"
           value="<?= e($before) ?>" max="<?= date('Y-m-d') ?>">
    <select class="days-input file-select" name="file" dir="ltr">
      <option value="*" <?= $target === '*' ? 'selected' : '' ?>><?= __('كل الملفات') ?> (<?= count($files) ?>)</option>
      <?php foreach ($files as $file): ?>
        <option value="<?= e($file['rel']) ?>" <?= $target === $file['rel'] ? 'selected' : '' ?>><?= e($file['rel']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">🔍 <?= __('معاينة') ?></button>
  </div>

  <div class="quick-days">
    <span class="muted"><?= __('اختصارات — أقدم من:') ?></span>
    <?php foreach (['أسبوع' => 7, 'شهر' => 30, '3 شهور' => 90, '6 شهور' => 180, 'سنة' => 365] as $label => $daysAgo): ?>
      <?php $quick = date('Y-m-d', time() - $daysAgo * 86400); ?>
      <a class="chip-day <?= $before === $quick ? 'is-active' : '' ?>"
         href="?page=cleanup&amp;before=<?= $quick ?>&amp;file=<?= e(urlencode($target ?: '*')) ?><?= e(src_qs()) ?>"><?= __($label) ?></a>
    <?php endforeach; ?>
  </div>
</form>

<?php if (!empty($skipped)): ?>
  <div class="alert alert-warn">
    <?= __('تم تخطي') ?> <strong><?= count($skipped) ?></strong> <?= __('مجلد لعدم وجود صلاحية قراءة:') ?>
    <span class="mono ltr"><?= e(implode(', ', array_slice($skipped, 0, 5))) ?></span>
    <?= count($skipped) > 5 ? '…' : '' ?>
  </div>
<?php endif; ?>

<?php if ($preview): ?>
  <?php if ($totalRemove === 0): ?>
    <div class="empty">
      <div class="empty-mark">✅</div>
      <h2><?= __('لا توجد سطور أقدم من') ?> <span class="ltr"><?= e(date('d/m/Y', strtotime($before))) ?></span></h2>
      <p class="muted"><?= count($preview) === 1 ? __('لا شيء للحذف في هذا الملف.') : __('لا شيء للحذف في الملفات المحددة.') ?></p>
    </div>
  <?php else: ?>

    <div class="alert alert-warn">
      <strong><?= __('تحذير:') ?></strong> <?= __('الحذف نهائي ولا يمكن التراجع عنه. سيتم حذف') ?>
      <strong><?= number_format($totalRemove) ?></strong> <?= __('سطر') ?>
      (<?= bytes_html($totalBytes) ?>) <?= __('الأقدم من') ?>
      <strong class="ltr"><?= e(date('d/m/Y', strtotime($before))) ?></strong>.
    </div>

    <div class="table-wrap table-preview">
      <table class="table">
        <thead>
          <tr>
            <th><?= __('الملف') ?></th>
            <th class="col-num"><?= __('إجمالي السطور') ?></th>
            <th class="col-num"><?= __('سطور ستُحذف') ?></th>
            <th class="col-num"><?= __('حجم سيتوفر') ?></th>
            <th class="col-date"><?= __('أقدم تاريخ') ?></th>
            <th class="col-date"><?= __('أحدث تاريخ') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $file): $scan = $file['scan']; ?>
            <tr class="<?= $scan['remove'] === 0 ? 'row-blocked' : '' ?><?= $file['writable'] ? '' : ' row-blocked' ?>">
              <td class="mono ltr"><?= e($file['rel']) ?>
                <?php if (!$file['writable']): ?><span class="tag tag-warn"><?= __('لا توجد صلاحية كتابة') ?></span><?php endif; ?>
                <?php if ($scan['lines'] > 0 && $scan['first'] === null): ?><span class="tag tag-warn"><?= __('لا توجد تواريخ متعرَّف عليها') ?></span><?php endif; ?>
              </td>
              <td class="col-num mono"><?= number_format($scan['lines']) ?></td>
              <td class="col-num mono"><strong><?= number_format($scan['remove']) ?></strong></td>
              <td class="col-num mono"><?= bytes_html($scan['bytes']) ?></td>
              <td class="col-date mono ltr"><?= $scan['first'] ? e($scan['first']) : '—' ?></td>
              <td class="col-date mono ltr"><?= $scan['last'] ? e($scan['last']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form class="panel panel-danger" method="post" action="?page=cleanup"
          data-confirm="<?= e(__('آخر تأكيد: سيتم حذف السطور نهائيًا.')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="before" value="<?= e($before) ?>">
      <input type="hidden" name="file" value="<?= e($target) ?>">
      <input type="hidden" name="confirm" value="yes">
      <?php if ($active): ?><input type="hidden" name="src" value="<?= e($active['name']) ?>"><?php endif; ?>

      <h2><?= __('تأكيد الحذف') ?></h2>
      <p><?= __('أعد إدخال التاريخ') ?> <strong class="ltr"><?= e(date('d/m/Y', strtotime($before))) ?></strong> <?= __('للتأكيد:') ?></p>
      <div class="days-row">
        <input class="days-input date-input" type="date" name="before_confirm" required dir="ltr">
        <button class="btn btn-danger" type="submit">🗑️ <?= __('حذف') ?> <?= number_format($totalRemove) ?> <?= __('سطر نهائيًا') ?></button>
      </div>
      <p class="muted small"><?= __('سيُسجَّل هذا الإجراء في سجل النشاط باسمك') ?> (<?= e(current_user()['username']) ?>).</p>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php layout_end('تنظيف اللوجات', $flashes); ?>
