<?php layout_start(); ?>

<div class="page-head">
  <div><h1>الإعدادات</h1><p class="muted">مسار اللوجات وطريقة قراءتها.</p></div>
  <a class="btn btn-ghost" href="?page=dashboard">← رجوع</a>
</div>

<?php if ($status['ok']): ?>
  <div class="alert alert-success">
    المسار الحالي صالح: <span class="mono ltr"><?= e($status['path']) ?></span>
    <?= $status['writable'] ? '· قابل للكتابة (الحذف يعمل)' : '· للقراءة فقط (الحذف لن يعمل)' ?>
  </div>
<?php else: ?>
  <div class="alert alert-error"><?= e($status['message']) ?></div>
<?php endif; ?>

<form class="panel" method="post" action="?page=settings">
  <?= csrf_field() ?>

  <label class="field">
    <span class="field-label">مسار مجلد اللوجات</span>
    <input type="text" name="log_dir" value="<?= e($config['log_dir']) ?>" required
           class="mono ltr" placeholder="/var/log/myapp" dir="ltr">
    <small class="muted">مسار مطلق على السيرفر. لازم يكون مقروءًا من مستخدم PHP
      (<span class="mono"><?= e(get_current_user()) ?></span>)، وقابلًا للكتابة لو عايز الحذف يعمل.</small>
  </label>

  <label class="field">
    <span class="field-label">أنماط الملفات</span>
    <input type="text" name="patterns" value="<?= e(implode(', ', (array)$config['patterns'])) ?>"
           class="mono ltr" placeholder="*.log, *.txt" dir="ltr">
    <small class="muted">مفصولة بفاصلة. مثال: <span class="mono ltr">*.log, *.txt, error-*</span> — اتركها فارغة لعرض كل الملفات.</small>
  </label>

  <label class="field field-check">
    <input type="checkbox" name="recursive" <?= !empty($config['recursive']) ? 'checked' : '' ?>>
    <span>البحث في المجلدات الفرعية</span>
  </label>

  <div class="field-row">
    <label class="field">
      <span class="field-label">عدد الأسطر الافتراضي عند العرض</span>
      <input type="number" name="tail_lines" min="10" max="20000" value="<?= (int)$config['tail_lines'] ?>" dir="ltr">
    </label>
    <label class="field">
      <span class="field-label">أقصى حجم للعرض (ميجابايت)</span>
      <input type="number" name="max_view_mb" min="1" max="500" value="<?= (int)$config['max_view_mb'] ?>" dir="ltr">
      <small class="muted">الملفات الأكبر تُعرض جزئيًا فقط.</small>
    </label>
  </div>

  <button class="btn btn-primary" type="submit">💾 حفظ الإعدادات</button>
</form>

<section class="section">
  <h2 class="section-title">معلومات النظام</h2>
  <div class="table-wrap">
    <table class="table table-kv">
      <tbody>
        <tr><td>مستخدم PHP</td><td class="mono ltr"><?= e(get_current_user()) ?></td></tr>
        <tr><td>إصدار PHP</td><td class="mono ltr"><?= e(PHP_VERSION) ?></td></tr>
        <tr><td>مجلد البيانات</td><td class="mono ltr"><?= e(DATA_PATH) ?>
          <?= is_writable(DATA_PATH) ? '<span class="tag">قابل للكتابة</span>' : '<span class="tag tag-warn">غير قابل للكتابة</span>' ?></td></tr>
        <tr><td>حجم سجل النشاط</td><td class="mono ltr"><?= is_file(AUDIT_FILE) ? bytes_html((int)filesize(AUDIT_FILE)) : '0 B' ?></td></tr>
        <tr><td>المنطقة الزمنية</td><td class="mono ltr"><?= e(date_default_timezone_get()) ?></td></tr>
      </tbody>
    </table>
  </div>
</section>

<?php layout_end('الإعدادات', $flashes); ?>
