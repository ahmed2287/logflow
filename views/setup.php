<?php layout_start(); ?>
<div class="auth-card">
  <div class="auth-head">
    <div class="auth-mark">🚀</div>
    <h1>الإعداد الأول</h1>
    <p class="muted">أنشئ حساب المدير الأول للوحة التحكم</p>
  </div>

  <form method="post" action="?page=setup" class="stack">
    <?= csrf_field() ?>
    <label>
      <span>اسم المستخدم</span>
      <input type="text" name="username" required autofocus pattern="[A-Za-z0-9._\-]{3,32}" dir="ltr"
             placeholder="admin">
      <small class="muted">3-32 حرفًا: حروف إنجليزية، أرقام، والرموز . _ -</small>
    </label>
    <label>
      <span>كلمة المرور</span>
      <input type="password" name="password" required minlength="8" autocomplete="new-password" dir="ltr">
      <small class="muted">8 أحرف على الأقل</small>
    </label>
    <label>
      <span>تأكيد كلمة المرور</span>
      <input type="password" name="confirm" required minlength="8" autocomplete="new-password" dir="ltr">
    </label>
    <button class="btn btn-primary btn-block" type="submit">إنشاء الحساب</button>
  </form>
</div>
<?php layout_end('الإعداد الأول', $flashes); ?>
