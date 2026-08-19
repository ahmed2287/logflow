<?php
/** @var string $content @var string $title @var array $flashes */
$me              = current_user();
$page            = (string)($_GET['page'] ?? 'dashboard');
$sources         = log_sources();
$activeSrc       = log_active_source();
$lang            = lang();
$isRtl           = lang_dir() === 'rtl';
$pendingRequests = (function_exists('cleanup_pending_requests') && is_admin()) ? cleanup_pending_requests() : [];
$pendingCount    = count($pendingRequests);
$serverTimestamp = time();
$title           = $title ?? '';
$flashes         = (array)($flashes ?? []);
$content         = $content ?? '';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ? __($title) . ' · LogFlow' : __('LogFlow — لوحة إدارة ومتابعة اللوجات')) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<script>
(function () {
  try {
    var t = localStorage.getItem('logflow_theme') || localStorage.getItem('almasrylog_theme');
    if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
  } catch (e) {}
})();
window.APP_I18N = {
  title: <?= json_encode(__('تأكيد العملية')) ?>,
  yes: <?= json_encode(__('نعم، نفّذ')) ?>,
  cancel: <?= json_encode(__('إلغاء')) ?>,
  synced: <?= json_encode(__('متطابقين (مُتزامن)')) ?>,
  offsetHours: <?= json_encode(__('فرق %d ساعة')) ?>,
  startLiveStream: <?= json_encode(__('▶️ بث مباشر')) ?>,
  stopLiveStream: <?= json_encode(__('⏸ إيقاف البث')) ?>,
  startLiveServer: <?= json_encode(__('▶️ تحديث لايف (1 ثانية)')) ?>,
  stopLiveServer: <?= json_encode(__('⏸ إيقاف التحديث')) ?>,
  runningSince: <?= json_encode(__('شغال منذ')) ?>,
  activeProcess: <?= json_encode(__('عملية نشطة')) ?>
};
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
svg { max-width: 100%; box-sizing: border-box; }
.flow-sparkline { height: 40px !important; max-height: 40px !important; width: 100% !important; display: block !important; overflow: hidden !important; }
summary::-webkit-details-marker { display: none; }
summary { list-style: none; cursor: pointer; }
</style>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../public/assets/style.css') ?>">
</head>
<body class="<?= $page === 'login' ? 'auth-body-page' : 'with-nav' ?>">

<?php if ($page !== 'login'): ?>
<aside class="app-sidebar">
  <div class="brand-wrapper">
    <div class="brand-icon">F</div>
    <div>
      <div class="brand-title">LogFlow</div>
      <div class="brand-subtitle"><?= __('إدارة ومتابعة اللوجات') ?></div>
    </div>
  </div>

  <div class="sidebar-search-pill" onclick="document.querySelector('input[type=search]')?.focus()">
    <span style="display: flex; align-items: center; gap: 0.5rem;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="width: 16px; height: 16px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <span><?= __('بحث سريع…') ?></span>
    </span>
    <kbd>⌘K</kbd>
  </div>

  <div class="nav-section-title"><?= __('القائمة الرئيسية') ?></div>

  <nav class="app-nav">
    <a class="nav-link <?= $page === 'dashboard' ? 'is-active' : '' ?>" href="?page=dashboard<?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
      <span><?= __('اللوحة الرئيسية') ?></span>
    </a>

    <a class="nav-link <?= $page === 'server' ? 'is-active' : '' ?>" href="?page=server<?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
      <span><?= __('السيرفر والموارد') ?></span>
    </a>
  </nav>

  <div class="nav-section-title" style="margin-top: 0.75rem;"><?= __('النظام والصيانة') ?></div>

  <nav class="app-nav">
    <a class="nav-link <?= $page === 'cleanup' ? 'is-active' : '' ?>" href="?page=cleanup<?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
      <span><?= __('تنظيف اللوجات') ?></span>
      <?php if ($pendingCount > 0): ?>
        <span class="nav-badge"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>

    <a class="nav-link <?= $page === 'audit' ? 'is-active' : '' ?>" href="?page=audit<?= e(src_qs()) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
      <span><?= __('سجل النشاط') ?></span>
    </a>

    <?php if (is_admin()): ?>
      <a class="nav-link <?= $page === 'users' ? 'is-active' : '' ?>" href="?page=users<?= e(src_qs()) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span><?= __('المستخدمون') ?></span>
      </a>

      <a class="nav-link <?= $page === 'settings' ? 'is-active' : '' ?>" href="?page=settings<?= e(src_qs()) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span><?= __('الإعدادات') ?></span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-cta-card">
    <h4>🔥 LogFlow Pro</h4>
    <p><?= __('متابعة وتحليل متطورة مع نظام التنبيهات المباشر.') ?></p>
    <a href="?page=server" class="btn btn-primary btn-sm btn-block"><?= __('عرض الموارد') ?></a>
  </div>

  <div class="sidebar-footer">
    <a href="?page=account" class="user-chip" title="<?= e(__('حسابي')) ?>">
      <div class="user-avatar"><?= strtoupper(substr($me['username'] ?? 'H', 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name">
          <span><?= e($me['username'] ?? 'User') ?></span>
          <svg class="verified-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14" style="width: 14px; height: 14px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <div class="user-role"><?= e(__(ROLES[$me['role'] ?? ''] ?? $me['role'] ?? '')) ?></div>
      </div>
    </a>
  </div>
</aside>

<header class="app-header">
  <div class="header-greeting">
    <h2><?= sprintf(__('مرحبًا %s 👋'), e($me['username'] ?? 'User')) ?></h2>
    <p><?= __('إليك ملخص متابعة اللوجات والسيرفر اليوم.') ?></p>
  </div>

  <div class="header-right-controls">
    <!-- Header 3-Column x 2-Row Time Sync Table Widget -->
    <div class="header-time-sync-widget" style="background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); padding: 0.35rem 0.75rem; display: flex; align-items: center;">
      <table style="border-collapse: collapse; text-align: center; margin: 0; padding: 0;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border); font-size: 0.72rem; color: var(--text-muted);">
            <th style="padding: 0 0.6rem 0.2rem; font-weight: 700; white-space: nowrap; color: var(--text-muted);"><?= __('وقت السيرفر') ?></th>
            <th style="padding: 0 0.6rem 0.2rem; font-weight: 700; white-space: nowrap; color: var(--text-muted);"><?= __('وقت الجهاز') ?></th>
            <th style="padding: 0 0.6rem 0.2rem; font-weight: 700; white-space: nowrap; color: var(--text-muted);"><?= __('فرق التوقيت') ?></th>
          </tr>
        </thead>
        <tbody>
          <tr style="font-size: 0.82rem; font-weight: 700; color: var(--text);">
            <td id="hdr-server-time" class="mono ltr" style="padding: 0.2rem 0.6rem 0; color: var(--text);" data-server-ts="<?= $serverTimestamp ?>"><?= date('H:i:s') ?></td>
            <td id="hdr-client-time" class="mono ltr" style="padding: 0.2rem 0.6rem 0; color: var(--text);">--:--:--</td>
            <td style="padding: 0.2rem 0.6rem 0;">
              <span id="hdr-offset-val" class="mono ltr" style="color: var(--accent); font-weight: 800; margin-inline-end: 0.35rem;">00:00:00</span>
              <span id="hdr-offset-badge" class="tag tag-sm tag-success" style="font-size: 0.68rem; padding: 0.1rem 0.35rem;"><?= __('مُتزامن') ?></span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="header-date-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="width: 16px; height: 16px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      <span><?= date('M d, Y') ?></span>
    </div>

    <!-- Notification Dropdown -->
    <details class="dropdown" style="position: relative;">
      <summary class="icon-btn-badge" title="<?= e(__('الإشعارات والتنبيهات')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <?php if ($pendingCount > 0): ?>
          <span class="badge-counter"><?= $pendingCount ?></span>
        <?php endif; ?>
      </summary>
      <div class="dropdown-body" style="position: absolute; inset-inline-end: 0; top: 115%; width: 320px; background: var(--surface-solid); border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 1rem; box-shadow: var(--shadow-lg); z-index: 100;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
          <span>🔔 <?= __('الإشعارات') ?></span>
          <span class="tag tag-info"><?= $pendingCount ?> <?= __('جديد') ?></span>
        </h4>
        <?php if ($pendingCount > 0): ?>
          <div style="display: flex; flex-direction: column; gap: 0.6rem; max-height: 240px; overflow-y: auto;">
            <?php foreach ($pendingRequests as $req): ?>
              <a href="?page=cleanup" style="display: block; padding: 0.6rem; background: var(--surface-2); border-radius: 8px; text-decoration: none;">
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--accent);">📨 <?= __('طلب تنظيف من:') ?> <?= e($req['user']) ?></div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">📁 <?= e($req['src']) ?> (أقدَم من <?= e($req['before']) ?>)</div>
              </a>
            <?php endforeach; ?>
          </div>
          <a href="?page=cleanup" class="btn btn-primary btn-sm btn-block" style="margin-top: 0.75rem;"><?= __('إدارة كافة الطلبات') ?></a>
        <?php else: ?>
          <p class="muted small" style="margin: 0; text-align: center; padding: 0.5rem 0;"><?= __('✅ لا توجد طلبات تنظيف معلقة.') ?></p>
        <?php endif; ?>
      </div>
    </details>

    <button class="icon-btn-badge" type="button" id="theme-toggle-header" title="<?= e(__('تبديل الوضع الداكن/الفاتح')) ?>">🌓</button>
    <a class="btn btn-ghost btn-sm" href="?page=lang&amp;to=<?= $lang === 'ar' ? 'en' : 'ar' ?>"><?= strtoupper($lang === 'ar' ? 'EN' : 'AR') ?></a>
    
    <form method="post" action="?page=logout" class="inline">
      <?= csrf_field() ?>
      <button class="icon-btn-badge" type="submit" title="<?= e(__('تسجيل الخروج')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="width: 18px; height: 18px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </button>
    </form>
  </div>
</header>
<?php endif; ?>

  <?= $content ?>

  <footer class="app-footer" style="margin-top: 3.5rem; padding: 1.25rem 0 0.5rem; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; font-size: 0.84rem; color: var(--text-muted);">
    <div style="display: flex; align-items: center; gap: 0.4rem;">
      <span>⚡ <strong style="color: var(--text);">LogFlow v2.5</strong></span>
      <span>·</span>
      <span><?= __('نظام إدارة ومتابعة اللوجات والسيرفر') ?></span>
    </div>
    <div>
      <?= __('Made with ❤️ by') ?>
      <a href="https://viber-solutions.com" target="_blank" rel="noopener noreferrer" style="color: var(--accent); font-weight: 800; text-decoration: none; transition: opacity 0.2s ease;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
        Viber Solutions 🚀
      </a>
    </div>
  </footer>
</main>

<script>
window.APP_I18N = {
  title: <?= json_encode(__('تأكيد العملية')) ?>,
  yes: <?= json_encode(__('نعم، نفّذ')) ?>,
  cancel: <?= json_encode(__('إلغاء')) ?>
};
</script>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../public/assets/app.js') ?>"></script>
</body>
</html>
