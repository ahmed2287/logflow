<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1><?= $scope === 'mine' ? '🙋 ' . __('سجل نشاطي') : '🕵️ ' . __('سجل نشاط اللوحة') ?></h1>
    <p class="muted">
      <?= $scope === 'mine' ? __('سجل نشاطك الشخصي.') : __('كل عمليات المستخدمين — من فعل ماذا ومتى.') ?>
      <?= __('إجمالي') ?> <strong><?= number_format($total) ?></strong> <?= __('عملية.') ?>
    </p>
  </div>
  <a class="btn btn-ghost" href="?page=dashboard">← <?= __('رجوع') ?></a>
</div>

<?php if ($scope === 'all' && $stats): ?>
  <section class="section">
    <h2 class="section-title"><?= __('ملخص الحذف لكل مستخدم') ?></h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= __('المستخدم') ?></th>
            <th class="col-num"><?= __('ملفات محذوفة') ?></th>
            <th class="col-num"><?= __('حجم محرَّر') ?></th>
            <th class="col-num"><?= __('عمليات') ?></th>
            <th class="col-date"><?= __('آخر عملية') ?></th>
            <th class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats as $username => $stat): ?>
            <tr>
              <td><strong><?= e($username) ?></strong></td>
              <td class="col-num mono"><?= number_format($stat['files']) ?></td>
              <td class="col-num mono"><?= bytes_html($stat['bytes']) ?></td>
              <td class="col-num mono"><?= number_format($stat['events']) ?></td>
              <td class="col-date mono ltr">
                <?= $stat['last'] ? date('Y-m-d H:i', strtotime($stat['last'])) : '—' ?>
              </td>
              <td class="col-actions">
                <a class="btn btn-ghost btn-sm" href="?page=audit&amp;scope=all&amp;user=<?= urlencode($username) ?>&amp;action=delete_file"><?= __('تفاصيل') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<form class="toolbar" method="get" action="">
  <input type="hidden" name="page" value="audit">
  <input type="hidden" name="scope" value="<?= e($scope) ?>">
  <?php if ($scope === 'all'): ?>
    <select name="user">
      <option value=""><?= __('كل المستخدمين') ?></option>
      <?php foreach ($users as $username): ?>
        <option value="<?= e($username) ?>" <?= $filters['user'] === $username ? 'selected' : '' ?>><?= e($username) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <select name="action">
    <option value=""><?= __('كل الإجراءات') ?></option>
    <?php foreach (AUDIT_ACTIONS as $key => $label): ?>
      <option value="<?= e($key) ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= e(__($label)) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="toolbar-field"><span><?= __('من') ?></span><input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
  <label class="toolbar-field"><span><?= __('إلى') ?></span><input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
  <input class="input-search" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('ابحث في التفاصيل…')) ?>">
  <button class="btn btn-ghost" type="submit"><?= __('تصفية') ?></button>
  <a class="btn btn-ghost" href="?page=audit&amp;scope=<?= e($scope) ?>"><?= __('مسح') ?></a>
</form>

<?php if (!$rows): ?>
  <div class="empty">
    <div class="empty-mark">🗒️</div>
    <h2><?= __('لا توجد سجلات مطابقة') ?></h2>
    <p class="muted"><?= __('جرّب توسيع نطاق التصفية.') ?></p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th class="col-date"><?= __('الوقت') ?></th>
          <th><?= __('المستخدم') ?></th>
          <th><?= __('الإجراء') ?></th>
          <th><?= __('التفاصيل') ?></th>
          <th class="col-ip">IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
            $action  = (string)($row['action'] ?? '');
            $details = is_array($row['details'] ?? null) ? $row['details'] : [];
            $rowCls  = in_array($action, ['delete_file', 'cleanup'], true) ? 'row-destructive'
                     : ($action === 'login_failed' ? 'row-warn' : '');
          ?>
          <tr class="<?= $rowCls ?>">
            <td class="col-date mono ltr">
              <?= e(date('Y-m-d H:i:s', strtotime((string)($row['ts'] ?? 'now')))) ?>
            </td>
            <td><strong><?= e((string)($row['user'] ?? '—')) ?></strong></td>
            <td><span class="tag tag-<?= e($action) ?>"><?= e(__(AUDIT_ACTIONS[$action] ?? $action)) ?></span></td>
            <td class="col-details">
              <?php if ($action === 'cleanup' && isset($details['before'])): ?>
                <?= __('حذف السطور الأقدم من') ?> <strong class="ltr"><?= e((string)$details['before']) ?></strong> —
                <strong><?= number_format((int)($details['lines'] ?? 0)) ?></strong> <?= __('سطر') ?>
                (<?= bytes_html((int)($details['bytes'] ?? 0)) ?>)
              <?php elseif ($action === 'cleanup'): ?>
                <?= __('أقدم من') ?> <strong><?= (int)($details['days'] ?? 0) ?></strong> <?= __('يوم') ?> —
                <?= __('حذف') ?> <strong><?= (int)($details['count'] ?? 0) ?></strong> <?= __('ملف') ?>
                (<?= bytes_html((int)($details['bytes'] ?? 0)) ?>)
              <?php elseif ($action === 'delete_file'): ?>
                <?= __('حذف') ?> <strong><?= (int)($details['count'] ?? 0) ?></strong> <?= __('ملف') ?>
                (<?= bytes_html((int)($details['bytes'] ?? 0)) ?>)
              <?php elseif (!empty($details['file'])): ?>
                <span class="mono ltr trunc"><?= e((string)$details['file']) ?></span>
              <?php elseif (!empty($details['username'])): ?>
                <span class="mono ltr"><?= e((string)$details['username']) ?></span>
                <?= !empty($details['role']) ? '(' . e(__(ROLES[$details['role']] ?? $details['role'])) . ')' : '' ?>
              <?php elseif ($action === 'settings'): ?>
                <span class="muted"><?= __('تعديل إعدادات اللوحة') ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>

              <?php if (!empty($details['src'])): ?>
                <span class="tag">📁 <?= e((string)$details['src']) ?></span>
              <?php endif; ?>

              <?php if (!empty($details['files']) && is_array($details['files'])): ?>
                <details class="files-details">
                  <summary><?= __('عرض الملفات') ?> (<?= count($details['files']) ?>)</summary>
                  <ul class="file-list mono ltr">
                    <?php foreach (array_slice($details['files'], 0, 500) as $file): ?>
                      <li><?= e((string)$file) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($details['files']) > 500): ?>
                      <li class="muted">… +<?= count($details['files']) - 500 ?></li>
                    <?php endif; ?>
                  </ul>
                </details>
              <?php endif; ?>
            </td>
            <td class="col-ip mono ltr muted"><?= e((string)($row['ip'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
    $pages = (int)ceil($total / $perPage);
    if ($pages > 1):
      $query = $_GET;
  ?>
    <nav class="pager">
      <?php if ($page > 1): $query['p'] = $page - 1; ?>
        <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($query)) ?>"><?= __('السابق') ?></a>
      <?php endif; ?>
      <span class="muted"><?= __('صفحة') ?> <?= $page ?> <?= __('من') ?> <?= $pages ?></span>
      <?php if ($page < $pages): $query['p'] = $page + 1; ?>
        <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($query)) ?>"><?= __('التالي') ?></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php layout_end($scope === 'mine' ? 'سجل نشاطي' : 'سجل نشاط اللوحة', $flashes); ?>
