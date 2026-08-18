<?php
/**
 * @var string $title
 * @var array  $flashes
 * @var callable $content  rendered by the caller via layout_start/layout_end
 */
$user    = current_user();
$current = (string)($_GET['page'] ?? 'dashboard');
$nav = [
    'dashboard' => ['label' => 'اللوجات',   'icon' => '📄', 'admin' => false],
    'cleanup'   => ['label' => 'تنظيف',      'icon' => '🧹', 'admin' => true],
    'audit'     => ['label' => 'سجل النشاط', 'icon' => '🕵️', 'admin' => false],
    'settings'  => ['label' => 'الإعدادات',  'icon' => '⚙️', 'admin' => true],
    'users'     => ['label' => 'المستخدمون', 'icon' => '👥', 'admin' => true],
];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'لوحة اللوجات') ?> · AlmasryLog</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
  <div class="brand"><span class="brand-mark">📋</span> AlmasryLog</div>
  <nav class="nav">
    <?php foreach ($nav as $key => $item): ?>
      <?php if ($item['admin'] && !is_admin()) continue; ?>
      <a class="nav-link <?= $current === $key ? 'is-active' : '' ?>" href="?page=<?= e($key) ?>">
        <span aria-hidden="true"><?= $item['icon'] ?></span> <?= e($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="topbar-end">
    <a class="chip" href="?page=account" title="حسابي">
      <?= e($user['username']) ?>
      <span class="badge badge-<?= $user['role'] === ROLE_ADMIN ? 'admin' : 'viewer' ?>"><?= e(ROLES[$user['role']] ?? $user['role']) ?></span>
    </a>
    <form method="post" action="?page=logout" class="inline">
      <?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm" type="submit">خروج</button>
    </form>
  </div>
</header>
<?php endif; ?>

<main class="<?= $user ? 'page' : 'page page-centered' ?>">
  <?php foreach (($flashes ?? []) as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?? '' ?>
</main>

<footer class="footer">AlmasryLog — لوحة إدارة اللوجات · <?= date('Y') ?></footer>
<script src="assets/app.js"></script>
</body>
</html>
