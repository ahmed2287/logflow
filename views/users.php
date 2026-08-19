<?php layout_start(); $me = current_user()['username']; ?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('المستخدمون') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= count($users) ?> <?= __('حساب على اللوحة.') ?></p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('رجوع') ?></a>
</div>

<form class="saas-card" method="post" action="?page=users" style="margin-bottom: 2rem;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h2 style="margin-top: 0; font-size: 1.15rem; font-weight: 700;"><?= __('إضافة مستخدم') ?></h2>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
    <div>
      <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('اسم المستخدم') ?></label>
      <input type="text" name="username" required pattern="[A-Za-z0-9._\-]{3,32}" dir="ltr" class="mono" style="width: 100%;">
    </div>
    <div>
      <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('كلمة المرور') ?></label>
      <input type="password" name="password" required minlength="8" dir="ltr" autocomplete="new-password" style="width: 100%;">
    </div>
    <div>
      <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('الصلاحية') ?></label>
      <select name="role" style="width: 100%;">
        <?php foreach (ROLES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === ROLE_VIEWER ? 'selected' : '' ?>><?= e(__($label)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <p class="muted small" style="margin-bottom: 1.25rem;">
    <strong><?= __('مدير') ?>:</strong> <?= __('يحذف اللوجات، يغيّر الإعدادات، ويدير المستخدمين ويرى سجل الجميع.') ?><br>
    <strong><?= __('مشاهدة فقط') ?>:</strong> <?= __('يعرض ويحمّل اللوجات، ويرى سجل نشاطه هو فقط.') ?>
  </p>
  <button class="btn btn-primary" type="submit">➕ <?= __('إضافة') ?></button>
</form>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th><?= __('المستخدم') ?></th>
        <th><?= __('الصلاحية') ?></th>
        <th class="col-date"><?= __('أُنشئ') ?></th>
        <th class="col-date"><?= __('آخر دخول') ?></th>
        <th class="col-actions"><?= __('إجراءات') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): $username = (string)$user['username']; $isMe = strcasecmp($username, $me) === 0; ?>
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <strong class="mono ltr"><?= e($username) ?></strong>
              <?php if ($isMe): ?><span class="tag tag-info"><?= __('أنت') ?></span><?php endif; ?>
            </div>
          </td>
          <td>
            <?php if ($isMe): ?>
              <span class="tag tag-<?= ($user['role'] ?? '') === ROLE_ADMIN ? 'info' : 'success' ?>">
                <?= e(__(ROLES[$user['role'] ?? ''] ?? $user['role'] ?? '')) ?>
              </span>
            <?php else: ?>
              <form method="post" action="?page=users" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <select name="role" onchange="this.form.submit()" style="height: 2.2rem; font-size: 0.82rem;">
                  <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($user['role'] ?? '') === $key ? 'selected' : '' ?>><?= e(__($label)) ?></option>
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
              <summary class="btn btn-ghost btn-sm"><?= __('كلمة مرور') ?></summary>
              <form method="post" action="?page=users" class="dropdown-body" style="padding: 0.5rem; background: var(--surface-solid); border: 1px solid var(--border-strong); border-radius: 10px; margin-top: 0.25rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <input type="password" name="password" required minlength="8" placeholder="<?= e(__('كلمة مرور جديدة')) ?>" dir="ltr" autocomplete="new-password" style="height: 2.2rem;">
                <button class="btn btn-primary btn-sm" type="submit"><?= __('تغيير') ?></button>
              </form>
            </details>
            <?php if (!$isMe): ?>
              <form method="post" action="?page=users" class="inline"
                    data-confirm="<?= e(sprintf(__('حذف المستخدم %s؟'), $username)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= __('حذف') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php layout_end('المستخدمون', $flashes); ?>
