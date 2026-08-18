<?php layout_start(); $me = current_user()['username']; ?>

<div class="page-head">
  <div><h1>المستخدمون</h1><p class="muted"><?= count($users) ?> حساب على اللوحة.</p></div>
  <a class="btn btn-ghost" href="?page=dashboard">← رجوع</a>
</div>

<form class="panel" method="post" action="?page=users">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h2>إضافة مستخدم</h2>
  <div class="field-row">
    <label class="field">
      <span class="field-label">اسم المستخدم</span>
      <input type="text" name="username" required pattern="[A-Za-z0-9._\-]{3,32}" dir="ltr" class="mono">
    </label>
    <label class="field">
      <span class="field-label">كلمة المرور</span>
      <input type="password" name="password" required minlength="8" dir="ltr" autocomplete="new-password">
    </label>
    <label class="field">
      <span class="field-label">الصلاحية</span>
      <select name="role">
        <?php foreach (ROLES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === ROLE_VIEWER ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p class="muted small"><strong>مدير:</strong> يحذف اللوجات، يغيّر الإعدادات، ويدير المستخدمين ويرى سجل الجميع.
    <strong>مشاهدة فقط:</strong> يعرض ويحمّل اللوجات، ويرى سجل نشاطه هو فقط.</p>
  <button class="btn btn-primary" type="submit">➕ إضافة</button>
</form>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>المستخدم</th>
        <th>الصلاحية</th>
        <th class="col-date">أُنشئ</th>
        <th class="col-date">آخر دخول</th>
        <th class="col-actions">إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): $username = (string)$user['username']; $isMe = strcasecmp($username, $me) === 0; ?>
        <tr>
          <td><strong class="mono ltr"><?= e($username) ?></strong>
            <?php if ($isMe): ?><span class="tag">أنت</span><?php endif; ?>
          </td>
          <td>
            <?php if ($isMe): ?>
              <span class="badge badge-<?= ($user['role'] ?? '') === ROLE_ADMIN ? 'admin' : 'viewer' ?>">
                <?= e(ROLES[$user['role'] ?? ''] ?? $user['role'] ?? '') ?>
              </span>
            <?php else: ?>
              <form method="post" action="?page=users" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <select name="role" onchange="this.form.submit()">
                  <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($user['role'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td class="col-date mono ltr"><?= !empty($user['created_at']) ? date('Y-m-d', strtotime((string)$user['created_at'])) : '—' ?></td>
          <td class="col-date mono ltr">
            <?= !empty($user['last_login']) ? date('Y-m-d H:i', strtotime((string)$user['last_login'])) : '—' ?>
          </td>
          <td class="col-actions">
            <details class="dropdown">
              <summary class="btn btn-ghost btn-sm">كلمة مرور</summary>
              <form method="post" action="?page=users" class="dropdown-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <input type="password" name="password" required minlength="8" placeholder="كلمة مرور جديدة" dir="ltr" autocomplete="new-password">
                <button class="btn btn-primary btn-sm" type="submit">تغيير</button>
              </form>
            </details>
            <?php if (!$isMe): ?>
              <form method="post" action="?page=users" class="inline"
                    data-confirm="حذف المستخدم <?= e($username) ?>؟">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <button class="btn btn-danger btn-sm" type="submit">حذف</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php layout_end('المستخدمون', $flashes); ?>
