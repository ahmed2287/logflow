<?php layout_start(); ?>
<div class="auth-card">
  <div class="auth-head">
    <div class="auth-mark">📋</div>
    <h1>AlmasryLog</h1>
    <p class="muted"><?= __('لوحة إدارة ومتابعة اللوجات') ?></p>
  </div>

  <?php if ($locked): ?>
    <div class="alert alert-error"><?= __('تم إيقاف المحاولات مؤقتًا بعد عدة محاولات فاشلة. حاول بعد قليل.') ?></div>
  <?php endif; ?>

  <form method="post" action="?page=login" class="stack" autocomplete="on">
    <?= csrf_field() ?>
    <label>
      <span><?= __('اسم المستخدم') ?></span>
      <input type="text" name="username" required autofocus autocomplete="username" dir="ltr">
    </label>
    <label>
      <span><?= __('كلمة المرور') ?></span>
      <input type="password" name="password" required autocomplete="current-password" dir="ltr">
    </label>
    <button class="btn btn-primary btn-block" type="submit" <?= $locked ? 'disabled' : '' ?>><?= __('دخول') ?></button>
  </form>
</div>
<?php layout_end('تسجيل الدخول', $flashes); ?>
