<?php
/**
 * @var string $title
 * @var array  $flashes
 * @var callable $content  rendered by the caller via layout_start/layout_end
 */
$user    = current_user();
$current = (string)($_GET['page'] ?? 'dashboard');
// The audit page has two scopes: "mine" (every user) and "all" (admins only).
$auditScope = $current === 'audit'
    ? ((string)($_GET['scope'] ?? (is_admin() ? 'all' : 'mine')) === 'all' ? 'all' : 'mine')
    : '';
$nav = [
    ['href' => '?page=dashboard',        'label' => 'اللوجات',          'icon' => '📄', 'admin' => false, 'active' => $current === 'dashboard'],
    ['href' => '?page=cleanup',          'label' => 'تنظيف',            'icon' => '🧹', 'admin' => true,  'active' => $current === 'cleanup'],
    ['href' => '?page=audit&scope=mine', 'label' => 'سجل نشاطي',        'icon' => '🙋', 'admin' => false, 'active' => $auditScope === 'mine'],
    ['href' => '?page=audit&scope=all',  'label' => 'سجل نشاط اللوحة',  'icon' => '🕵️', 'admin' => true,  'active' => $auditScope === 'all'],
    ['href' => '?page=server',           'label' => 'السيرفر',          'icon' => '🖥️', 'admin' => false, 'active' => $current === 'server'],
    ['href' => '?page=settings',         'label' => 'الإعدادات',        'icon' => '⚙️', 'admin' => true,  'active' => $current === 'settings'],
    ['href' => '?page=users',            'label' => 'المستخدمون',       'icon' => '👥', 'admin' => true,  'active' => $current === 'users'],
];
?>
<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= e(lang_dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__($title ?? 'لوحة اللوجات')) ?> · AlmasryLog</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
<script>
// Apply the saved theme before first paint so the page doesn't flash.
(function () {
  try {
    var t = localStorage.getItem('almasrylog_theme');
    if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
  } catch (e) {}
})();
</script>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../public/assets/style.css') ?>">
</head>
<body<?= $user ? ' class="with-nav"' : '' ?>>
<?php if ($user): ?>
<aside class="sidebar">
  <div class="brand"><span class="brand-mark">📋</span> AlmasryLog</div>
  <nav class="nav">
    <?php foreach ($nav as $item): ?>
      <?php if ($item['admin'] && !is_admin()) continue; ?>
      <a class="nav-link <?= $item['active'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
        <span aria-hidden="true"><?= $item['icon'] ?></span> <?= e(__($item['label'])) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-end">
    <button class="btn btn-ghost btn-sm theme-toggle" type="button" id="theme-toggle" title="<?= e(__('تبديل الوضع الفاتح/الداكن')) ?>">🌓</button>
    <a class="btn btn-ghost btn-sm lang-toggle" href="?page=lang&amp;to=<?= lang() === 'ar' ? 'en' : 'ar' ?>"
       title="<?= e(__('تبديل اللغة')) ?>"><?= lang() === 'ar' ? 'EN' : 'عربي' ?></a>
    <a class="chip" href="?page=account" title="<?= e(__('حسابي')) ?>">
      <?= e($user['username']) ?>
      <span class="badge badge-<?= $user['role'] === ROLE_ADMIN ? 'admin' : 'viewer' ?>"><?= e(__(ROLES[$user['role']] ?? $user['role'])) ?></span>
    </a>
    <form method="post" action="?page=logout" class="inline">
      <?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('خروج')) ?></button>
    </form>
  </div>
</aside>
<?php else: ?>
<div class="theme-toggle-floating">
  <button class="btn btn-ghost btn-sm theme-toggle" type="button" id="theme-toggle" title="<?= e(__('تبديل الوضع الفاتح/الداكن')) ?>">🌓</button>
  <a class="btn btn-ghost btn-sm lang-toggle" href="?page=lang&amp;to=<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= lang() === 'ar' ? 'EN' : 'عربي' ?></a>
</div>
<?php endif; ?>

<main class="<?= $user ? 'page' : 'page page-centered' ?>">
  <?php foreach (($flashes ?? []) as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?? '' ?>
</main>

<footer class="footer">AlmasryLog — <?= e(__('لوحة إدارة اللوجات')) ?> · <?= date('Y') ?></footer>
<script>
window.APP_I18N = {
  title:  <?= json_encode(__('تأكيد العملية'), JSON_UNESCAPED_UNICODE) ?>,
  yes:    <?= json_encode(__('نعم، نفّذ'), JSON_UNESCAPED_UNICODE) ?>,
  cancel: <?= json_encode(__('إلغاء'), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../public/assets/app.js') ?>"></script>
</body>
</html>
