<?php
/** @var array $config @var array $flashes */
$config = $config ?? config_load();
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('الربط والتكاملات (Integrations)') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= __('ربط نظام اللوجات مع السيرفرات والتطبيقات الخارجية عبر Webhooks و REST APIs.') ?></p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('اللوحة الرئيسية') ?></a>
</div>

<form class="saas-card" method="post" action="?page=integrations" style="margin-bottom: 2rem;">
  <?= csrf_field() ?>

  <div style="margin-bottom: 1.5rem;">
    <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
      <span>🔗</span> <?= __('إعدادات الـ Webhook العام (Generic Webhook Payload Integration)') ?>
    </h3>
    <p class="muted small" style="margin-bottom: 1.25rem;">
      <?= __('قم بإدخال رابط الـ Endpoint لتمرير بيانات الأخطاء والحوادث فور حدوثها في صيغة JSON إلى تطبيقاتك وسيرفراتك الخارجيّة.') ?>
    </p>

    <div style="margin-bottom: 1.25rem; background: var(--surface-2); padding: 0.85rem 1.1rem; border-radius: var(--radius); border: 1px solid var(--border);">
      <label style="display: flex; align-items: center; gap: 0.65rem; font-weight: 700; cursor: pointer; color: var(--text);">
        <input type="checkbox" name="webhook_enabled" value="1" <?= !empty($config['webhook_enabled']) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
        <span><?= __('تفعيل الـ Webhook التلقائي للأحداث والأخطاء (Enable Webhook Integration)') ?></span>
      </label>
    </div>

    <div style="margin-bottom: 1.25rem;">
      <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('رابط نقطة النهاية (Webhook Endpoint URL)') ?></label>
      <input type="url" name="webhook_url" value="<?= e($config['webhook_url'] ?? '') ?>" class="mono ltr" placeholder="https://api.example.com/v1/webhooks/logflow-alerts" dir="ltr" style="width: 100%;">
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('صيغة الـ Payload (Format)') ?></label>
        <select name="webhook_format" style="width: 100%; height: 40px; border-radius: var(--radius); font-size: 0.88rem;">
          <option value="json" <?= ($config['webhook_format'] ?? 'json') === 'json' ? 'selected' : '' ?>>Standard JSON Payload</option>
          <option value="slack" <?= ($config['webhook_format'] ?? '') === 'slack' ? 'selected' : '' ?>>Slack / Discord Compatible</option>
        </select>
      </div>
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('مفتاح الأمان الهيدر (Secret Auth Token - اختياري)') ?></label>
        <input type="password" name="webhook_secret" value="<?= e($config['webhook_secret'] ?? '') ?>" class="mono ltr" placeholder="X-LogFlow-Secret-Key" dir="ltr" style="width: 100%;">
      </div>
    </div>

    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <button class="btn btn-primary" type="submit">💾 <?= __('حفظ إعدادات الـ Webhook') ?></button>
      <button class="btn btn-ghost" type="button" id="btn-test-webhook" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700;">🧪 <?= __('إرسال تجربة Webhook') ?></button>
      <div id="test-webhook-status" style="font-size: 0.88rem; font-weight: 700;"></div>
    </div>
  </div>
</form>

<div class="saas-card">
  <h3 style="margin-top: 0; font-size: 1.05rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
    <span>⚡</span> <?= __('مفاتيح الـ API للتكامل الخارجي (REST API Tokens)') ?>
  </h3>
  <p class="muted small" style="margin-bottom: 1.25rem;">
    <?= __('استخدام مفاتيح الـ API لجلب حالة النظام والسجلات عبر الاستعلامات البرمجية الخارجية.') ?>
  </p>
  <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
    <input type="text" readonly value="logflow_live_<?= md5('viber_solutions_' . (get_current_user() ?: 'admin')) ?>" class="mono ltr" style="flex: 1; min-width: 250px; background: var(--surface-2); font-weight: 700; color: var(--text);" dir="ltr">
    <button class="btn btn-ghost" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('<?= __('تم نسخ المفتاح بنجاح!') ?>');" style="border: 1px solid var(--border); font-weight: 700;">📋 <?= __('نسخ المفتاح') ?></button>
  </div>
</div>

<?php layout_end(__('الربط والتكاملات'), $flashes); ?>
