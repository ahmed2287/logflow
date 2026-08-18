<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1>تنظيف اللوجات القديمة</h1>
    <p class="muted mono ltr"><?= e($status['path']) ?></p>
  </div>
  <a class="btn btn-ghost" href="?page=dashboard">← رجوع</a>
</div>

<form class="panel" method="get" action="">
  <input type="hidden" name="page" value="cleanup">
  <h2>اختر عدد الأيام</h2>
  <p class="muted">سيتم عرض الملفات التي لم تُعدَّل خلال آخر <strong>N</strong> يوم — تراجعها ثم تؤكد الحذف.</p>

  <div class="days-row">
    <input class="days-input" type="number" name="days" min="0" max="3650" required
           value="<?= $days !== null ? (int)$days : '' ?>" placeholder="10" autofocus>
    <span class="days-unit">يوم</span>
    <button class="btn btn-primary" type="submit">🔍 معاينة</button>
  </div>

  <div class="quick-days">
    <span class="muted">اختصارات:</span>
    <?php foreach ([7, 10, 14, 30, 60, 90, 180, 365] as $preset): ?>
      <a class="chip-day <?= $days === $preset ? 'is-active' : '' ?>" href="?page=cleanup&amp;days=<?= $preset ?>"><?= $preset ?></a>
    <?php endforeach; ?>
  </div>
  <p class="muted small">إجمالي الملفات في المجلد حاليًا: <strong><?= number_format($allCount) ?></strong></p>
</form>

<?php if (!empty($skipped)): ?>
  <div class="alert alert-warn">
    تم تخطي <strong><?= count($skipped) ?></strong> مجلد لعدم وجود صلاحية قراءة، ولن تُحذف الملفات داخله:
    <span class="mono ltr"><?= e(implode(', ', array_slice($skipped, 0, 5))) ?></span>
    <?= count($skipped) > 5 ? '…' : '' ?>
  </div>
<?php endif; ?>

<?php if ($days !== null): ?>
  <?php if (!$candidates): ?>
    <div class="empty">
      <div class="empty-mark">✅</div>
      <h2>لا توجد ملفات أقدم من <?= (int)$days ?> يوم</h2>
      <p class="muted">لا شيء للحذف — المجلد نظيف بالنسبة لهذه المدة.</p>
    </div>
  <?php else: ?>

    <div class="alert alert-warn">
      <strong>تحذير:</strong> الحذف نهائي ولا يمكن التراجع عنه. سيتم حذف
      <strong><?= number_format($totals['count']) ?></strong> ملف بحجم
      <strong><?= bytes_html($totals['bytes']) ?></strong>.
      <?php if ($days === 0): ?>
        <br>عدد الأيام = 0 يعني <strong>حذف كل الملفات</strong> في المجلد.
      <?php endif; ?>
    </div>

    <div class="table-wrap table-preview">
      <table class="table">
        <thead>
          <tr>
            <th>الملف</th>
            <th class="col-num">الحجم</th>
            <th class="col-date">آخر تعديل</th>
            <th class="col-num">العمر</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($candidates as $file): ?>
            <tr class="<?= $file['writable'] ? '' : 'row-blocked' ?>">
              <td class="mono ltr"><?= e($file['rel']) ?>
                <?php if (!$file['writable']): ?><span class="tag tag-warn">غير قابل للحذف</span><?php endif; ?>
              </td>
              <td class="col-num mono"><?= bytes_html($file['size']) ?></td>
              <td class="col-date mono ltr"><?= date('Y-m-d H:i', $file['mtime']) ?></td>
              <td class="col-num"><span class="pill <?= age_class($file['age_days']) ?>"><?= (int)$file['age_days'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form class="panel panel-danger" method="post" action="?page=cleanup"
          data-confirm="آخر تأكيد: سيتم حذف <?= (int)$totals['count'] ?> ملف نهائيًا.">
      <?= csrf_field() ?>
      <input type="hidden" name="days" value="<?= (int)$days ?>">
      <input type="hidden" name="confirm" value="yes">

      <h2>تأكيد الحذف</h2>
      <p>اكتب عدد الأيام <strong><?= (int)$days ?></strong> في الخانة للتأكيد:</p>
      <div class="days-row">
        <input class="days-input" type="number" name="days_confirm" required placeholder="<?= (int)$days ?>" dir="ltr">
        <button class="btn btn-danger" type="submit">🗑️ حذف <?= number_format($totals['count']) ?> ملف نهائيًا</button>
      </div>
      <p class="muted small">سيُسجَّل هذا الإجراء في سجل النشاط باسمك (<?= e(current_user()['username']) ?>).</p>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php layout_end('تنظيف اللوجات', $flashes); ?>
