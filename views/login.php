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
  
  <div class="auth-card">
    <div class="auth-head">
      <div class="auth-mark-wrapper">
        <div class="auth-mark-glow"></div>
        <svg class="auth-logo-svg" width="64" height="64" style="width: 64px; height: 64px; max-width: 64px; max-height: 64px;" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="48" height="48" rx="14" fill="url(#logo-grad)"/>
          <path d="M14 16H34M14 22H30M14 28H24M14 34H28" stroke="white" stroke-width="3" stroke-linecap="round"/>
          <circle cx="34" cy="32" r="6" fill="#38BDF8"/>
          <path d="M32 32L33.5 33.5L36.5 30.5" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <defs>
            <linearGradient id="logo-grad" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
              <stop stop-color="#3B82F6"/>
              <stop offset="0.5" stop-color="#6366F1"/>
              <stop offset="1" stop-color="#8B5CF6"/>
            </linearGradient>
          </defs>
        </svg>
      </div>
      
      <h1 class="gradient-title">LogFlow</h1>
      <p class="subtitle-badge">
        <span class="pulse-dot"></span>
        <?= __('لوحة إدارة ومتابعة اللوجات') ?>
      </p>
    </div>

    <?php if ($locked): ?>
      <div class="alert alert-error">
        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span><?= __('تم إيقاف المحاولات مؤقتًا بعد عدة محاولات فاشلة. حاول بعد قليل.') ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="?page=login" class="auth-form" autocomplete="on">
      <?= csrf_field() ?>
      
      <div class="form-field">
        <label for="input-username">
          <span><?= __('اسم المستخدم') ?></span>
        </label>
        <div class="input-wrapper">
          <input type="text" id="input-username" name="username" required autofocus autocomplete="username" dir="ltr" placeholder="hamdy">
          <svg class="field-icon field-icon-start" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
      </div>

      <div class="form-field">
        <label for="input-password">
          <span><?= __('كلمة المرور') ?></span>
        </label>
        <div class="input-wrapper">
          <input type="password" id="input-password" name="password" required autocomplete="current-password" dir="ltr" placeholder="••••••••">
          <svg class="field-icon field-icon-start" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <button type="button" class="btn-toggle-password" id="toggle-password-btn" title="<?= __('إظهار/إخفاء كلمة المرور') ?>" aria-label="<?= __('إظهار/إخفاء كلمة المرور') ?>">
            <svg class="eye-icon eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="eye-icon eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
      </div>

      <button class="btn btn-primary btn-block btn-submit" type="submit" <?= $locked ? 'disabled' : '' ?>>
        <span><?= __('تسجيل الدخول') ?></span>
        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div class="auth-features">
      <div class="feature-chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span><?= __('حماية مشفرة') ?></span>
      </div>
      <div class="feature-chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <span><?= __('متابعة فورية') ?></span>
      </div>
      <div class="feature-chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span><?= __('سريع وآمن') ?></span>
      </div>
    </div>
  </div>
</div>
<?php layout_end('تسجيل الدخول', $flashes); ?>
