<?php
/** @var array $files @var int $totalFiles @var int $totalBytes @var ?int $oldestMtime
 *  @var ?int $latestMtime @var array $sources @var ?array $active @var array $options
 *  @var array $status @var array $skipped */
$files      = $files ?? [];
$totals     = $totals ?? ['count' => count($files), 'bytes' => 0];
$sources    = $sources ?? [];
$active     = $active ?? null;
$options    = $options ?? [];
$status     = $status ?? ['ok' => true];
$skipped    = $skipped ?? [];

layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('ملفات وسجلات اللوجات') ?><?= $active ? ' — ' . e($active['name']) : '' ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= __('عرض، استكشاف، وتنزيل ملفات السجلات الحية للنظام والتطبيقات.') ?></p>
  </div>
  <div style="display: flex; gap: 0.6rem; align-items: center;">
    <a class="btn btn-primary" href="?page=dashboard">← <?= __('اللوحة الرئيسية') ?></a>
  </div>
</div>

<?php if (!$status['ok']): ?>
  <div class="alert alert-warn" style="margin-bottom: 1.5rem;">
    ⚠️ <?= e($status['message']) ?>
  </div>
<?php endif; ?>

<!-- 1. Source Tabs (Solid Orange System Buttons with White Folder Icons) -->
<?php if (count($sources) > 1): ?>
  <nav class="src-tabs" style="display: flex; gap: 0.6rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
    <?php foreach ($sources as $source): ?>
      <?php $isActive = $active && $active['name'] === $source['name']; ?>
      <a class="btn <?= $isActive ? 'btn-primary is-active-tab' : '' ?>"
         href="?page=logs&amp;src=<?= urlencode($source['name']) ?>">
        <svg viewBox="0 0 24 24" fill="rgba(255, 255, 255, 0.25)" stroke="#ffffff" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px; max-width: 18px; max-height: 18px; flex-shrink: 0; color: #ffffff;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <span style="color: #ffffff; font-weight: 700;"><?= e($source['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<!-- 2. Search & Filter Toolbar -->
<form class="saas-toolbar" method="get" action="" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.25rem;">
  <input type="hidden" name="page" value="logs">
  <?php if ($active): ?><input type="hidden" name="src" value="<?= e($active['name']) ?>"><?php endif; ?>
  
  <div class="search-field" style="flex: 1; min-width: 220px;">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px; max-width: 18px; max-height: 18px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('ابحث في أسماء الملفات…')) ?>">
  </div>

  <select name="min_age" style="height: 40px; border-radius: var(--radius); font-size: 0.84rem;">
    <option value=""><?= __('كل الأعمار (الأيام)') ?></option>
    <option value="1" <?= ($_GET['min_age'] ?? '') === '1' ? 'selected' : '' ?>><?= __('أقدم من 1 يوم') ?></option>
    <option value="7" <?= ($_GET['min_age'] ?? '') === '7' ? 'selected' : '' ?>><?= __('أقدم من 7 أيام') ?></option>
    <option value="30" <?= ($_GET['min_age'] ?? '') === '30' ? 'selected' : '' ?>><?= __('أقدم من 30 يوم') ?></option>
  </select>

  <select name="sort" style="height: 40px; border-radius: var(--radius); font-size: 0.84rem;">
    <option value="mtime" <?= ($_GET['sort'] ?? 'mtime') === 'mtime' ? 'selected' : '' ?>><?= __('الترتيب حسب: التاريخ') ?></option>
    <option value="size" <?= ($_GET['sort'] ?? '') === 'size' ? 'selected' : '' ?>><?= __('الترتيب حسب: الحجم') ?></option>
    <option value="name" <?= ($_GET['sort'] ?? '') === 'name' ? 'selected' : '' ?>><?= __('الترتيب حسب: الاسم') ?></option>
  </select>

  <select name="dir" style="height: 40px; border-radius: var(--radius); font-size: 0.84rem;">
    <option value="desc" <?= ($_GET['dir'] ?? 'desc') === 'desc' ? 'selected' : '' ?>><?= __('تنازلي ⬇') ?></option>
    <option value="asc" <?= ($_GET['dir'] ?? '') === 'asc' ? 'selected' : '' ?>><?= __('تصاعدي ⬆') ?></option>
  </select>

  <button class="btn btn-primary btn-sm" type="submit" style="height: 40px; font-weight: 700; padding: 0 1.2rem;"><?= __('تطبيق الفلتر') ?></button>
</form>

<!-- 3. Log Files Data Table -->
<form method="post" action="?page=delete" id="form-bulk-delete">
  <?= csrf_field() ?>
  <input type="hidden" name="src" value="<?= e($active ? $active['name'] : '') ?>">

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width: 40px;"><input type="checkbox" id="check-all" title="<?= e(__('تحديد الكل')) ?>"></th>
          <th><?= __('اسم الملف') ?></th>
          <th class="col-num"><?= __('الحجم') ?></th>
          <th class="col-date"><?= __('آخر تعديل') ?></th>
          <th class="col-actions"><?= __('الإجراءات') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$files): ?>
          <tr>
            <td colspan="5" class="empty-state">
              📂 <?= __('لا توجد ملفات لوجات مطابقة للبحث أو المجلد فارغ.') ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($files as $file): ?>
            <tr>
              <td><input type="checkbox" name="files[]" value="<?= e($file['rel']) ?>" class="row-check" data-size="<?= (int)$file['size'] ?>"></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px; max-width: 18px; max-height: 18px; color: var(--accent);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                  <a class="mono ltr" style="font-weight: 700; color: #ffffff;" href="?page=view&amp;file=<?= urlencode($file['rel']) ?><?= e(src_qs()) ?>"><?= e($file['rel']) ?></a>
                </div>
              </td>
              <td class="col-num mono"><?= bytes_html($file['size']) ?></td>
              <td class="col-date mono ltr"><?= date('Y-m-d H:i', $file['mtime']) ?></td>
              <td class="col-actions">
                <a class="btn btn-ghost btn-sm" href="?page=view&amp;file=<?= urlencode($file['rel']) ?><?= e(src_qs()) ?>">👁️ <?= __('عرض') ?></a>
                <a class="btn btn-ghost btn-sm" href="?page=download&amp;file=<?= urlencode($file['rel']) ?><?= e(src_qs()) ?>">⬇ <?= __('تحميل') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<?php
layout_end('ملفات اللوجات');
