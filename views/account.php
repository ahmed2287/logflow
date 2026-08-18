<?php layout_start(); ?>

<div class="page-head">
  <div><h1>حسابي</h1>
    <p class="muted"><span class="mono ltr"><?= e($me['username']) ?></span> ·
      <span class="badge badge-<?= $me['role'] === ROLE_ADMIN ? 'admin' : 'viewer' ?>"><?= e(ROLES[$me['role']] ?? $me['role']) ?></span>
    </p>
  </div>
  <a class="btn btn-ghost" href="?page=dashboard">← رجوع</a>
</div>

<form class="panel panel-narrow" method="post" action="?page=account">
  <?= csrf_field() ?>
  <h2>تغيير كلمة المرور</h2>
  <label class="field">
    <span class="field-label">كلمة المرور الحالية</span>
    <input type="password" name="current_password" required dir="ltr" autocomplete="current-password">
  </label>
  <label class="field">
    <span class="field-label">كلمة المرور الجديدة</span>
    <input type="password" name="new_password" required minlength="8" dir="ltr" autocomplete="new-password">
    <small class="muted">8 أحرف على الأقل</small>
  </label>
  <label class="field">
    <span class="field-label">تأكيد كلمة المرور الجديدة</span>
    <input type="password" name="confirm_password" required minlength="8" dir="ltr" autocomplete="new-password">
  </label>
  <button class="btn btn-primary" type="submit">تحديث</button>
</form>

<p class="muted"><a href="?page=audit">← عرض سجل نشاطي</a></p>

<?php layout_end('حسابي', $flashes); ?>
