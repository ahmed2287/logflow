<?php
/** @var array $config @var array $flashes */
$config = $config ?? config_load();
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('التنبيهات والإشعارات') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= __('إدارة قنوات التنبيه التلقائية عند اكتشاف أي أخطاء في اللوجات.') ?></p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('اللوحة الرئيسية') ?></a>
</div>

<form class="saas-card" method="post" action="?page=notifications" style="margin-bottom: 2rem;">
  <?= csrf_field() ?>

  <div style="margin-bottom: 1.5rem;">
    <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
      <span>📧</span> <?= __('تنبيهات البريد الإلكتروني (Email & SMTP Notifications)') ?>
    </h3>
    <p class="muted small" style="margin-bottom: 1.25rem;">
      <?= __('قم بضبط بيانات خادم الـ SMTP لإرسال تنبيهات بريدية فورية بـ هويّة النظام عند رصد أي أخطاء جديدة في اللوجات.') ?>
    </p>

    <div style="margin-bottom: 1.25rem; background: var(--surface-2); padding: 0.85rem 1.1rem; border-radius: var(--radius); border: 1px solid var(--border);">
      <label style="display: flex; align-items: center; gap: 0.65rem; font-weight: 700; cursor: pointer; color: var(--text);">
        <input type="checkbox" name="email_enabled" value="1" <?= !empty($config['email_enabled']) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
        <span><?= __('تفعيل التنبيهات البريدية التلقائية عند حدوث أخطاء (Enable Email Notifications)') ?></span>
      </label>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('خادم الـ SMTP (Host)') ?></label>
        <input type="text" name="smtp_host" value="<?= e($config['smtp_host'] ?? 'smtp.gmail.com') ?>" class="mono ltr" placeholder="smtp.gmail.com" dir="ltr" style="width: 100%;">
      </div>
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('المنفذ (Port)') ?></label>
        <input type="number" name="smtp_port" value="<?= (int)($config['smtp_port'] ?? 587) ?>" dir="ltr" style="width: 100%;">
      </div>
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('التشفير (Crypto)') ?></label>
        <select name="smtp_crypto" style="width: 100%; height: 40px; border-radius: var(--radius); font-size: 0.88rem;">
          <option value="tls" <?= ($config['smtp_crypto'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Port 587 - Recommended)</option>
          <option value="ssl" <?= ($config['smtp_crypto'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
          <option value="none" <?= ($config['smtp_crypto'] ?? '') === 'none' ? 'selected' : '' ?>>None (Port 25)</option>
        </select>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('إيميل المرسل (SMTP User)') ?></label>
        <input type="email" name="smtp_user" value="<?= e($config['smtp_user'] ?? '') ?>" class="mono ltr" placeholder="alerts@viber-solutions.com" dir="ltr" style="width: 100%;">
      </div>
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('كلمة المرور / App Password') ?></label>
        <input type="password" name="smtp_pass" value="<?= e($config['smtp_pass'] ?? '') ?>" class="mono ltr" placeholder="••••••••••••" dir="ltr" style="width: 100%;">
      </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('إيميل المستلم للتنبيهات (Recipient Email)') ?></label>
        <input type="email" name="alert_recipient" value="<?= e($config['alert_recipient'] ?? '') ?>" class="mono ltr" placeholder="admin@viber-solutions.com" dir="ltr" style="width: 100%;">
      </div>
      <div>
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('مهلة منع التكرار (دقائق)') ?></label>
        <input type="number" name="cooldown_minutes" min="1" max="1440" value="<?= (int)($config['cooldown_minutes'] ?? 5) ?>" dir="ltr" style="width: 100%;">
        <p class="muted small" style="margin-top: 0.3rem;"><?= __('يتم حظر تكرار نفس التنبيه خلال هذه الفترة لتفادي إغراق صندوق الوارد.') ?></p>
      </div>
    </div>

    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <button class="btn btn-primary" type="submit">💾 <?= __('حفظ إعدادات التنبيهات') ?></button>
      <button class="btn btn-ghost" type="button" id="btn-test-email" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700;">🧪 <?= __('إرسال إيميل تجريبي') ?></button>
      <div id="test-email-status" style="font-size: 0.88rem; font-weight: 700;"></div>
    </div>
  </div>
</form>

<div class="saas-card" style="opacity: 0.75;">
  <h3 style="margin-top: 0; font-size: 1.05rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
    <span>📱</span> <?= __('قنوات التنبيه الفوري القادمة (Telegram & Webhooks)') ?>
    <span class="tag tag-info" style="font-size: 0.72rem;"><?= __('قريبًا Pro') ?></span>
  </h3>
  <p class="muted small" style="margin-bottom: 1rem;">
    <?= __('ربط التنبيهات مع بوت تيليجرام وويب هوك Slack / Discord للتنبيه المباشر في مجموعات العمل.') ?>
  </p>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
    <div style="padding: 1rem; background: var(--surface-2); border-radius: var(--radius); border: 1px dashed var(--border);">
      <strong>🤖 Telegram Bot Alerts</strong>
      <p class="muted small" style="margin: 0.3rem 0 0;"><?= __('إرسال تنبيهات لحظية إلى حسابك أو مجموعتك على تيليجرام.') ?></p>
    </div>
    <div style="padding: 1rem; background: var(--surface-2); border-radius: var(--radius); border: 1px dashed var(--border);">
      <strong>🔗 Webhooks (Slack / Discord)</strong>
      <p class="muted small" style="margin: 0.3rem 0 0;"><?= __('توجيه حمولة JSON مباشرة إلى سيرفراتك وقنوات فريق الدعم.') ?></p>
    </div>
  </div>
</div>

<?php layout_end(__('التنبيهات والإشعارات'), $flashes); ?>
