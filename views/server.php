<?php
/** @var string $hostname @var array $load @var array $cpu @var array $memory
 *  @var int $uptime @var array $disks @var array $procs @var int $nproc
 *  @var string $psort @var bool $auto */
$barClass = fn(float $p): string => $p >= 90 ? 'bar-danger' : ($p >= 75 ? 'bar-warn' : '');
$memPct   = $memory['total'] ? $memory['used'] * 100 / $memory['total'] : 0;
$swapPct  = $memory['swap_total'] ? $memory['swap_used'] * 100 / $memory['swap_total'] : 0;
$cores    = array_filter($cpu, fn($k) => $k !== 'all', ARRAY_FILTER_USE_KEY);
layout_start();
?>

<div class="page-head">
  <div>
    <h1>🖥️ <?= __('السيرفر') ?> — <span class="mono ltr"><?= e($hostname) ?></span></h1>
    <p class="muted"><?= __('شغال منذ') ?> <?= e(sys_uptime_human($uptime)) ?> · <?= number_format($nproc) ?> <?= __('عملية نشطة') ?></p>
  </div>
  <div class="head-actions">
    <a class="btn <?= $auto ? 'btn-primary' : 'btn-ghost' ?>" href="?page=server&amp;psort=<?= e($psort) ?><?= $auto ? '' : '&amp;auto=1' ?>">
      <?= $auto ? '⏸ ' . __('إيقاف التحديث التلقائي') : '▶️ ' . __('تحديث تلقائي (كل 5 ثواني)') ?>
    </a>
    <a class="btn btn-ghost" href="?page=server&amp;psort=<?= e($psort) ?><?= $auto ? '&amp;auto=1' : '' ?>">🔄 <?= __('تحديث') ?></a>
    <a class="btn btn-ghost" href="?page=dashboard">← <?= __('رجوع') ?></a>
  </div>
</div>

<div class="cards">
  <div class="card">
    <div class="card-label">CPU</div>
    <div class="card-value"><?= number_format((float)($cpu['all'] ?? 0), 1) ?>%</div>
    <div class="card-meta"><?= count($cores) ?> <?= __('كور') ?></div>
    <div class="day-bar sys-bar <?= $barClass((float)($cpu['all'] ?? 0)) ?>"><span style="width:<?= min(100, (float)($cpu['all'] ?? 0)) ?>%"></span></div>
  </div>
  <div class="card">
    <div class="card-label">Load Average (1 · 5 · 15)</div>
    <div class="card-value ltr"><?= number_format($load['1'], 2) ?> · <?= number_format($load['5'], 2) ?> · <?= number_format($load['15'], 2) ?></div>
    <div class="card-meta"><?= __('نسبةً لعدد الكورات:') ?> <?= count($cores) ? number_format($load['1'] * 100 / count($cores), 0) : 0 ?>%</div>
  </div>
  <div class="card">
    <div class="card-label"><?= __('الذاكرة') ?></div>
    <div class="card-value"><?= bytes_html($memory['used']) ?> <small>/ <?= bytes_html($memory['total']) ?></small></div>
    <div class="card-meta"><?= __('متاح:') ?> <?= bytes_html($memory['available']) ?> · <?= __('كاش:') ?> <?= bytes_html($memory['cached']) ?></div>
    <div class="day-bar sys-bar <?= $barClass($memPct) ?>"><span style="width:<?= min(100, $memPct) ?>%"></span></div>
  </div>
  <div class="card">
    <div class="card-label">Swap</div>
    <div class="card-value"><?= bytes_html($memory['swap_used']) ?> <small>/ <?= bytes_html($memory['swap_total']) ?></small></div>
    <div class="day-bar sys-bar <?= $barClass($swapPct) ?>"><span style="width:<?= min(100, $swapPct) ?>%"></span></div>
  </div>
</div>

<section class="section">
  <h2 class="section-title"><?= __('استهلاك كل كور') ?></h2>
  <div class="core-grid">
    <?php foreach ($cores as $i => $pct): ?>
      <div class="core-cell">
        <span class="core-label mono"><?= (int)$i ?></span>
        <div class="day-bar sys-bar <?= $barClass((float)$pct) ?>"><span style="width:<?= min(100, (float)$pct) ?>%"></span></div>
        <span class="core-pct mono ltr"><?= number_format((float)$pct, 0) ?>%</span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <h2 class="section-title">💾 <?= __('مساحة السيرفر (البارتشنات)') ?></h2>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= __('نقطة التركيب') ?></th>
          <th><?= __('الجهاز') ?></th>
          <th><?= __('النظام') ?></th>
          <th class="col-num"><?= __('الحجم') ?></th>
          <th class="col-num"><?= __('مستخدم') ?></th>
          <th class="col-num"><?= __('متاح') ?></th>
          <th><?= __('الاستخدام') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($disks as $disk): ?>
          <tr>
            <td class="mono ltr"><strong><?= e($disk['mount']) ?></strong></td>
            <td class="mono ltr"><?= e($disk['source']) ?></td>
            <td class="mono ltr"><?= e($disk['fstype']) ?></td>
            <td class="col-num mono"><?= bytes_html($disk['size']) ?></td>
            <td class="col-num mono"><?= bytes_html($disk['used']) ?></td>
            <td class="col-num mono"><strong><?= bytes_html($disk['avail']) ?></strong></td>
            <td style="min-width:160px">
              <div class="day-bar sys-bar <?= $barClass((float)$disk['pcent']) ?>"><span style="width:<?= min(100, $disk['pcent']) ?>%"></span></div>
              <span class="mono ltr small"><?= $disk['pcent'] ?>%</span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2>⚙️ <?= __('أعلى العمليات') ?></h2>
    <div class="quick-days">
      <a class="chip-day <?= $psort === 'cpu' ? 'is-active' : '' ?>" href="?page=server&amp;psort=cpu<?= $auto ? '&amp;auto=1' : '' ?>">CPU</a>
      <a class="chip-day <?= $psort === 'mem' ? 'is-active' : '' ?>" href="?page=server&amp;psort=mem<?= $auto ? '&amp;auto=1' : '' ?>"><?= __('الذاكرة') ?></a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th class="col-num">PID</th>
          <th><?= __('المستخدم') ?></th>
          <th class="col-num">CPU%</th>
          <th class="col-num">MEM%</th>
          <th class="col-num">RSS</th>
          <th><?= __('الحالة') ?></th>
          <th><?= __('المدة') ?></th>
          <th><?= __('الأمر') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($procs as $proc): ?>
          <tr>
            <td class="col-num mono"><?= (int)$proc['pid'] ?></td>
            <td class="mono ltr"><?= e($proc['user']) ?></td>
            <td class="col-num mono"><strong><?= number_format($proc['cpu'], 1) ?></strong></td>
            <td class="col-num mono"><?= number_format($proc['mem'], 1) ?></td>
            <td class="col-num mono"><?= bytes_html($proc['rss']) ?></td>
            <td class="mono ltr"><?= e($proc['stat']) ?></td>
            <td class="mono ltr"><?= e($proc['etime']) ?></td>
            <td class="col-sample"><code class="sample mono ltr" title="<?= e($proc['cmd']) ?>"><?= e($proc['cmd']) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($auto): ?>
  <script>setTimeout(function () { location.reload(); }, 5000);</script>
<?php endif; ?>

<?php layout_end('السيرفر', $flashes); ?>
