<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1>اللوجات</h1>
    <?php if ($status['ok']): ?>
      <p class="muted mono ltr"><?= e($status['path']) ?></p>
    <?php endif; ?>
  </div>
  <?php if (is_admin()): ?>
    <a class="btn btn-ghost" href="?page=settings">⚙️ تغيير المسار</a>
  <?php endif; ?>
</div>

<?php if (!$status['ok']): ?>
  <div class="empty">
    <div class="empty-mark">📂</div>
    <h2>لا يوجد مسار لوجات صالح</h2>
    <p class="muted"><?= e($status['message']) ?></p>
    <?php if (is_admin()): ?>
      <a class="btn btn-primary" href="?page=settings">اذهب إلى الإعدادات</a>
    <?php else: ?>
      <p class="muted">اطلب من المدير ضبط المسار.</p>
    <?php endif; ?>
  </div>
<?php else: ?>

  <?php if (!empty($status['message'])): ?>
    <div class="alert alert-warn"><?= e($status['message']) ?></div>
  <?php endif; ?>

  <?php if (!empty($skipped)): ?>
    <div class="alert alert-warn">
      القائمة غير كاملة — تم تخطي <strong><?= count($skipped) ?></strong> مجلد لعدم وجود صلاحية قراءة:
      <span class="mono ltr"><?= e(implode(', ', array_slice($skipped, 0, 5))) ?></span>
      <?= count($skipped) > 5 ? '…' : '' ?>
    </div>
  <?php endif; ?>

  <div class="cards">
    <div class="card">
      <div class="card-label">عدد الملفات</div>
      <div class="card-value"><?= number_format($totals['count']) ?></div>
    </div>
    <div class="card">
      <div class="card-label">الحجم الكلي</div>
      <div class="card-value"><?= bytes_html($totals['bytes']) ?></div>
    </div>
    <div class="card">
      <div class="card-label">أقدم ملف</div>
      <div class="card-value">
        <?php $oldest = $files ? max(array_column($files, 'age_days')) : 0; ?>
        <?= $files ? number_format($oldest) . ' يوم' : '—' ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">أحدث تعديل</div>
      <div class="card-value">
        <?= $files ? e(ago(max(array_column($files, 'mtime')))) : '—' ?>
      </div>
    </div>
  </div>

  <form class="toolbar" method="get" action="">
    <input type="hidden" name="page" value="dashboard">
    <input class="input-search" type="search" name="q" value="<?= e($options['search']) ?>"
           placeholder="ابحث باسم الملف أو المجلد…">
    <label class="toolbar-field">
      <span>أقدم من</span>
      <input type="number" name="min_age" min="0" value="<?= e($options['min_age']) ?>" placeholder="—" style="width:5rem">
      <span>يوم</span>
    </label>
    <select name="sort">
      <option value="mtime" <?= $options['sort'] === 'mtime' ? 'selected' : '' ?>>تاريخ التعديل</option>
      <option value="size"  <?= $options['sort'] === 'size'  ? 'selected' : '' ?>>الحجم</option>
      <option value="name"  <?= $options['sort'] === 'name'  ? 'selected' : '' ?>>الاسم</option>
    </select>
    <select name="dir">
      <option value="desc" <?= $options['dir'] === 'desc' ? 'selected' : '' ?>>تنازلي</option>
      <option value="asc"  <?= $options['dir'] === 'asc'  ? 'selected' : '' ?>>تصاعدي</option>
    </select>
    <button class="btn btn-ghost" type="submit">تطبيق</button>
    <?php if ($options['search'] !== '' || $options['min_age'] !== ''): ?>
      <a class="btn btn-ghost" href="?page=dashboard">مسح الفلتر</a>
    <?php endif; ?>
  </form>

  <?php if (!$files): ?>
    <div class="empty">
      <div class="empty-mark">🔍</div>
      <h2>لا توجد ملفات مطابقة</h2>
      <p class="muted">جرّب تعديل الفلتر، أو راجع أنماط الملفات في الإعدادات.</p>
    </div>
  <?php else: ?>

    <form method="post" action="?page=delete" id="bulk-form"
          data-confirm="سيتم حذف الملفات المختارة نهائيًا. هل أنت متأكد؟">
      <?= csrf_field() ?>

      <?php if (is_admin()): ?>
        <div class="bulkbar" id="bulkbar" hidden>
          <span><strong id="bulk-count">0</strong> ملف مختار (<span id="bulk-size" class="ltr">0</span>)</span>
          <button class="btn btn-danger btn-sm" type="submit">🗑️ حذف المختار</button>
          <button class="btn btn-ghost btn-sm" type="button" id="bulk-clear">إلغاء التحديد</button>
        </div>
      <?php endif; ?>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <?php if (is_admin()): ?>
                <th class="col-check"><input type="checkbox" id="check-all" title="تحديد الكل"></th>
              <?php endif; ?>
              <th>الملف</th>
              <th class="col-num">الحجم</th>
              <th class="col-date">آخر تعديل</th>
              <th class="col-num">العمر</th>
              <th class="col-actions">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file): ?>
              <tr>
                <?php if (is_admin()): ?>
                  <td class="col-check">
                    <input type="checkbox" name="files[]" class="row-check"
                           value="<?= e($file['rel']) ?>" data-size="<?= (int)$file['size'] ?>"
                           <?= $file['writable'] ? '' : 'disabled title="لا توجد صلاحية حذف"' ?>>
                  </td>
                <?php endif; ?>
                <td class="col-file">
                  <a class="file-link mono ltr" href="?page=view&amp;file=<?= urlencode($file['rel']) ?>"><?= e($file['rel']) ?></a>
                </td>
                <td class="col-num mono"><?= bytes_html($file['size']) ?></td>
                <td class="col-date">
                  <span class="mono ltr"><?= date('Y-m-d H:i', $file['mtime']) ?></span>
                  <small class="muted">منذ <?= e(ago($file['mtime'])) ?></small>
                </td>
                <td class="col-num">
                  <span class="pill <?= age_class($file['age_days']) ?>"><?= (int)$file['age_days'] ?> يوم</span>
                </td>
                <td class="col-actions">
                  <a class="btn btn-ghost btn-sm" href="?page=view&amp;file=<?= urlencode($file['rel']) ?>">عرض</a>
                  <a class="btn btn-ghost btn-sm" href="?page=download&amp;file=<?= urlencode($file['rel']) ?>">تحميل</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php if ($stats): ?>
  <section class="section">
    <div class="section-head">
      <h2>من حذف ماذا</h2>
      <a class="btn btn-ghost btn-sm" href="?page=audit&amp;action=cleanup">كل السجل ←</a>
    </div>
    <div class="cards">
      <?php foreach (array_slice($stats, 0, 4, true) as $username => $stat): ?>
        <a class="card card-link" href="?page=audit&amp;user=<?= urlencode($username) ?>">
          <div class="card-label"><?= e($username) ?></div>
          <div class="card-value"><?= number_format($stat['files']) ?> <small>ملف</small></div>
          <div class="card-meta">
            <?= bytes_html($stat['bytes']) ?> · <?= number_format($stat['events']) ?> عملية
            <?php if ($stat['last']): ?>
              · آخرها منذ <?= e(ago(strtotime($stat['last']))) ?>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($recent): ?>
  <section class="section">
    <div class="section-head">
      <h2>آخر النشاطات</h2>
      <a class="btn btn-ghost btn-sm" href="?page=audit">عرض الكل ←</a>
    </div>
    <ul class="timeline">
      <?php foreach ($recent as $row): ?>
        <li>
          <span class="mono muted ltr"><?= e(date('m-d H:i', strtotime((string)$row['ts']))) ?></span>
          <strong><?= e($row['user']) ?></strong>
          <span class="tag"><?= e(AUDIT_ACTIONS[$row['action']] ?? $row['action']) ?></span>
          <?php if (!empty($row['details']['count'])): ?>
            <span class="muted"><?= (int)$row['details']['count'] ?> ملف</span>
          <?php elseif (!empty($row['details']['file'])): ?>
            <span class="muted mono ltr trunc"><?= e($row['details']['file']) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php layout_end('اللوجات', $flashes); ?>
