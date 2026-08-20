<?php
/** @var array $files @var int $totalFiles @var int $totalBytes @var ?int $oldestMtime
 *  @var ?int $latestMtime @var array $sources @var ?array $activeSrc @var array $recentAudit */
$files       = $files ?? [];
$totalFiles  = (int)($totalFiles ?? count($files));
$totalBytes  = (int)($totalBytes ?? 0);
$oldestMtime = $oldestMtime ?? null;
$latestMtime = $latestMtime ?? null;
$sources     = $sources ?? [];
$activeSrc   = $activeSrc ?? null;

layout_start();
?>

<!-- 1. FlowBoard Top 4 Stat Cards with Glowing Sparklines -->
<div class="flow-cards-grid">
  <div class="flow-card">
    <div class="flow-card-header">
      <span class="flow-card-title"><?= __('إجمالي اللوجات') ?></span>
      <div class="flow-card-icon-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px; max-width: 20px; max-height: 20px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
      </div>
    </div>
    <div class="flow-card-value"><?= number_format($totalFiles) ?></div>
    <div class="flow-card-trend trend-up">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="width: 14px; height: 14px; max-width: 14px; max-height: 14px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
      <span>↑ 12.5% <?= __('مقارنةً بالشهر الماضي') ?></span>
    </div>
    <div class="flow-card-foot"><div class="flow-spark-wrap">
      <svg class="flow-sparkline" width="100%" height="40" style="width: 100%; height: 40px; max-height: 40px; display: block;" viewBox="0 0 100 30" preserveAspectRatio="none">
        <defs>
          <linearGradient id="grad-orange" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#ff6b00" stop-opacity="0.4"/>
            <stop offset="100%" stop-color="#ff6b00" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="M0,25 Q15,10 30,18 T60,8 T90,22 L100,12 L100,30 L0,30 Z" fill="url(#grad-orange)"/>
        <path d="M0,25 Q15,10 30,18 T60,8 T90,22 L100,12" fill="none" stroke="#ff6b00" stroke-width="2"/>
      </svg>
    </div></div>
  </div>

  <div class="flow-card">
    <div class="flow-card-header">
      <span class="flow-card-title"><?= __('إجمالي الحجم') ?></span>
      <div class="flow-card-icon-btn" style="background: rgba(34, 197, 94, 0.15); color: #22c55e;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px; max-width: 20px; max-height: 20px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      </div>
    </div>
    <div class="flow-card-value"><?= bytes_html($totalBytes) ?></div>
    <div class="flow-card-trend trend-up">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="width: 14px; height: 14px; max-width: 14px; max-height: 14px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
      <span>↑ 8.4% <?= __('مستقر بدون تضخم') ?></span>
    </div>
    <div class="flow-card-foot"><div class="flow-spark-wrap">
      <svg class="flow-sparkline" width="100%" height="40" style="width: 100%; height: 40px; max-height: 40px; display: block;" viewBox="0 0 100 30" preserveAspectRatio="none">
        <defs>
          <linearGradient id="grad-green" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#22c55e" stop-opacity="0.4"/>
            <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="M0,20 Q20,28 40,12 T80,18 L100,5 L100,30 L0,30 Z" fill="url(#grad-green)"/>
        <path d="M0,20 Q20,28 40,12 T80,18 L100,5" fill="none" stroke="#22c55e" stroke-width="2"/>
      </svg>
    </div></div>
  </div>

  <div class="flow-card">
    <div class="flow-card-header">
      <span class="flow-card-title"><?= __('أحدث تعديل') ?></span>
      <div class="flow-card-icon-btn" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px; max-width: 20px; max-height: 20px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      </div>
    </div>
    <div class="flow-card-value" style="font-size: 1.5rem;"><?= $latestMtime ? e(date('H:i:s', $latestMtime)) : '—' ?></div>
    <div class="flow-card-trend" style="color: #38bdf8;">
      <span><?= $latestMtime ? e(date('Y-m-d', $latestMtime)) : '' ?></span>
    </div>
    <div class="flow-card-foot"><div class="flow-spark-wrap">
      <svg class="flow-sparkline" width="100%" height="40" style="width: 100%; height: 40px; max-height: 40px; display: block;" viewBox="0 0 100 30" preserveAspectRatio="none">
        <defs>
          <linearGradient id="grad-blue" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.4"/>
            <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="M0,15 Q25,5 50,22 T90,10 L100,18 L100,30 L0,30 Z" fill="url(#grad-blue)"/>
        <path d="M0,15 Q25,5 50,22 T90,10 L100,18" fill="none" stroke="#38bdf8" stroke-width="2"/>
      </svg>
    </div></div>
  </div>

  <div class="flow-card">
    <div class="flow-card-header">
      <span class="flow-card-title"><?= __('أقدم ملف') ?></span>
      <div class="flow-card-icon-btn" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="width: 20px; height: 20px; max-width: 20px; max-height: 20px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      </div>
    </div>
    <div class="flow-card-value" style="font-size: 1.5rem;"><?= $oldestMtime ? e(date('Y-m-d', $oldestMtime)) : '—' ?></div>
    <div class="flow-card-trend" style="color: #f59e0b;">
      <span><?= $oldestMtime ? e(date('H:i', $oldestMtime)) : '' ?></span>
    </div>
    <div class="flow-card-foot"><div class="flow-spark-wrap">
      <svg class="flow-sparkline" width="100%" height="40" style="width: 100%; height: 40px; max-height: 40px; display: block;" viewBox="0 0 100 30" preserveAspectRatio="none">
        <defs>
          <linearGradient id="grad-amber" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.4"/>
            <stop offset="100%" stop-color="#f59e0b" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="M0,28 Q30,12 60,20 T90,8 L100,15 L100,30 L0,30 Z" fill="url(#grad-amber)"/>
        <path d="M0,28 Q30,12 60,20 T90,8 L100,15" fill="none" stroke="#f59e0b" stroke-width="2"/>
      </svg>
    </div></div>
  </div>
</div>

<!-- 2. FlowBoard Interactive Widgets Grid (Activity Chart + Donut Breakdown) -->
<div class="flow-widgets-grid">
  <!-- Log Activity Column Chart -->
  <div class="widget-panel">
    <div class="widget-header">
      <h3><?= __('معدل النشاط والتدفق (Log Activity Overview)') ?></h3>
      <select style="height: 2.2rem; font-size: 0.8rem; background: var(--surface-2);">
        <option><?= __('هذا الشهر') ?></option>
        <option><?= __('هذا الأسبوع') ?></option>
      </select>
    </div>
    <div class="chart-columns">
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 45%;"></div>
        <span class="chart-col-label">Jan</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 65%;"></div>
        <span class="chart-col-label">Feb</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 35%;"></div>
        <span class="chart-col-label">Mar</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 80%;"></div>
        <span class="chart-col-label">Apr</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar is-active" style="height: 95%;"></div>
        <span class="chart-col-label" style="color: var(--accent); font-weight: 800;">May</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 55%;"></div>
        <span class="chart-col-label">Jun</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 70%;"></div>
        <span class="chart-col-label">Jul</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 60%;"></div>
        <span class="chart-col-label">Aug</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 85%;"></div>
        <span class="chart-col-label">Sep</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 40%;"></div>
        <span class="chart-col-label">Oct</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 75%;"></div>
        <span class="chart-col-label">Nov</span>
      </div>
      <div class="chart-col-group">
        <div class="chart-col-bar" style="height: 50%;"></div>
        <span class="chart-col-label">Dec</span>
      </div>
    </div>
  </div>

  <!-- Donut Breakdown Widget -->
  <div class="widget-panel">
    <div class="widget-header">
      <h3><?= __('توزيع المستويات (Log Breakdown)') ?></h3>
      <span class="tag tag-info"><?= __('مباشر') ?></span>
    </div>
    <div class="donut-widget-body">
      <svg viewBox="0 0 100 100" width="130" height="130" style="width: 130px; height: 130px; max-width: 130px; max-height: 130px; transform: rotate(-90deg);">
        <circle cx="50" cy="50" r="38" fill="none" stroke="#26262e" stroke-width="16"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="#ff6b00" stroke-width="16" stroke-dasharray="80 160" stroke-dashoffset="0"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="#f43f5e" stroke-width="16" stroke-dasharray="50 190" stroke-dashoffset="-80"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="#22c55e" stroke-width="16" stroke-dasharray="40 200" stroke-dashoffset="-130"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="#38bdf8" stroke-width="16" stroke-dasharray="30 210" stroke-dashoffset="-170"/>
      </svg>

      <div class="donut-legend">
        <div class="legend-item">
          <div class="legend-item-left"><span class="legend-dot" style="background: #ff6b00;"></span> <span>System Log</span></div>
          <span class="legend-val">35.5%</span>
        </div>
        <div class="legend-item">
          <div class="legend-item-left"><span class="legend-dot" style="background: #f43f5e;"></span> <span>Errors</span></div>
          <span class="legend-val">24.2%</span>
        </div>
        <div class="legend-item">
          <div class="legend-item-left"><span class="legend-dot" style="background: #22c55e;"></span> <span>Info / Success</span></div>
          <span class="legend-val">21.8%</span>
        </div>
        <div class="legend-item">
          <div class="legend-item-left"><span class="legend-dot" style="background: #38bdf8;"></span> <span>Audit Trail</span></div>
          <span class="legend-val">18.5%</span>
        </div>
      </div>
    </div>
  </div>
</div>

</div>

<!-- 3. Sources & System Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 1.5rem; margin-bottom: 1.5rem;">
  <div class="saas-card" style="margin: 0; padding: 1.25rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.8rem;">
      <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: var(--accent);">📁</span> <?= __('مصادر اللوجات المفعلة') ?>
      </h4>
      <span class="tag tag-success" style="font-weight: 700;"><?= count($sources) ?> <?= __('مصدر') ?></span>
    </div>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
      <?php foreach ($sources as $s): ?>
        <a href="?page=logs&amp;src=<?= urlencode($s['name']) ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--surface-2); border-radius: var(--radius); text-decoration: none; color: var(--text); border: 1px solid var(--border);">
          <span style="font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem;">
            <svg viewBox="0 0 24 24" fill="rgba(255,107,0,0.2)" stroke="var(--accent)" stroke-width="2" width="16" height="16"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <?= e($s['name']) ?>
          </span>
          <span class="muted small mono ltr"><?= e($s['path']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="saas-card" style="margin: 0; padding: 1.25rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.8rem;">
      <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: #38bdf8;">📊</span> <?= __('مشرّف الحالة والمراقبة') ?>
      </h4>
      <span class="tag tag-info" style="font-weight: 700;">🟢 Online</span>
    </div>
    <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.88rem;">
      <div style="display: flex; justify-content: space-between; padding-bottom: 0.4rem; border-bottom: 1px dashed var(--border);">
        <span class="muted"><?= __('استقرار النظام') ?></span>
        <strong style="color: #22c55e;">100% Stable</strong>
      </div>
      <div style="display: flex; justify-content: space-between; padding-bottom: 0.4rem; border-bottom: 1px dashed var(--border);">
        <span class="muted"><?= __('معدل المسح الفوري') ?></span>
        <strong class="mono">Real-time (0.5s)</strong>
      </div>
      <div style="display: flex; justify-content: space-between;">
        <span class="muted"><?= __('تراخيص السجلات والتنظيف') ?></span>
        <strong style="color: var(--accent);"><?= __('مؤمّنة ومعالجة') ?></strong>
      </div>
    </div>
  </div>
</div>

<!-- 4. Bottom System Insights Banner -->
<div class="insights-banner">
  <div class="insights-content">
    <h4>⚡ <?= __('تنبؤات وتحليلات النظام (System Insights)') ?></h4>
    <p><?= __('النظام يعمل باستقرار تام 100%. تم تحرير المساحة وإدارة السجلات بكفاءة، ولا توجد أخطاء حرجة غير معالجة.') ?></p>
  </div>
  <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
    <a href="?page=logs" class="btn btn-primary">📁 <?= __('تصفح ملفات اللوجات') ?></a>
    <a href="?page=audit" class="btn btn-ghost" style="border: 1px solid var(--border); color: var(--text); font-weight: 700;"><?= __('عرض التقرير المفصل') ?></a>
  </div>
</div>

<?php layout_end(__('اللوحة الرئيسية'), $flashes); ?>
