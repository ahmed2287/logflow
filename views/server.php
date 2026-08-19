<?php
/** @var string $hostname @var array $load @var array $cpu @var array $memory
 *  @var int $uptime @var array $disks @var array $procs @var int $nproc
 *  @var string $psort @var bool $auto */
$barClass = fn(float $p): string => $p >= 90 ? 'tag-error' : ($p >= 75 ? 'tag-warn' : 'tag-success');
$memPct   = $memory['total'] ? $memory['used'] * 100 / $memory['total'] : 0;
$swapPct  = $memory['swap_total'] ? $memory['swap_used'] * 100 / $memory['swap_total'] : 0;
$cores    = array_filter($cpu, fn($k) => $k !== 'all', ARRAY_FILTER_USE_KEY);
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title" style="margin: 0; font-size: 1.6rem;"><?= __('السيرفر') ?> — <span class="mono ltr" style="font-size: 1.3rem; color: var(--text);"><?= e($hostname) ?></span></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;" id="server-uptime-nproc"><?= __('فعال منذ') ?> <?= e(sys_uptime_human($uptime)) ?> · <?= number_format($nproc) ?> <?= __('عملية نشطة') ?></p>
  </div>
  <div class="head-actions" style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
    <a class="btn" href="?page=dashboard" style="background: rgba(255, 107, 0, 0.15); color: var(--accent); border: 1px solid rgba(255, 107, 0, 0.3); font-weight: 700;">← <?= __('رجوع') ?></a>
    <a class="btn btn-primary" href="?page=server&amp;psort=<?= e($psort) ?><?= $auto ? '&amp;auto=1' : '' ?>">🔄 <?= __('تحديث') ?></a>
    <button type="button" class="btn btn-primary" id="btn-live-server" data-psort="<?= e($psort) ?>" title="<?= e(__('تحديث استهلاك الموارد لايف كل ثانية')) ?>">
      <span class="live-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block;"></span>
      <span class="live-label">🔄 <?= __('تحديث تلقائي (كل 5 ثواني)') ?></span>
    </button>
  </div>
</div>

<!-- 4 Elevated Metric Cards for Server -->
<div class="flow-cards-grid" style="align-items: start; align-content: start;">
  <div class="flow-card" style="height: auto; min-height: 0; align-self: start;">
    <div class="flow-card-header">
      <span class="flow-card-title">CPU Usage</span>
      <span class="tag <?= $barClass((float)($cpu['all'] ?? 0)) ?>"><?= count($cores) ?> <?= __('كور') ?></span>
    </div>
    <div class="flow-card-value" id="srv-cpu-val"><?= number_format((float)($cpu['all'] ?? 0), 1) ?>%</div>
    <div style="background: rgba(255, 255, 255, 0.1); border-radius: 999px; height: 8px; overflow: hidden; margin-top: 0.75rem;">
      <div id="srv-cpu-bar" style="width: <?= min(100, (float)($cpu['all'] ?? 0)) ?>%; height: 100%; background: var(--accent-gradient); border-radius: 999px; transition: width 0.3s ease;"></div>
    </div>
  </div>

  <div class="flow-card" style="height: auto; min-height: 0; align-self: start;">
    <div class="flow-card-header">
      <span class="flow-card-title">Load Average (1 · 5 · 15)</span>
      <span class="tag tag-info" id="srv-load-badge"><?= count($cores) ? number_format($load['1'] * 100 / count($cores), 0) : 0 ?>% Load</span>
    </div>
    <div class="flow-card-value ltr" id="srv-load-val" style="font-size: 1.5rem;"><?= number_format($load['1'], 2) ?> · <?= number_format($load['5'], 2) ?> · <?= number_format($load['15'], 2) ?></div>
    <div style="font-size: 0.8rem; margin-top: 0.5rem;" class="muted"><?= __('نسبةً لعدد الكورات الكلي') ?></div>
  </div>

  <div class="flow-card" style="height: auto; min-height: 0; align-self: start;">
    <div class="flow-card-header">
      <span class="flow-card-title"><?= __('الذاكرة (RAM)') ?></span>
      <span class="tag <?= $barClass($memPct) ?>" id="srv-mem-badge"><?= number_format($memPct, 0) ?>%</span>
    </div>
    <div class="flow-card-value" id="srv-mem-val" style="font-size: 1.45rem;"><?= bytes_html($memory['used']) ?> <small class="muted">/ <?= bytes_html($memory['total']) ?></small></div>
    <div style="background: rgba(255, 255, 255, 0.1); border-radius: 999px; height: 8px; overflow: hidden; margin-top: 0.75rem;">
      <div id="srv-mem-bar" style="width: <?= min(100, $memPct) ?>%; height: 100%; background: var(--accent-gradient); border-radius: 999px; transition: width 0.3s ease;"></div>
    </div>
  </div>

  <div class="flow-card" style="height: auto; min-height: 0; align-self: start;">
    <div class="flow-card-header">
      <span class="flow-card-title">Swap Storage</span>
      <span class="tag <?= $barClass($swapPct) ?>" id="srv-swap-badge"><?= number_format($swapPct, 0) ?>%</span>
    </div>
    <div class="flow-card-value" id="srv-swap-val" style="font-size: 1.45rem;"><?= bytes_html($memory['swap_used']) ?> <small class="muted">/ <?= bytes_html($memory['swap_total']) ?></small></div>
    <div style="background: rgba(255, 255, 255, 0.1); border-radius: 999px; height: 8px; overflow: hidden; margin-top: 0.75rem;">
      <div id="srv-swap-bar" style="width: <?= min(100, $swapPct) ?>%; height: 100%; background: var(--accent-gradient); border-radius: 999px; transition: width 0.3s ease;"></div>
    </div>
  </div>
</div>

<section style="margin-top: 2rem;">
  <div style="display: flex; align-items: center; justify-content: flex-end; margin-bottom: 1rem;">
    <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text);"><?= __('استهلاك كل كور') ?></h2>
  </div>
  <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
    <?php foreach ($cores as $i => $pct): ?>
      <div class="flow-card" style="padding: 0.65rem 1rem; width: 160px; height: auto; min-height: 0;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem;">
          <span class="mono muted" style="font-size: 0.8rem;">Core #<?= (int)$i ?></span>
          <span class="mono ltr" id="srv-core-val-<?= (int)$i ?>" style="font-weight: 700; font-size: 0.88rem; color: var(--text);"><?= number_format((float)$pct, 0) ?>%</span>
        </div>
        <div style="background: rgba(255, 255, 255, 0.1); border-radius: 999px; height: 6px; overflow: hidden;">
          <div id="srv-core-bar-<?= (int)$i ?>" style="width: <?= min(100, (float)$pct) ?>%; height: 100%; background: var(--accent-gradient); border-radius: 999px; transition: width 0.3s ease;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section style="margin-top: 2.5rem;">
  <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem; color: var(--text);">💾 <?= __('مساحة السيرفر (البارتشنات)') ?></h2>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= __('نقطة الأرشيف') ?></th>
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
            <td class="mono ltr"><strong style="color: var(--text);"><?= e($disk['mount']) ?></strong></td>
            <td class="mono ltr"><?= e($disk['source']) ?></td>
            <td class="mono ltr"><?= e($disk['fstype']) ?></td>
            <td class="col-num mono"><?= bytes_html($disk['size']) ?></td>
            <td class="col-num mono"><?= bytes_html($disk['used']) ?></td>
            <td class="col-num mono"><strong style="color: var(--text);"><?= bytes_html($disk['avail']) ?></strong></td>
            <td style="min-width: 160px;">
              <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div style="flex: 1; background: rgba(255, 255, 255, 0.1); border-radius: 999px; height: 8px; overflow: hidden;">
                  <div style="width: <?= min(100, $disk['pcent']) ?>%; height: 100%; background: var(--accent-gradient); border-radius: 999px;"></div>
                </div>
                <span class="mono ltr small" style="font-weight: 700; color: var(--text);"><?= $disk['pcent'] ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section style="margin-top: 2.5rem;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
    <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text);">⚙️ <?= __('أعلى العمليات النشطة') ?></h2>
    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
      <input type="text" id="proc-search-input" class="form-control form-control-sm mono ltr" placeholder="🔍 <?= __('فلترة العمليات (اسم، PID، مستخدم)...') ?>" style="width: 250px; font-size: 0.82rem;">
      <div style="display: flex; gap: 0.35rem;">
        <a class="btn btn-sm <?= $psort === 'cpu' ? 'btn-primary' : 'btn-ghost' ?>" href="?page=server&amp;psort=cpu<?= $auto ? '&amp;auto=1' : '' ?>">CPU</a>
        <a class="btn btn-sm <?= $psort === 'mem' ? 'btn-primary' : 'btn-ghost' ?>" href="?page=server&amp;psort=mem<?= $auto ? '&amp;auto=1' : '' ?>"><?= __('الذاكرة') ?></a>
      </div>
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
      <tbody id="srv-procs-tbody">
        <?php foreach ($procs as $proc): ?>
          <tr>
            <td class="col-num mono"><?= (int)$proc['pid'] ?></td>
            <td class="mono ltr"><?= e($proc['user']) ?></td>
            <td class="col-num mono"><strong style="color: var(--text);"><?= number_format($proc['cpu'], 1) ?></strong></td>
            <td class="col-num mono"><?= number_format($proc['mem'], 1) ?></td>
            <td class="col-num mono"><?= bytes_html($proc['rss']) ?></td>
            <td class="mono ltr"><?= e($proc['stat']) ?></td>
            <td class="mono ltr"><?= e($proc['etime']) ?></td>
            <td style="max-width: 380px;">
              <div class="cmd-wrapper" data-pid="<?= (int)$proc['pid'] ?>">
                <code class="cmd-text"><?= e($proc['cmd']) ?></code>
                <?php if (mb_strlen($proc['cmd']) > 35): ?>
                  <button type="button" class="btn-expand-cmd" title="<?= e(__('عرض/إخفاء الأمر كاملًا')) ?>">▼</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($auto): ?>
  <script>setTimeout(function () { location.reload(); }, 5000);</script>
<?php endif; ?>

<?php layout_end(__('السيرفر والموارد'), $flashes); ?>
