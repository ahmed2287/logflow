<?php layout_start(); ?>
<div class="auth-card">
  <div class="auth-head">
    <div class="auth-mark">🚀</div>
    <h1><?= __('الإعداد الأول') ?></h1>
    <p class="muted"><?= __('أنشئ حساب المدير الأول للوحة التحكم') ?></p>
  </div>

  <form method="post" action="?page=setup" class="stack">
    <?= csrf_field() ?>
    <label>
      <span><?= __('اسم المستخدم') ?></span>
      <input type="text" name="username" required autofocus pattern="[A-Za-z0-9._\-]{3,32}" dir="ltr"
             placeholder="admin">
      <small class="muted"><?= __('3-32 حرفًا: حروف إنجليزية، أرقام، والرموز . _ -') ?></small>
    </label>
    <label>
      <span><?= __('كلمة المرور') ?></span>
      <input type="password" name="password" required minlength="8" autocomplete="new-password" dir="ltr">
      <small class="muted"><?= __('8 أحرف على الأقل') ?></small>
    </label>
    <label>
      <span><?= __('تأكيد كلمة المرور') ?></span>
      <input type="password" name="confirm" required minlength="8" autocomplete="new-password" dir="ltr">
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= __('إنشاء الحساب') ?></button>
  </form>
</div>
<?php layout_end('الإعداد الأول', $flashes); ?>
