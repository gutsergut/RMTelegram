<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  profile
 * Profile page - Telegram WebApp standalone
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Profile\HtmlView $this */

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatId = $this->tgUser['chat_id'] ?? $chat;
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';

// Данные пользователя
$userName = $this->tgUser['name'] ?? 'Пользователь';
$userPhone = $this->tgUser['phone'] ?? '';
$userId = $this->tgUser['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        html, body { background: #ffffff !important; color: #222 !important; margin: 0; padding: 0; }
        body { padding-bottom: 52px; }
        body.contentpane { padding: 0 !important; margin: 0 !important; }

        /* Cart badge */
        #cart-badge { position: absolute; top: 2px; right: 6px; background: #f0506e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; }

        /* Bottom nav - same as app page */
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
        #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
        #app-bottom-nav .tg-safe-text { display: inline-flex; align-items: center; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
        #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
        #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }
    </style>
    <script>
        // Force light theme
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
</head>
<body>

<div id="profile-app" class="uk-container uk-container-small uk-padding-small">
    <h1 class="uk-h3 uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></h1>

    <!-- Карточка пользователя -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <div class="uk-flex uk-flex-middle">
            <div class="uk-width-auto uk-margin-right">
                <span uk-icon="icon: user; ratio: 2"></span>
            </div>
            <div class="uk-width-expand">
                <h3 class="uk-card-title uk-margin-remove-bottom"><?php echo htmlspecialchars($userName); ?></h3>
                <?php if ($userPhone): ?>
                <p class="uk-text-meta uk-margin-remove-top"><?php echo htmlspecialchars($userPhone); ?></p>
                <?php elseif ($userId > 0): ?>
                <p class="uk-text-meta uk-margin-remove-top">ID: <?php echo $userId; ?></p>
                <?php else: ?>
                <p class="uk-text-meta uk-margin-remove-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_GUEST'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Блок баллов -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3 class="uk-card-title uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS'); ?></h3>
        <?php if ($this->points > 0): ?>
        <div class="uk-text-lead uk-text-primary">
            <?php echo number_format($this->points, 0, ',', ' '); ?> <?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_UNIT'); ?>
            <span class="uk-text-muted uk-text-small">(= <?php echo $this->pointsEquivalent; ?>)</span>
        </div>
        <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=points<?php echo $baseQuery; ?>" class="uk-link-muted uk-text-small">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_VIEW_HISTORY'); ?> →
        </a>
        <?php else: ?>
        <div class="uk-text-muted">
            0 <?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_UNIT'); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Меню профиля -->
    <div class="uk-card uk-card-default uk-margin-bottom">
        <ul class="uk-list uk-list-divider uk-margin-remove">
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: list" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <?php if ($this->points > 0): ?>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=points<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: star" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_HISTORY'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=codes<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: tag" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO_CODES'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=referrals<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: users" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
        </ul>
    </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Force light theme again
        document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
        document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
        document.body.style.backgroundColor = '#ffffff';
        document.body.style.color = '#222222';

        try { document.body.classList.remove('contentpane'); } catch(e){}
        try { document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax'; } catch(e) {}

        try {
            if (window.Telegram && window.Telegram.WebApp) {
                Telegram.WebApp.ready();
                Telegram.WebApp.expand();

                // Setup BackButton
                Telegram.WebApp.BackButton.show();
                Telegram.WebApp.BackButton.onClick(function() {
                    const chat = new URLSearchParams(location.search).get('chat') || '';
                    let url = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app';
                    if (chat) url += '&chat=' + encodeURIComponent(chat);
                    window.location.href = url;
                });

                const chatId = Telegram.WebApp.initDataUnsafe?.user?.id;
                window.TG_CHAT_ID = chatId || 0;

                if (chatId) {
                    document.querySelectorAll('a[href*="com_radicalmart_telegram"]').forEach(link => {
                        try {
                            const url = new URL(link.href);
                            if (!url.searchParams.has('chat')) {
                                url.searchParams.set('chat', chatId);
                                link.href = url.toString();
                            }
                        } catch(e) {}
                    });
                }
            }
        } catch(e) { console.log('[Profile] TG error:', e); }

        // Load cart count
        refreshCart();
    });
</script>

<script>
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
</script>

<script>
// --- UIkit Icons Fix ---
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
        if (forced>0) console.log('[RMT][icons] UIkit.icon forced for', forced, 'elements');
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
