<?php
/** @var string $rel @var int $size @var int $mb @var string $key @var array $detail @var string $from @var string $to @var ?int $rangeCount */
$tag = ['error' => 'tag-error', 'warn' => 'tag-warn', 'info' => ''][$detail['level']] ?? '';
$maxDay = $detail['days'] ? max($detail['days']) : 0;
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="trunc-title">🔎 <?= __('تفاصيل رسالة متكررة') ?></h1>
    <p class="muted mono ltr"><?= e($rel) ?> · <?= bytes_html($size) ?></p>
  </div>
  <div class="head-actions">
    <?php if (empty($full) && !empty($detail['partial'])): ?>
      <a class="btn btn-primary" href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($key) ?>&amp;full=1<?= e(src_qs()) ?>">🔬 <?= __('تحليل الملف بالكامل') ?></a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="?page=analyze&amp;file=<?= urlencode($rel) ?><?= !empty($full) ? '&amp;full=1' : '&amp;mb=' . (int)$mb ?><?= e(src_qs()) ?>">📊 <?= __('رجوع للتحليل') ?></a>
    <a class="btn btn-ghost" href="?page=view&amp;file=<?= urlencode($rel) ?>&amp;find=<?= urlencode(mb_strcut($detail['first_line'], 0, 80)) ?><?= e(src_qs()) ?>">📄 <?= __('عرض اللوج') ?></a>
  </div>
</div>

<?php if (!$detail['found']): ?>
  <div class="empty">
    <div class="empty-mark">🤷</div>
    <h2><?= __('الرسالة غير موجودة في النافذة المفحوصة') ?></h2>
    <p class="muted"><?= __('جرّب توسيع النافذة — الرسالة قد تكون في جزء أقدم من الملف.') ?></p>
  </div>
<?php else: ?>

  <?php if (!empty($full)): ?>
    <div class="alert alert-success">
      ✅ <?= __('نتيجة تحليل الملف بالكامل') ?> — <?= __('اكتمل في') ?>
      <span class="mono ltr"><?= e(date('Y-m-d H:i', strtotime((string)($job['finished_at'] ?? 'now')))) ?></span>
      (<?= bytes_html((int)($job['total_bytes'] ?? 0)) ?>).
      <a class="chip-day" href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($key) ?>&amp;full=1&amp;restart=1<?= e(src_qs()) ?>">🔄 <?= __('إعادة التحليل') ?></a>
    </div>
  <?php elseif ($detail['partial']): ?>
    <div class="alert alert-warn">
      <?= __('الملف كبير') ?> (<?= bytes_html($size) ?>) — <?= __('تم تحليل') ?> <strong><?= __('آخر') ?> <?= bytes_html($detail['scanned']) ?></strong> <?= __('فقط (الأحدث). كل الفحص بيتم على السيرفر — لا يتم تحميل الملف عندك.') ?>
      <span class="quick-days" style="display:inline-flex">
        <a class="chip-day" href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($key) ?>&amp;full=1<?= e(src_qs()) ?>">🔬 <?= __('تحليل الملف بالكامل (في الخلفية)') ?></a>
        <?= __('أو وسّع النافذة السريعة:') ?>
        <?php foreach ([50, 200, 500, 1000] as $preset): ?>
          <a class="chip-day <?= $mb === $preset ? 'is-active' : '' ?>"
             href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($key) ?>&amp;mb=<?= $preset ?><?= e(src_qs()) ?>"><?= $preset ?> MB</a>
        <?php endforeach; ?>
      </span>
    </div>
  <?php endif; ?>

  <div class="cards">
    <div class="card">
      <div class="card-label"><?= __('التكرار الكلي') ?></div>
      <div class="card-value"><?= number_format($detail['count']) ?></div>
      <div class="card-meta"><?= __('من أصل') ?> <?= number_format($detail['total']) ?> <?= __('سطر') ?>
        (<?= $detail['total'] ? number_format($detail['count'] * 100 / $detail['total'], 1) : 0 ?>%)</div>
    </div>
    <div class="card">
      <div class="card-label"><?= __('أول ظهور') ?></div>
      <div class="card-value ltr"><?= $detail['first'] ? e($detail['first']) : '—' ?></div>
    </div>
    <div class="card">
      <div class="card-label"><?= __('آخر ظهور') ?></div>
      <div class="card-value ltr"><?= $detail['last'] ? e($detail['last']) : '—' ?></div>
    </div>
    <div class="card">
      <div class="card-label"><?= __('النوع') ?></div>
      <div class="card-value"><span class="tag <?= $tag ?>"><?= __(['error' => 'خطأ', 'warn' => 'تحذير', 'info' => 'عادي'][$detail['level']]) ?></span></div>
      <?php if ($detail['undated']): ?>
        <div class="card-meta"><?= number_format($detail['undated']) ?> <?= __('ظهور بدون تاريخ') ?></div>
      <?php endif; ?>
    </div>
  </div>

  <section class="section">
    <h2 class="section-title"><?= __('السطر كامل (أول ظهور)') ?></h2>
    <pre class="full-line ltr"><?= e($detail['first_line']) ?></pre>
    <?php if ($detail['last_line'] !== '' && $detail['last_line'] !== $detail['first_line']): ?>
      <h2 class="section-title"><?= __('آخر ظهور (قد تختلف التفاصيل المتغيرة)') ?></h2>
      <pre class="full-line ltr"><?= e($detail['last_line']) ?></pre>
    <?php endif; ?>
  </section>

  <section class="section">
    <h2 class="section-title">📅 <?= __('كام مرة في فترة معينة؟') ?></h2>
    <form class="toolbar" method="get" action="">
      <input type="hidden" name="page" value="repeat">
      <input type="hidden" name="file" value="<?= e($rel) ?>">
      <input type="hidden" name="k" value="<?= e($key) ?>">
      <?php if (!empty($full)): ?><input type="hidden" name="full" value="1"><?php else: ?><input type="hidden" name="mb" value="<?= (int)$mb ?>"><?php endif; ?>
      <?php if (log_active_source()): ?><input type="hidden" name="src" value="<?= e(log_active_source()['name']) ?>"><?php endif; ?>
      <label class="toolbar-field"><span><?= __('من') ?></span><input type="date" name="from" value="<?= e($from) ?>"></label>
      <label class="toolbar-field"><span><?= __('إلى') ?></span><input type="date" name="to" value="<?= e($to) ?>"></label>
      <button class="btn btn-primary" type="submit"><?= __('احسب') ?></button>
      <?php if ($rangeCount !== null): ?>
        <a class="btn btn-ghost" href="?page=repeat&amp;file=<?= urlencode($rel) ?>&amp;k=<?= e($key) ?><?= !empty($full) ? '&amp;full=1' : '&amp;mb=' . (int)$mb ?><?= e(src_qs()) ?>"><?= __('مسح') ?></a>
      <?php endif; ?>
    </form>

    <?php if ($rangeCount !== null): ?>
      <div class="alert alert-success">
        <?= __('في الفترة') ?>
        <strong class="ltr"><?= $from !== '' ? e($from) : '…' ?> ← <?= $to !== '' ? e($to) : '…' ?></strong>:
        <strong><?= number_format($rangeCount) ?></strong> <?= __('مرة') ?>
        <?= $detail['count'] ? '(' . number_format($rangeCount * 100 / $detail['count'], 1) . '% ' . __('من إجمالي التكرار') . ')' : '' ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($detail['days']): ?>
    <section class="section">
      <h2 class="section-title"><?= __('التوزيع اليومي') ?></h2>
      <div class="table-wrap table-preview">
        <table class="table">
          <thead>
            <tr><th class="col-date"><?= __('اليوم') ?></th><th class="col-num"><?= __('التكرار') ?></th><th><?= __('النسبة') ?></th></tr>
          </thead>
          <tbody>
            <?php foreach ($detail['days'] as $day => $n): ?>
              <?php $inRange = $rangeCount !== null && ($from === '' || $day >= $from) && ($to === '' || $day <= $to); ?>
              <tr class="<?= $rangeCount !== null && !$inRange ? 'row-blocked' : '' ?>">
                <td class="col-date mono ltr"><?= e($day) ?></td>
                <td class="col-num mono"><strong><?= number_format($n) ?></strong></td>
                <td><div class="day-bar"><span style="width:<?= $maxDay ? max(1, (int)round($n * 100 / $maxDay)) : 0 ?>%"></span></div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php layout_end('تفاصيل رسالة متكررة', $flashes); ?>
