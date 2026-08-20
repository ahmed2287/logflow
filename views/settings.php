<?php
$sources = log_sources();
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('الإعدادات') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= __('مسارات اللوجات وطريقة قراءتها.') ?></p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('رجوع') ?></a>
</div>

<form class="saas-card" method="post" action="?page=settings" style="margin-bottom: 2rem;">
  <?= csrf_field() ?>

  <div style="margin-bottom: 1.5rem;">
    <h3 style="margin-top: 0; font-size: 1.05rem; font-weight: 700;"><?= __('مسارات اللوجات') ?></h3>
    <p class="muted small" style="margin-bottom: 1rem;">
      <?= __('لكل مسار اسم يظهر في اللوحة. المسار لازم يكون مطلقًا ومقروءًا من مستخدم PHP') ?>
      (<span class="mono"><?= e(get_current_user()) ?></span>)<?= __('، وقابلًا للكتابة لو عايز الحذف يعمل.') ?>
      <?= __('علّم ✕ على أي مسار لحذفه من القائمة.') ?>
    </p>

    <div id="sources-rows" style="display: flex; flex-direction: column; gap: 0.75rem;">
      <?php foreach ($sources as $i => $source): ?>
        <?php
          $stype = $source['type'] ?? 'dir';
          $real  = realpath($source['path']);
          $ok    = ($stype === 'docker') ? ($real !== false || file_exists($source['path'])) : ($real !== false && is_dir($real) && is_readable($real));
        ?>
        <div class="source-row" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <input type="text" name="sources[<?= $i ?>][name]" value="<?= e($source['name']) ?>"
                 class="source-name" placeholder="<?= e(__('الاسم')) ?>" maxlength="40" style="width: 160px;">
          <select name="sources[<?= $i ?>][type]" class="source-type" style="width: 155px; font-size: 0.84rem;">
            <option value="dir" <?= $stype === 'dir' ? 'selected' : '' ?>>📂 <?= __('مجلد لوجات') ?></option>
            <option value="docker" <?= $stype === 'docker' ? 'selected' : '' ?>>🐳 <?= __('دوكر كومبوز') ?></option>
            <option value="cmd" <?= ($stype === 'cmd' || $stype === 'command') ? 'selected' : '' ?>>⚡ <?= __('أمر سيستم / كوماتد') ?></option>
          </select>
          <input type="text" name="sources[<?= $i ?>][path]" value="<?= e($source['path']) ?>"
                 class="mono ltr source-path" placeholder="/var/log/myapp | /app/docker-compose.yml | journalctl -n 300 --no-pager" dir="ltr" style="flex: 1; min-width: 220px;">
          <span class="tag <?= ($stype === 'cmd' || $stype === 'command') ? 'tag-info' : ($ok ? 'tag-success' : 'tag-warn') ?>"><?= ($stype === 'cmd' || $stype === 'command') ? '⚡ Command Stream' : ($ok ? ($stype === 'docker' ? '🐳 Docker' : (is_writable((string)$real) ? __('صالح') : __('قراءة فقط'))) : __('غير صالح')) ?></span>
          <label style="color: var(--danger); cursor: pointer; display: inline-flex; align-items: center; gap: 0.2rem;" title="<?= e(__('حذف من القائمة')) ?>">
            <input type="checkbox" name="sources[<?= $i ?>][remove]" value="1"> ✕
          </label>
        </div>
      <?php endforeach; ?>
      <div class="source-row" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <input type="text" name="sources[new0][name]" class="source-name" placeholder="<?= e(__('اسم مسار جديد')) ?>" maxlength="40" style="width: 160px;">
        <select name="sources[new0][type]" class="source-type" style="width: 155px; font-size: 0.84rem;">
          <option value="dir">📂 <?= __('مجلد لوجات') ?></option>
          <option value="docker">🐳 <?= __('دوكر كومبوز') ?></option>
          <option value="cmd">⚡ <?= __('أمر سيستم / كوماتد') ?></option>
        </select>
        <input type="text" name="sources[new0][path]" class="mono ltr source-path" placeholder="/var/log/myapp | journalctl -n 300 --no-pager | docker compose logs" dir="ltr" style="flex: 1; min-width: 220px;">
      </div>
    </div>
    <button class="btn btn-ghost btn-sm" type="button" id="add-source" style="margin-top: 0.75rem;">＋ <?= __('مسار آخر') ?></button>
  </div>

  <div style="margin-bottom: 1.25rem;">
    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('أنماط الملفات') ?></label>
    <input type="text" name="patterns" value="<?= e(implode(', ', (array)$config['patterns'])) ?>"
           class="mono ltr" placeholder="*.log, *.txt" dir="ltr" style="width: 100%;">
    <p class="muted small" style="margin-top: 0.3rem;"><?= __('مفصولة بفاصلة. مثال:') ?> <span class="mono ltr">*.log, *.txt, error-*</span> — <?= __('اتركها فارغة لعرض كل الملفات.') ?></p>
  </div>

  <div style="margin-bottom: 1.25rem;">
    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
      <input type="checkbox" name="recursive" <?= !empty($config['recursive']) ? 'checked' : '' ?>>
      <span><?= __('البحث في المجلدات الفرعية') ?></span>
    </label>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div>
      <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('عدد الأسطر الافتراضي عند العرض') ?></label>
      <input type="number" name="tail_lines" min="10" max="20000" value="<?= (int)$config['tail_lines'] ?>" dir="ltr" style="width: 100%;">
    </div>
    <div>
      <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('أقصى حجم للعرض (ميجابايت)') ?></label>
      <input type="number" name="max_view_mb" min="1" max="500" value="<?= (int)$config['max_view_mb'] ?>" dir="ltr" style="width: 100%;">
      <p class="muted small" style="margin-top: 0.3rem;"><?= __('الملفات الأكبر تُعرض جزئيًا فقط.') ?></p>
    </div>
  </div>

  <button class="btn btn-primary" type="submit">💾 <?= __('حفظ الإعدادات') ?></button>
</form>

<section style="margin-top: 2rem;">
  <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;"><?= __('معلومات النظام') ?></h2>
  <div class="table-wrap">
    <table class="table">
      <tbody>
        <tr><td style="width: 200px; color: var(--text-muted);"><?= __('مستخدم PHP') ?></td><td class="mono ltr"><?= e(get_current_user()) ?></td></tr>
        <tr><td style="color: var(--text-muted);"><?= __('إصدار PHP') ?></td><td class="mono ltr"><?= e(PHP_VERSION) ?></td></tr>
        <tr><td style="color: var(--text-muted);"><?= __('مجلد البيانات') ?></td><td class="mono ltr"><?= e(DATA_PATH) ?>
          <?= is_writable(DATA_PATH) ? '<span class="tag tag-success">' . __('قابل للكتابة') . '</span>' : '<span class="tag tag-warn">' . __('غير قابل للكتابة') . '</span>' ?></td></tr>
        <tr><td style="color: var(--text-muted);"><?= __('حجم سجل النشاط') ?></td><td class="mono ltr"><?= is_file(AUDIT_FILE) ? bytes_html((int)filesize(AUDIT_FILE)) : '0 B' ?></td></tr>
        <tr><td style="color: var(--text-muted);"><?= __('المنطقة الزمنية') ?></td><td class="mono ltr"><?= e(date_default_timezone_get()) ?></td></tr>
      </tbody>
    </table>
  </div>
</section>

<script>
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
