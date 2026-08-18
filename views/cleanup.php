<?php
/** @var array $status @var array $skipped @var string $before @var string $target @var array $files @var array $preview @var array $sources @var ?array $active @var array $pending @var array $myRequests */
$totalRemove = array_sum(array_map(fn($f) => $f['scan']['remove'], $preview));
$totalBytes  = array_sum(array_map(fn($f) => $f['scan']['bytes'], $preview));
$isAdmin     = is_admin();
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

<?php if ($isAdmin && $pending): ?>
  <section class="panel panel-danger">
    <h2>📨 <?= __('طلبات تنظيف في انتظار موافقتك') ?> (<?= count($pending) ?>)</h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= __('المستخدم') ?></th>
            <th><?= __('المسار') ?></th>
            <th><?= __('الملف') ?></th>
            <th class="col-date"><?= __('أقدم من') ?></th>
            <th class="col-num"><?= __('سطور ستُحذف') ?></th>
            <th class="col-num"><?= __('حجم سيتوفر') ?></th>
            <th class="col-date"><?= __('وقت الطلب') ?></th>
            <th class="col-actions"><?= __('إجراءات') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $req): ?>
            <tr>
              <td><strong><?= e($req['user']) ?></strong></td>
              <td>📁 <?= e($req['src']) ?></td>
              <td class="mono ltr"><?= $req['target'] === '*' ? '<strong>' . __('كل الملفات') . '</strong>' : e($req['target']) ?></td>
              <td class="col-date mono ltr"><?= e($req['before']) ?></td>
              <td class="col-num mono"><strong><?= number_format((int)($req['preview']['lines'] ?? 0)) ?></strong></td>
              <td class="col-num mono"><?= bytes_html((int)($req['preview']['bytes'] ?? 0)) ?></td>
              <td class="col-date mono ltr"><?= e(date('m-d H:i', strtotime((string)$req['created_at']))) ?></td>
              <td class="col-actions">
                <form method="post" action="?page=cleanup" class="inline"
                      data-confirm="<?= e(sprintf(
                          __('الموافقة على طلب %s: سيتم حذف حوالي %s سطر (الأقدم من %s) نهائيًا. لا يمكن التراجع.'),
                          $req['user'],
                          number_format((int)($req['preview']['lines'] ?? 0)),
                          date('d/m/Y', strtotime((string)$req['before']))
                      )) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="request_action" value="approve">
                  <input type="hidden" name="request_id" value="<?= e($req['id']) ?>">
                  <button class="btn btn-danger btn-sm" type="submit">✅ <?= __('موافقة وتنفيذ') ?></button>
                </form>
                <form method="post" action="?page=cleanup" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="request_action" value="reject">
                  <input type="hidden" name="request_id" value="<?= e($req['id']) ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">✖ <?= __('رفض') ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small"><?= __('التنفيذ يتم بمعايير الطلب نفسها وقت الموافقة — الأرقام قد تزيد لو الملف كبر بعد الطلب.') ?></p>
  </section>
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

  <div class="quick-days" id="quick-days">
    <span class="muted"><?= __('اختصارات — أقدم من:') ?></span>
    <?php foreach (['أسبوع' => 7, 'شهر واحد' => 30, '3 شهور' => 90, '6 شهور' => 180, 'سنة واحدة' => 365] as $label => $daysAgo): ?>
      <?php $quick = date('Y-m-d', time() - $daysAgo * 86400); ?>
      <button type="button" class="chip-day <?= $before === $quick ? 'is-active' : '' ?>"
              data-date="<?= $quick ?>"><?= __($label) ?></button>
    <?php endforeach; ?>
    <span class="muted small"><?= __('الاختصار يملأ التاريخ فقط — المعاينة تبدأ بزرار «معاينة».') ?></span>
  </div>
</form>

<script>
// Shortcut chips only fill the date field; nothing runs until "Preview".
document.querySelectorAll('#quick-days [data-date]').forEach(function (chip) {
  chip.addEventListener('click', function () {
    document.querySelector('input[name=before]').value = chip.dataset.date;
    document.querySelectorAll('#quick-days [data-date]').forEach(function (c) { c.classList.remove('is-active'); });
    chip.classList.add('is-active');
  });
});
</script>

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

    <?php
      $filesCount  = count(array_filter($preview, fn($f) => $f['scan']['remove'] > 0));
      $confirmMsg  = $isAdmin
          ? sprintf(__('سيتم حذف %s سطر نهائيًا من %d ملف (الأقدم من %s). لا يمكن التراجع عن هذه العملية.'),
                    number_format($totalRemove), $filesCount, date('d/m/Y', strtotime($before)))
          : sprintf(__('سيتم إرسال طلب للمدير لحذف %s سطر من %d ملف (الأقدم من %s). لن يُحذف شيء قبل موافقته.'),
                    number_format($totalRemove), $filesCount, date('d/m/Y', strtotime($before)));
    ?>
    <form class="panel panel-danger" method="post" action="?page=cleanup"
          data-confirm="<?= e($confirmMsg) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="before" value="<?= e($before) ?>">
      <input type="hidden" name="file" value="<?= e($target) ?>">
      <input type="hidden" name="confirm" value="yes">
      <?php if ($active): ?><input type="hidden" name="src" value="<?= e($active['name']) ?>"><?php endif; ?>

      <h2><?= $isAdmin ? __('تأكيد الحذف') : __('إرسال طلب التنظيف للمدير') ?></h2>
      <p><?= __('أعد إدخال التاريخ') ?> <strong class="ltr"><?= e(date('d/m/Y', strtotime($before))) ?></strong> <?= __('للتأكيد:') ?></p>
      <div class="days-row">
        <input class="days-input date-input" type="date" name="before_confirm" required dir="ltr">
        <?php if ($isAdmin): ?>
          <button class="btn btn-danger" type="submit">🗑️ <?= __('حذف') ?> <?= number_format($totalRemove) ?> <?= __('سطر نهائيًا') ?></button>
        <?php else: ?>
          <button class="btn btn-primary" type="submit">📨 <?= __('إرسال الطلب للمدير') ?></button>
        <?php endif; ?>
      </div>
      <p class="muted small">
        <?= $isAdmin
            ? __('سيُسجَّل هذا الإجراء في سجل النشاط باسمك') . ' (' . e(current_user()['username']) . ').'
            : __('لن يُحذف أي سطر إلا بعد موافقة المدير — سيصله طلبك بالتفاصيل دي.') ?>
      </p>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$isAdmin && $myRequests): ?>
  <section class="section">
    <h2 class="section-title">📨 <?= __('طلباتي') ?></h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="col-date"><?= __('وقت الطلب') ?></th>
            <th><?= __('المسار') ?></th>
            <th><?= __('الملف') ?></th>
            <th class="col-date"><?= __('أقدم من') ?></th>
            <th class="col-num"><?= __('سطور') ?></th>
            <th><?= __('الحالة') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myRequests as $req): ?>
            <tr>
              <td class="col-date mono ltr"><?= e(date('m-d H:i', strtotime((string)$req['created_at']))) ?></td>
              <td>📁 <?= e($req['src']) ?></td>
              <td class="mono ltr"><?= $req['target'] === '*' ? __('كل الملفات') : e($req['target']) ?></td>
              <td class="col-date mono ltr"><?= e($req['before']) ?></td>
              <td class="col-num mono"><?= number_format((int)($req['preview']['lines'] ?? 0)) ?></td>
              <td>
                <?php if ($req['status'] === 'pending'): ?>
                  <span class="tag tag-warn">⏳ <?= __('في انتظار الموافقة') ?></span>
                <?php elseif ($req['status'] === 'approved'): ?>
                  <span class="tag tag-login">✅ <?= __('تمت الموافقة') ?></span>
                  <?php if (!empty($req['result'])): ?>
                    <span class="muted small"><?= number_format((int)$req['result']['lines']) ?> <?= __('سطر') ?> (<?= bytes_html((int)$req['result']['bytes']) ?>)</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="tag tag-error">✖ <?= __('مرفوض') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php layout_end('تنظيف اللوجات', $flashes); ?>
