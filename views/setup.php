<?php layout_start(); ?>
<div class="auth-top-controls" style="position: fixed; top: 1.5rem; inset-inline-end: 1.5rem; display: flex; align-items: center; gap: 0.6rem; z-index: 100;">
  <button class="icon-btn-badge" type="button" id="theme-toggle" title="<?= e(__('تبديل الوضع الداكن/الفاتح')) ?>">🌓</button>
  <a class="btn btn-ghost btn-sm" href="?page=lang&amp;to=<?= lang() === 'ar' ? 'en' : 'ar' ?>" style="background: var(--surface); border: 1px solid var(--border); font-weight: 700; border-radius: 12px; height: 40px; padding: 0 0.85rem; display: inline-flex; align-items: center; justify-content: center;"><?= strtoupper(lang() === 'ar' ? 'EN' : 'AR') ?></a>
</div>

<div class="auth-wrapper">
  <div class="auth-bg-grid"></div>
  <div class="auth-glow-1"></div>
  <div class="auth-glow-2"></div>
  <div class="auth-glow-3"></div>
  
  <div class="auth-card" style="max-width: 440px; margin: 2rem auto;">
    <div class="auth-head" style="margin-bottom: 1.75rem;">
      <div class="auth-mark-wrapper" style="margin-bottom: 1rem;">
        <div class="auth-mark-glow"></div>
        <div style="font-size: 2.2rem; line-height: 1;">🚀</div>
      </div>
      
      <h1 class="gradient-title" style="font-size: 1.8rem; margin-bottom: 0.4rem;"><?= __('الإعداد الأول') ?></h1>
      <p class="subtitle-badge">
        <span class="pulse-dot"></span>
        <?= __('أنشئ حساب المدير الأول للوحة التحكم') ?>
      </p>
    </div>

    <form method="post" action="?page=setup" class="auth-form" autocomplete="on" style="display: flex; flex-direction: column; gap: 1.25rem;">
      <?= csrf_field() ?>
      
      <div class="form-field">
        <label for="input-username" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: var(--text);">
          <span><?= __('اسم المستخدم') ?></span>
        </label>
        <div class="input-wrapper">
          <input type="text" id="input-username" name="username" required autofocus pattern="[A-Za-z0-9._\-]{3,32}" autocomplete="username" dir="ltr" placeholder="admin">
          <svg class="field-icon field-icon-start" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
        <small class="muted small" style="margin-top: 0.4rem; display: block; font-size: 0.78rem;"><?= __('3-32 حرفًا: حروف إنجليزية، أرقام، والرموز . _ -') ?></small>
      </div>

      <div class="form-field">
        <label for="input-password" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: var(--text);">
          <span><?= __('كلمة المرور') ?></span>
        </label>
        <div class="input-wrapper">
          <input type="password" id="input-password" name="password" required minlength="8" autocomplete="new-password" dir="ltr" placeholder="••••••••">
          <svg class="field-icon field-icon-start" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <small class="muted small" style="margin-top: 0.4rem; display: block; font-size: 0.78rem;"><?= __('8 أحرف على الأقل') ?></small>
      </div>

      <div class="form-field">
        <label for="input-confirm" style="display: block; margin-bottom: 0.5rem; font-weight: 700; color: var(--text);">
          <span><?= __('تأكيد كلمة المرور') ?></span>
        </label>
        <div class="input-wrapper">
          <input type="password" id="input-confirm" name="confirm" required minlength="8" autocomplete="new-password" dir="ltr" placeholder="••••••••">
          <svg class="field-icon field-icon-start" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
        </div>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top: 1.25rem; width: 100%; font-weight: 700; font-size: 1rem;"><?= __('إنشاء الحساب') ?></button>
    </form>
  </div>
</div>
<?php layout_end(__('الإعداد الأول'), $flashes); ?>
