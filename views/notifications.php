<?php
/** @var array $config @var array $flashes */
$config = $config ?? config_load();
$tab    = (string)($_GET['tab'] ?? 'email');
if (!in_array($tab, ['email', 'mattermost', 'telegram'], true)) {
    $tab = 'email';
}
layout_start();
?>

<div class="page-head">
  <div>
    <h1 class="gradient-title"><?= __('التنبيهات والإشعارات') ?></h1>
    <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;"><?= __('إدارة قنوات التنبيه التلقائية والربط مع البريد، ماترموست، وتليجرام.') ?></p>
  </div>
  <a class="btn btn-primary" href="?page=dashboard">← <?= __('اللوحة الرئيسية') ?></a>
</div>

<!-- Notification Channels Tab Bar -->
<nav class="src-tabs" style="display: flex; gap: 0.6rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
  <a class="btn <?= $tab === 'email' ? 'btn-primary is-active-tab' : '' ?>" href="?page=notifications&amp;tab=email">
    <span>📧</span> <span style="color: #ffffff; font-weight: 700;"><?= __('البريد الإلكتروني (Email)') ?></span>
  </a>
  <a class="btn <?= $tab === 'mattermost' ? 'btn-primary is-active-tab' : '' ?>" href="?page=notifications&amp;tab=mattermost">
    <span>💬</span> <span style="color: #ffffff; font-weight: 700;"><?= __('ماترموست (Mattermost)') ?></span>
  </a>
  <a class="btn <?= $tab === 'telegram' ? 'btn-primary is-active-tab' : '' ?>" href="?page=notifications&amp;tab=telegram">
    <span>🤖</span> <span style="color: #ffffff; font-weight: 700;"><?= __('تليجرام (Telegram)') ?></span>
  </a>
</nav>

<!-- Tab 1: Email & SMTP Settings -->
<?php if ($tab === 'email'): ?>
  <form class="saas-card" method="post" action="?page=notifications&amp;tab=email" style="margin-bottom: 2rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="channel_tab" value="email">

    <div style="margin-bottom: 1.5rem;">
      <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
        <span>📧</span> <?= __('إعدادات تنبيهات البريد الإلكتروني (Email & SMTP)') ?>
      </h3>
      <p class="muted small" style="margin-bottom: 1.25rem;">
        <?= __('قم بضبط بيانات خادم الـ SMTP لإرسال تنبيهات بريدية فورية بـ هويّة النظام عند رصد أي أخطاء جديدة في اللوجات.') ?>
      </p>

      <div style="margin-bottom: 1.25rem; background: var(--surface-2); padding: 0.85rem 1.1rem; border-radius: var(--radius); border: 1px solid var(--border);">
        <label style="display: flex; align-items: center; gap: 0.65rem; font-weight: 700; cursor: pointer; color: var(--text);">
          <input type="checkbox" name="email_enabled" value="1" <?= !empty($config['email_enabled']) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
          <span><?= __('تفعيل التنبيهات البريدية التلقائية (Enable Email Alerts)') ?></span>
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
          <p class="muted small" style="margin-top: 0.3rem;"><?= __('يتم حظر تكرار نفس التنبيه خلال هذه الفترة لتفادي إغراق البريد.') ?></p>
        </div>
      </div>

      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary" type="submit">💾 <?= __('حفظ إعدادات البريد') ?></button>
        <button class="btn btn-ghost" type="button" id="btn-test-email" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700;">🧪 <?= __('إرسال إيميل تجريبي') ?></button>
        <div id="test-email-status" style="font-size: 0.88rem; font-weight: 700;"></div>
      </div>
    </div>
  </form>
<?php endif; ?>

<!-- Tab 2: Mattermost Settings -->
<?php if ($tab === 'mattermost'): ?>
  <form class="saas-card" method="post" action="?page=notifications&amp;tab=mattermost" style="margin-bottom: 2rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="channel_tab" value="mattermost">

    <div style="margin-bottom: 1.5rem;">
      <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
        <span>💬</span> <?= __('إعدادات تنبيهات ماترموست (Mattermost Webhook Integration)') ?>
      </h3>
      <p class="muted small" style="margin-bottom: 1.25rem;">
        <?= __('ربط إشعارات الأخطاء مباشرة مع سيرفر قنوات Mattermost الخاصة بفريق العمل عبر الـ Incoming Webhook.') ?>
      </p>

      <div style="margin-bottom: 1.25rem; background: var(--surface-2); padding: 0.85rem 1.1rem; border-radius: var(--radius); border: 1px solid var(--border);">
        <label style="display: flex; align-items: center; gap: 0.65rem; font-weight: 700; cursor: pointer; color: var(--text);">
          <input type="checkbox" name="mattermost_enabled" value="1" <?= !empty($config['mattermost_enabled']) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
          <span><?= __('تفعيل تنبيهات ماترموست (Enable Mattermost Alerts)') ?></span>
        </label>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('رابط الـ Webhook (Incoming Webhook URL)') ?></label>
        <input type="url" name="mattermost_webhook_url" value="<?= e($config['mattermost_webhook_url'] ?? '') ?>" class="mono ltr" placeholder="https://mattermost.example.com/hooks/xxx-xxx-xxx" dir="ltr" style="width: 100%;">
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('اسم القناة (Channel Name - اختيارية)') ?></label>
          <input type="text" name="mattermost_channel" value="<?= e($config['mattermost_channel'] ?? '') ?>" class="mono ltr" placeholder="system-alerts" dir="ltr" style="width: 100%;">
        </div>
        <div>
          <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('اسم البوت (Bot Username)') ?></label>
          <input type="text" name="mattermost_username" value="<?= e($config['mattermost_username'] ?? 'LogFlow Alert') ?>" class="mono ltr" placeholder="LogFlow Alert" dir="ltr" style="width: 100%;">
        </div>
      </div>

      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary" type="submit">💾 <?= __('حفظ إعدادات ماترموست') ?></button>
        <button class="btn btn-ghost" type="button" id="btn-test-mattermost" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700;">🧪 <?= __('إرسال تنبيه تجريبي لـ Mattermost') ?></button>
        <div id="test-mattermost-status" style="font-size: 0.88rem; font-weight: 700;"></div>
      </div>
    </div>
  </form>
<?php endif; ?>

<!-- Tab 3: Telegram Settings -->
<?php if ($tab === 'telegram'): ?>
  <form class="saas-card" method="post" action="?page=notifications&amp;tab=telegram" style="margin-bottom: 2rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="channel_tab" value="telegram">

    <div style="margin-bottom: 1.5rem;">
      <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.5rem;">
        <span>🤖</span> <?= __('إعدادات تنبيهات تليجرام (Telegram Bot Integration)') ?>
      </h3>
      <p class="muted small" style="margin-bottom: 1.25rem;">
        <?= __('توصيل إشعارات الأخطاء الحية فورياً إلى حسابك أو مجموعتك على Telegram عبر Bot API.') ?>
      </p>

      <div style="margin-bottom: 1.25rem; background: var(--surface-2); padding: 0.85rem 1.1rem; border-radius: var(--radius); border: 1px solid var(--border);">
        <label style="display: flex; align-items: center; gap: 0.65rem; font-weight: 700; cursor: pointer; color: var(--text);">
          <input type="checkbox" name="telegram_enabled" value="1" <?= !empty($config['telegram_enabled']) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
          <span><?= __('تفعيل تنبيهات تليجرام (Enable Telegram Alerts)') ?></span>
        </label>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('توكن البوت (Bot Token)') ?></label>
          <input type="text" name="telegram_bot_token" value="<?= e($config['telegram_bot_token'] ?? '') ?>" class="mono ltr" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" dir="ltr" style="width: 100%;">
        </div>
        <div>
          <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text);"><?= __('معرف المحادثة أو القناة (Chat ID)') ?></label>
          <input type="text" name="telegram_chat_id" value="<?= e($config['telegram_chat_id'] ?? '') ?>" class="mono ltr" placeholder="-100123456789" dir="ltr" style="width: 100%;">
        </div>
      </div>

      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <button class="btn btn-primary" type="submit">💾 <?= __('حفظ إعدادات تليجرام') ?></button>
        <button class="btn btn-ghost" type="button" id="btn-test-telegram" style="border: 1px solid var(--accent); color: var(--accent); font-weight: 700;">🧪 <?= __('إرسال تنبيه تجريبي لـ Telegram') ?></button>
        <div id="test-telegram-status" style="font-size: 0.88rem; font-weight: 700;"></div>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php layout_end(__('التنبيهات والإشعارات'), $flashes); ?>
