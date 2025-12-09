<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  settings
 * Settings page - Edit profile data
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Settings\HtmlView $this */

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatId = $this->tgUser['chat_id'] ?? $chat;
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';
$fullscreenPadding = isset($this->params) ? (int) $this->params->get('fullscreen_top_padding', 60) : 60;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_SETTINGS'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
    // UIkit Icons fallback: load from CDN if icons plugin not present
    (function(){
        function hasIcons(){ try { return !!(window.UIkit && UIkit.icon); } catch(e){ return false; } }
        function loadCdnIcons(){
            try {
                var s=document.createElement('script');
                s.src='https://cdn.jsdelivr.net/npm/uikit@3.17.11/dist/js/uikit-icons.min.js';
                s.async=true;
                s.onload=function(){ try{ window.UIkit && UIkit.update && UIkit.update(); }catch(e){} };
                document.head.appendChild(s);
            } catch(e){}
        }
        if (!hasIcons()){
            if (document.readyState==='loading'){
                document.addEventListener('DOMContentLoaded', function(){ if (!hasIcons()) loadCdnIcons(); });
            } else {
                loadCdnIcons();
            }
        }
    })();
    </script>
    <style>
        html, body { background: #ffffff !important; color: #222 !important; margin: 0; padding: 0; }
        body { padding-bottom: 52px; padding-top: env(safe-area-inset-top, 0px); }
        body.contentpane { padding: 0 !important; margin: 0 !important; }
        /* Fullscreen mode top padding for Telegram header buttons */
        :root { --tg-fullscreen-padding: <?php echo $fullscreenPadding; ?>px; }
        .tg-fullscreen-padding { padding-top: var(--tg-fullscreen-padding, 60px); }
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
        #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
        #app-bottom-nav .tg-safe-text { display: inline-flex; align-items: center; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
        #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
        #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }
        #cart-badge { position: absolute; top: 2px; right: 6px; background: #f0506e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; }
    </style>
    <script>
        (function(){
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
            document.documentElement.style.setProperty('--tg-theme-hint-color', '#999999');
            document.documentElement.style.setProperty('--tg-theme-link-color', '#2678b6');
            document.documentElement.style.setProperty('--tg-theme-button-color', '#3390ec');
            document.documentElement.style.setProperty('--tg-theme-button-text-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-secondary-bg-color', '#f5f5f5');
        })();
    </script>
    <style>
        #app-top-nav { min-height: 44px; }
        #app-top-nav .uk-navbar-center { flex-grow: 1; display: flex; justify-content: center; }
        #app-top-nav .uk-navbar-item { min-height: 44px; padding-top: 4px; padding-bottom: 4px; }
        #app-top-nav .uk-logo img { height: 32px; display: block; }
        body.tg-fullscreen #app-top-nav { margin-top: var(--tg-fullscreen-padding, 60px); }
    </style>
</head>
<body>

<nav id="app-top-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center">
        <a class="uk-navbar-item uk-logo" href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" title="cacao.land">
            <img src="/images/logo/cacao_logo.svg" alt="cacao.land">
        </a>
    </div>
</nav>

<div id="settings-app" class="uk-container uk-container-small uk-padding-small">
    <h1 class="uk-h3 uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_SETTINGS'); ?></h1>

    <form id="settings-form" class="uk-form-stacked">
        <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
            <div class="uk-margin">
                <label class="uk-form-label" for="last_name"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_NAME'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="text" id="last_name" name="last_name"
                           value="<?php echo htmlspecialchars($this->userData['last_name'] ?? ''); ?>"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_NAME'); ?>">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="first_name"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FIRST_NAME'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="text" id="first_name" name="first_name"
                           value="<?php echo htmlspecialchars($this->userData['first_name'] ?? ''); ?>"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FIRST_NAME'); ?>">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="second_name"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SECOND_NAME'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="text" id="second_name" name="second_name"
                           value="<?php echo htmlspecialchars($this->userData['second_name'] ?? ''); ?>"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_SECOND_NAME'); ?>">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="phone"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="tel" id="phone" name="phone"
                           value="<?php echo htmlspecialchars($this->userData['phone'] ?? ''); ?>"
                           placeholder="+7 999 123-45-67">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="email">
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL'); ?>
                    <?php if (!empty($this->userData['email'])): ?>
                        <?php if (!empty($this->userData['email_verified'])): ?>
                            <span class="uk-label uk-label-success uk-margin-small-left" style="font-size:0.7em;"><?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_BADGE'); ?></span>
                        <?php else: ?>
                            <a href="#" id="resend-verification-link" class="uk-margin-small-left" style="font-size:0.85em; text-decoration:none;">
                                <span class="uk-label uk-label-warning" style="font-size:0.8em; cursor:pointer;">⚠ <?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_NOT_VERIFIED'); ?></span>
                                <span class="uk-text-primary uk-text-small" style="margin-left:4px;"><?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_RESEND'); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </label>
                <div class="uk-form-controls">
                    <div class="uk-inline uk-width-1-1">
                        <input class="uk-input" type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($this->userData['email'] ?? ''); ?>"
                               placeholder="email@example.com">
                    </div>
                </div>
                <!-- Блок верификации email -->
                <div id="email-verification-block" class="uk-margin-small-top" style="display:none;">
                    <button type="button" id="btn-send-code" class="uk-button uk-button-small uk-button-secondary">
                        <?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_SEND_CODE'); ?>
                    </button>
                    <div id="code-input-block" style="display:none;" class="uk-margin-small-top">
                        <div class="uk-flex uk-flex-middle uk-flex-wrap" uk-margin>
                            <input type="text" id="email-code" class="uk-input uk-form-width-small"
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                   style="width:120px;">
                            <button type="button" id="btn-verify-code" class="uk-button uk-button-small uk-button-primary uk-margin-small-left">
                                <?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFY'); ?>
                            </button>
                        </div>
                        <p class="uk-text-meta uk-margin-remove-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL_ENTER_CODE'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($this->userData['has_joomla_account'])): ?>
        <!-- Password Change Section -->
        <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
            <h3 class="uk-card-title uk-h5"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGE'); ?></h3>

            <div class="uk-margin">
                <label class="uk-form-label" for="current_password"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CURRENT'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="password" id="current_password" name="current_password"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CURRENT'); ?>" autocomplete="current-password">
                </div>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="new_password"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NEW'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="password" id="new_password" name="new_password"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NEW'); ?>" autocomplete="new-password" minlength="8">
                </div>
                <p class="uk-text-meta uk-margin-remove-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_MIN_LENGTH'); ?></p>
            </div>

            <div class="uk-margin">
                <label class="uk-form-label" for="confirm_password"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CONFIRM'); ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="password" id="confirm_password" name="confirm_password"
                           placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CONFIRM'); ?>" autocomplete="new-password">
                </div>
            </div>

            <button type="button" id="btn-change-password" class="uk-button uk-button-secondary">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGE_BTN'); ?>
            </button>
        </div>
        <?php endif; ?>

        <button type="submit" class="uk-button uk-button-primary uk-width-1-1">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_SAVE'); ?>
        </button>
    </form>
</div>

<!-- Bottom fixed nav -->
<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart<?php echo $baseQuery; ?>" class="tg-safe-text" style="position:relative;">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                    <span id="cart-badge" style="display:none;">0</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li class="uk-active">
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<script>
// Force UIkit icons rendering
function forceUikitIcons(){
    try {
        if (!window.UIkit || !UIkit.icon) return;
        document.querySelectorAll('[uk-icon]').forEach(function(el){
            if (el.querySelector('svg')) return;
            var attr = el.getAttribute('uk-icon');
            var name = attr ? (attr.match(/icon\s*:\s*([^;]+)/) ? attr.match(/icon\s*:\s*([^;]+)/)[1].trim() : attr.trim()) : '';
            if (name) try { UIkit.icon(el, { icon: name }); } catch(e){}
        });
        UIkit.update();
    } catch(e){}
}

document.addEventListener('DOMContentLoaded', function() {
    // Force UIkit icons update - multiple attempts for async loading
    try { if (window.UIkit && UIkit.update) UIkit.update(); } catch(e){}
    setTimeout(forceUikitIcons, 100);
    setTimeout(forceUikitIcons, 500);

    document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
    document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
    document.body.style.backgroundColor = '#ffffff';
    document.body.style.color = '#222222';

    try { document.body.classList.remove('contentpane'); } catch(e){}
    try { document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax'; } catch(e) {}

    // Add fullscreen class to body (navbar already has margin-top)
    try {
        if (window.Telegram && Telegram.WebApp && Telegram.WebApp.isFullscreen) {
            document.body.classList.add('tg-fullscreen');
        }
    } catch(e){}

    let chatId = 0;
    try {
        if (window.Telegram && window.Telegram.WebApp) {
            Telegram.WebApp.ready();
            Telegram.WebApp.expand();

            // Request fullscreen mode
            try {
                if (!Telegram.WebApp.isFullscreen) {
                    Telegram.WebApp.requestFullscreen();
                }
            } catch(e) { console.log('requestFullscreen error:', e); }

            Telegram.WebApp.BackButton.show();
            Telegram.WebApp.BackButton.onClick(function() {
                const chat = new URLSearchParams(location.search).get('chat') || '';
                let url = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile';
                if (chat) url += '&chat=' + encodeURIComponent(chat);
                window.location.href = url;
            });

            chatId = Telegram.WebApp.initDataUnsafe?.user?.id || 0;
            window.TG_CHAT_ID = chatId;
        }
    } catch(e) { console.log('[Settings] TG error:', e); }

    // === Email verification logic ===
    const emailInput = document.getElementById('email');
    const verificationBlock = document.getElementById('email-verification-block');
    const btnSendCode = document.getElementById('btn-send-code');
    const codeInputBlock = document.getElementById('code-input-block');
    const emailCodeInput = document.getElementById('email-code');
    const btnVerifyCode = document.getElementById('btn-verify-code');
    const resendLink = document.getElementById('resend-verification-link');

    const originalEmail = emailInput.value.trim();
    const isVerified = <?php echo (!empty($this->userData['email_verified'])) ? 'true' : 'false'; ?>;

    // Handle click on "not verified" link to resend code
    if (resendLink) {
        resendLink.addEventListener('click', function(e) {
            e.preventDefault();
            verificationBlock.style.display = 'block';
            // Scroll to verification block
            verificationBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Trigger send code
            setTimeout(function() { btnSendCode.click(); }, 300);
        });
    }

    // Show verification block if email exists but not verified, or if email changed
    function updateVerificationVisibility() {
        const currentEmail = emailInput.value.trim();
        if (!currentEmail) {
            verificationBlock.style.display = 'none';
            return;
        }
        // If email changed from original or not verified
        if (currentEmail !== originalEmail || !isVerified) {
            verificationBlock.style.display = 'block';
        } else {
            verificationBlock.style.display = 'none';
        }
    }

    emailInput.addEventListener('input', function() {
        updateVerificationVisibility();
        // Reset code input when email changes
        codeInputBlock.style.display = 'none';
        emailCodeInput.value = '';
    });

    updateVerificationVisibility();

    // Send verification code
    btnSendCode.addEventListener('click', async function() {
        const email = emailInput.value.trim();
        if (!email) {
            UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_EMPTY'))); ?>', status: 'warning'});
            return;
        }

        btnSendCode.disabled = true;
        btnSendCode.textContent = '...';

        try {
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option', 'com_radicalmart_telegram');
            url.searchParams.set('task', 'api.sendEmailCode');
            if (window.TG_CHAT_ID) url.searchParams.set('chat', window.TG_CHAT_ID);
            try {
                if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                    url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
                }
            } catch(e){}

            const res = await fetch(url.toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email }),
                credentials: 'same-origin'
            });
            const json = await res.json();

            if (json.success) {
                codeInputBlock.style.display = 'block';
                emailCodeInput.focus();
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_CODE_SENT'))); ?>', status: 'success'});
            } else {
                UIkit.notification({message: json.message || '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_SEND'))); ?>', status: 'danger'});
            }
        } catch(err) {
            console.error('Send code error:', err);
            UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_SEND'))); ?>', status: 'danger'});
        } finally {
            btnSendCode.disabled = false;
            btnSendCode.textContent = '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_SEND_CODE'))); ?>';
        }
    });

    // Verify code
    btnVerifyCode.addEventListener('click', async function() {
        const code = emailCodeInput.value.trim();
        if (!code || code.length !== 6) {
            UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_CODE_INVALID_FORMAT'))); ?>', status: 'warning'});
            return;
        }

        btnVerifyCode.disabled = true;
        btnVerifyCode.textContent = '...';

        try {
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option', 'com_radicalmart_telegram');
            url.searchParams.set('task', 'api.verifyEmailCode');
            if (window.TG_CHAT_ID) url.searchParams.set('chat', window.TG_CHAT_ID);
            try {
                if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                    url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
                }
            } catch(e){}

            const res = await fetch(url.toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code }),
                credentials: 'same-origin'
            });
            const json = await res.json();

            if (json.success) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_SUCCESS'))); ?>', status: 'success'});
                // Reload page to show verified status
                setTimeout(() => location.reload(), 1000);
            } else {
                UIkit.notification({message: json.message || '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_INVALID_CODE'))); ?>', status: 'danger'});
            }
        } catch(err) {
            console.error('Verify code error:', err);
            UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_SETTINGS_ERROR'))); ?>', status: 'danger'});
        } finally {
            btnVerifyCode.disabled = false;
            btnVerifyCode.textContent = '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFY'))); ?>';
        }
    });

    // Allow only digits in code input
    emailCodeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });


    // === Password change logic ===
    const btnChangePassword = document.getElementById('btn-change-password');
    if (btnChangePassword) {
        btnChangePassword.addEventListener('click', async function() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Validation
            if (!currentPassword) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CURRENT_REQUIRED'))); ?>', status: 'warning'});
                return;
            }
            if (!newPassword) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NEW_REQUIRED'))); ?>', status: 'warning'});
                return;
            }
            if (newPassword.length < 8) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_TOO_SHORT'))); ?>', status: 'warning'});
                return;
            }
            if (newPassword !== confirmPassword) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_MISMATCH'))); ?>', status: 'warning'});
                return;
            }

            btnChangePassword.disabled = true;
            const originalText = btnChangePassword.textContent;
            btnChangePassword.textContent = '...';

            try {
                const url = new URL(location.origin + '/index.php');
                url.searchParams.set('option', 'com_radicalmart_telegram');
                url.searchParams.set('task', 'api.changePassword');
                if (window.TG_CHAT_ID) url.searchParams.set('chat', window.TG_CHAT_ID);
                try {
                    if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                        url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
                    }
                } catch(e){}

                const res = await fetch(url.toString(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    }),
                    credentials: 'same-origin'
                });
                const json = await res.json();

                if (json.success) {
                    UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGED'))); ?>', status: 'success'});
                    // Clear password fields
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                } else {
                    UIkit.notification({message: json.message || '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGE_ERROR'))); ?>', status: 'danger'});
                }
            } catch(err) {
                console.error('Password change error:', err);
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGE_ERROR'))); ?>', status: 'danger'});
            } finally {
                btnChangePassword.disabled = false;
                btnChangePassword.textContent = originalText;
            }
        });
    }

    // Form submit
    document.getElementById('settings-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '...';

        const data = {
            first_name: form.first_name.value.trim(),
            second_name: form.second_name.value.trim(),
            last_name: form.last_name.value.trim(),
            phone: form.phone.value.trim(),
            email: form.email.value.trim()
        };

        try {
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option', 'com_radicalmart_telegram');
            url.searchParams.set('task', 'api.updateprofile');
            if (window.TG_CHAT_ID) url.searchParams.set('chat', window.TG_CHAT_ID);
            try {
                if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                    url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
                }
            } catch(e){}

            const res = await fetch(url.toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            });
            const json = await res.json();

            if (json.success) {
                UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_SETTINGS_SAVED'))); ?>', status: 'success'});
            } else {
                UIkit.notification({message: json.message || '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_SETTINGS_ERROR'))); ?>', status: 'danger'});
            }
        } catch(err) {
            console.error('Save error:', err);
            UIkit.notification({message: '<?php echo addslashes(str_replace(["\r\n", "\r", "\n"], ' ', Text::_('COM_RADICALMART_TELEGRAM_SETTINGS_ERROR'))); ?>', status: 'danger'});
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    refreshCart();
});

async function refreshCart() {
    try {
        const url = new URL(location.origin + '/index.php');
        url.searchParams.set('option', 'com_radicalmart_telegram');
        url.searchParams.set('task', 'api.cart');
        if (window.TG_CHAT_ID) url.searchParams.set('chat', window.TG_CHAT_ID);
        try {
            if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
            }
        } catch(e) {}

        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        const json = await res.json();
        const cart = json.data?.cart;
        const badge = document.getElementById('cart-badge');

        if (!cart || !cart.products || Object.keys(cart.products).length === 0) {
            if (badge) badge.style.display = 'none';
            return;
        }

        const count = (cart.total && cart.total.quantity) ? parseInt(cart.total.quantity, 10) : Object.keys(cart.products).length;
        if (badge) {
            if (count > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = String(count);
            } else {
                badge.style.display = 'none';
            }
        }
    } catch(e) { console.error('refreshCart error:', e); }
}

function RMT_EXTRACT_ICON_NAME(attr){
    if (!attr) return '';
    let s = String(attr);
    const m = s.match(/icon\s*:\s*([^;]+)/);
    if (m && m[1]) return m[1].trim();
    return s.trim();
}
function RMT_FORCE_UKIT_ICONS(){
    try{
        if (!window.UIkit || !UIkit.icon) return false;
        const nodes = document.querySelectorAll('[uk-icon]');
        let forced = 0;
        nodes.forEach(el => {
            if (el.querySelector('svg')) return;
            const name = RMT_EXTRACT_ICON_NAME(el.getAttribute('uk-icon'));
            try { UIkit.icon(el, { icon: name }); forced++; } catch(_){}
        });
        try { UIkit.update(); } catch(_){}
        return forced>0;
    }catch(e){ return false; }
}

let RMT_ICON_OBSERVER = null;
function RMT_OBSERVE_ICONS(){
    try{
        if (RMT_ICON_OBSERVER) return;
        RMT_ICON_OBSERVER = new MutationObserver((mutations) => {
            let needsCheck = false;
            for (const m of mutations){
                if (m.type === 'childList'){
                    if (m.target && (m.target.hasAttribute?.('uk-icon') || m.target.querySelector?.('[uk-icon]'))) {
                        needsCheck = true; break;
                    }
                    for (const n of m.addedNodes){
                        if (n.nodeType === 1 && ((n.hasAttribute && n.hasAttribute('uk-icon')) || n.querySelector?.('[uk-icon]'))) { needsCheck = true; break; }
                    }
                }
                if (needsCheck) break;
            }
            if (needsCheck) { try { RMT_FORCE_UKIT_ICONS(); } catch(e){} }
        });
        RMT_ICON_OBSERVER.observe(document.documentElement || document.body, { childList: true, subtree: true });
    }catch(e){}
}

document.addEventListener('DOMContentLoaded', function(){
    RMT_FORCE_UKIT_ICONS();
    RMT_OBSERVE_ICONS();
});
</script>

</body>
</html>
