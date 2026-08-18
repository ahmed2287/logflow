<?php
$sources = log_sources();
layout_start();
?>

<div class="page-head">
  <div><h1><?= __('الإعدادات') ?></h1><p class="muted"><?= __('مسارات اللوجات وطريقة قراءتها.') ?></p></div>
  <a class="btn btn-ghost" href="?page=dashboard">← <?= __('رجوع') ?></a>
</div>

<form class="panel" method="post" action="?page=settings">
  <?= csrf_field() ?>

  <div class="field">
    <span class="field-label"><?= __('مسارات اللوجات') ?></span>
    <small class="muted"><?= __('لكل مسار اسم يظهر في اللوحة. المسار لازم يكون مطلقًا ومقروءًا من مستخدم PHP') ?>
      (<span class="mono"><?= e(get_current_user()) ?></span>)<?= __('، وقابلًا للكتابة لو عايز الحذف يعمل.') ?>
      <?= __('علّم ✕ على أي مسار لحذفه من القائمة (لا يحذف ملفاته).') ?></small>

    <div id="sources-rows">
      <?php foreach ($sources as $i => $source): ?>
        <?php
          $real = realpath($source['path']);
          $ok   = $real !== false && is_dir($real) && is_readable($real);
        ?>
        <div class="source-row">
          <input type="text" name="sources[<?= $i ?>][name]" value="<?= e($source['name']) ?>"
                 class="source-name" placeholder="<?= e(__('الاسم')) ?>" maxlength="40">
          <input type="text" name="sources[<?= $i ?>][path]" value="<?= e($source['path']) ?>"
                 class="mono ltr source-path" placeholder="/var/log/myapp" dir="ltr">
          <span class="tag <?= $ok ? '' : 'tag-warn' ?>"><?= $ok ? (is_writable((string)$real) ? __('صالح') : __('قراءة فقط')) : __('غير صالح') ?></span>
          <label class="source-remove" title="<?= e(__('حذف من القائمة')) ?>">
            <input type="checkbox" name="sources[<?= $i ?>][remove]" value="1"> ✕
          </label>
        </div>
      <?php endforeach; ?>
      <div class="source-row">
        <input type="text" name="sources[new0][name]" class="source-name" placeholder="<?= e(__('اسم مسار جديد')) ?>" maxlength="40">
        <input type="text" name="sources[new0][path]" class="mono ltr source-path" placeholder="/var/log/myapp" dir="ltr">
      </div>
    </div>
    <button class="btn btn-ghost btn-sm" type="button" id="add-source">＋ <?= __('مسار آخر') ?></button>
  </div>

  <label class="field">
    <span class="field-label"><?= __('أنماط الملفات') ?></span>
    <input type="text" name="patterns" value="<?= e(implode(', ', (array)$config['patterns'])) ?>"
           class="mono ltr" placeholder="*.log, *.txt" dir="ltr">
    <small class="muted"><?= __('مفصولة بفاصلة. مثال:') ?> <span class="mono ltr">*.log, *.txt, error-*</span> — <?= __('اتركها فارغة لعرض كل الملفات.') ?></small>
  </label>

  <label class="field field-check">
    <input type="checkbox" name="recursive" <?= !empty($config['recursive']) ? 'checked' : '' ?>>
    <span><?= __('البحث في المجلدات الفرعية') ?></span>
  </label>

  <div class="field-row">
    <label class="field">
      <span class="field-label"><?= __('عدد الأسطر الافتراضي عند العرض') ?></span>
      <input type="number" name="tail_lines" min="10" max="20000" value="<?= (int)$config['tail_lines'] ?>" dir="ltr">
    </label>
    <label class="field">
      <span class="field-label"><?= __('أقصى حجم للعرض (ميجابايت)') ?></span>
      <input type="number" name="max_view_mb" min="1" max="500" value="<?= (int)$config['max_view_mb'] ?>" dir="ltr">
      <small class="muted"><?= __('الملفات الأكبر تُعرض جزئيًا فقط.') ?></small>
    </label>
  </div>

  <button class="btn btn-primary" type="submit">💾 <?= __('حفظ الإعدادات') ?></button>
</form>

<section class="section">
  <h2 class="section-title"><?= __('معلومات النظام') ?></h2>
  <div class="table-wrap">
    <table class="table table-kv">
      <tbody>
        <tr><td><?= __('مستخدم PHP') ?></td><td class="mono ltr"><?= e(get_current_user()) ?></td></tr>
        <tr><td><?= __('إصدار PHP') ?></td><td class="mono ltr"><?= e(PHP_VERSION) ?></td></tr>
        <tr><td><?= __('مجلد البيانات') ?></td><td class="mono ltr"><?= e(DATA_PATH) ?>
          <?= is_writable(DATA_PATH) ? '<span class="tag">' . __('قابل للكتابة') . '</span>' : '<span class="tag tag-warn">' . __('غير قابل للكتابة') . '</span>' ?></td></tr>
        <tr><td><?= __('حجم سجل النشاط') ?></td><td class="mono ltr"><?= is_file(AUDIT_FILE) ? bytes_html((int)filesize(AUDIT_FILE)) : '0 B' ?></td></tr>
        <tr><td><?= __('المنطقة الزمنية') ?></td><td class="mono ltr"><?= e(date_default_timezone_get()) ?></td></tr>
      </tbody>
    </table>
  </div>
</section>

<script>
// "＋ مسار آخر": clone the blank row with a fresh index — no libraries.
document.getElementById('add-source').addEventListener('click', function () {
  var rows  = document.getElementById('sources-rows');
  var blank = rows.lastElementChild;
  var clone = blank.cloneNode(true);
  var n     = rows.querySelectorAll('.source-row').length;
  clone.querySelectorAll('input').forEach(function (input) {
    input.value = '';
    input.name  = input.name.replace(/\[new\d+\]/, '[new' + n + ']');
  });
  rows.appendChild(clone);
  clone.querySelector('.source-name').focus();
});
</script>

<?php layout_end('الإعدادات', $flashes); ?>
