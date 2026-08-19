<?php layout_start(); ?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('حسابي') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;">
      <span class="mono ltr" style="font-weight: 700;"><?= e($me['username']) ?></span> ·
      <span class="tag tag-<?= $me['role'] === ROLE_ADMIN ? 'info' : 'success' ?>"><?= e(__(ROLES[$me['role']] ?? $me['role'])) ?></span>
    </p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('رجوع') ?></a>
</div>

<form class="saas-card" method="post" action="?page=account" style="max-width: 480px; margin-bottom: 2rem;">
  <?= csrf_field() ?>
  <h2 style="margin-top: 0; font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;"><?= __('تغيير كلمة المرور') ?></h2>
  
  <div style="margin-bottom: 1rem;">
    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('كلمة المرور الحالية') ?></label>
    <input type="password" name="current_password" required dir="ltr" autocomplete="current-password" style="width: 100%;">
  </div>

  <div style="margin-bottom: 1rem;">
    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('كلمة المرور الجديدة') ?></label>
    <input type="password" name="new_password" required minlength="8" dir="ltr" autocomplete="new-password" style="width: 100%;">
    <p class="muted small" style="margin-top: 0.25rem;"><?= __('8 أحرف على الأقل') ?></p>
  </div>

  <div style="margin-bottom: 1.5rem;">
    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem;"><?= __('تأكيد كلمة المرور الجديدة') ?></label>
    <input type="password" name="confirm_password" required minlength="8" dir="ltr" autocomplete="new-password" style="width: 100%;">
  </div>

  <button class="btn btn-primary" type="submit"><?= __('تحديث') ?></button>
</form>

<p class="muted"><a class="btn btn-ghost btn-sm" href="?page=audit&amp;scope=mine">← <?= __('عرض سجل نشاطي') ?></a></p>

<?php layout_end('حسابي', $flashes); ?>
