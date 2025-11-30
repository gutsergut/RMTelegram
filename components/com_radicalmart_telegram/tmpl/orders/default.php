<?php
/**
 * Orders View - Telegram WebApp
 * @package com_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Orders\HtmlView $this */

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);

// Данные пользователя из View (через TelegramUserHelper)
$tgUser = $this->tgUser;
$userId = $tgUser['user_id'] ?? 0;
$chatId = $tgUser['chat_id'] ?? $chat;

// Build base URL with chat param
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></title>
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
    <div class="uk-container uk-container-small uk-padding-small">
        <h1 class="uk-h3 uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></h1>

        <?php if (!empty($this->statuses)): ?>
        <div class="uk-margin-bottom">
            <select class="uk-select uk-form-small" onchange="filterByStatus(this.value)">
                <option value=""><?php echo Text::_('COM_RADICALMART_TELEGRAM_ALL_STATUSES'); ?></option>
                <?php foreach ($this->statuses as $status): ?>
                <option value="<?php echo $status->id; ?>" <?php echo $this->currentStatus == $status->id ? 'selected' : ''; ?>>
                    <?php echo $status->title; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (empty($this->items)): ?>
        <div class="uk-card uk-card-default uk-card-body uk-text-center">
            <span uk-icon="icon: list; ratio: 3" class="uk-text-muted"></span>
            <p class="uk-text-lead uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_ORDERS'); ?></p>
            <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="uk-button uk-button-primary">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_GO_CATALOG'); ?>
            </a>
        </div>
        <?php else: ?>
        <div class="uk-grid-small" uk-grid>
            <?php foreach ($this->items as $order): ?>
            <div class="uk-width-1-1">
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=order&id=<?php echo $order->id; ?><?php echo $baseQuery; ?>"
                   class="uk-card uk-card-default uk-card-small uk-card-hover uk-display-block uk-link-reset">
                    <div class="uk-card-header">
                        <div class="uk-grid-small uk-flex-middle" uk-grid>
                            <div class="uk-width-expand">
                                <h3 class="uk-card-title uk-margin-remove-bottom uk-text-bold">
                                    Заказ №<?php echo $order->number ?: $order->id; ?>
                                </h3>
                                <p class="uk-text-meta uk-margin-remove-top">
                                    <?php echo HTMLHelper::date($order->created, Text::_('DATE_FORMAT_LC4')); ?>
                                </p>
                            </div>
                            <div class="uk-width-auto">
                                <?php
                                $statusTitle = 'Неизвестно';
                                $statusClass = '';

                                if (!empty($order->status) && is_object($order->status)) {
                                    $statusTitle = $order->status->title ?? $order->status->rawtitle ?? 'Статус';
                                    if (strpos($statusTitle, 'COM_') === 0) {
                                        $statusTitle = $order->status->rawtitle ?? $statusTitle;
                                    }
                                    $statusClass = $order->status->params ? $order->status->params->get('class_site', '') : '';
                                    $statusClass = str_replace(['bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-secondary', 'bg-primary'],
                                                               ['uk-label-success', 'uk-label-danger', 'uk-label-warning', 'uk-label-warning', '', ''], $statusClass);
                                }
                                ?>
                                <span class="uk-label <?php echo $statusClass; ?>">
                                    <?php echo $statusTitle; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="uk-card-body">
                        <dl class="uk-description-list uk-description-list-divider uk-margin-remove">
                            <div class="uk-grid-small" uk-grid>
                                <dt class="uk-width-1-2"><?php echo Text::_('COM_RADICALMART_PRODUCTS'); ?></dt>
                                <dd class="uk-width-1-2 uk-text-right uk-text-bold"><?php echo count($order->products); ?></dd>
                            </div>
                            <?php if ($order->shipping && $order->shipping->get('title')): ?>
                            <div class="uk-grid-small" uk-grid>
                                <dt class="uk-width-1-2"><?php echo Text::_('COM_RADICALMART_SHIPPING'); ?></dt>
                                <dd class="uk-width-1-2 uk-text-right">
                                    <?php echo $order->shipping->get('title'); ?>
                                    <?php if ($shippingCost = $order->shipping->get('final_string')): ?>
                                    <span class="uk-text-muted"> (<?php echo $shippingCost; ?>)</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <?php endif; ?>
                            <?php if ($order->payment && $order->payment->get('title')): ?>
                            <div class="uk-grid-small" uk-grid>
                                <dt class="uk-width-1-2"><?php echo Text::_('COM_RADICALMART_PAYMENT'); ?></dt>
                                <dd class="uk-width-1-2 uk-text-right"><?php echo $order->payment->get('title'); ?></dd>
                            </div>
                            <?php endif; ?>
                            <div class="uk-grid-small" uk-grid>
                                <dt class="uk-width-1-2"><?php echo Text::_('COM_RADICALMART_TOTAL'); ?></dt>
                                <dd class="uk-width-1-2 uk-text-right uk-text-bold uk-text-primary">
                                    <?php echo $order->total['final_string'] ?? ''; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="uk-card-footer uk-text-right">
                        <span class="uk-text-small uk-text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_VIEW_DETAILS'); ?></span>
                        <span uk-icon="chevron-right"></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
                <li class="uk-active">
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Force light theme again after DOM loaded
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
            document.body.style.backgroundColor = '#ffffff';
            document.body.style.color = '#222222';

            // Initialize UIkit icons
            if (typeof UIkit !== 'undefined') {
                UIkit.update(document.body);
            }

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
                        const url = new URL(window.location.href);
                        if (!url.searchParams.has('chat')) {
                            url.searchParams.set('chat', chatId);
                            window.location.replace(url.toString());
                            return;
                        }
                        document.querySelectorAll('a[href*="com_radicalmart_telegram"]').forEach(link => {
                            try {
                                const linkUrl = new URL(link.href);
                                if (!linkUrl.searchParams.has('chat')) {
                                    linkUrl.searchParams.set('chat', chatId);
                                    link.href = linkUrl.toString();
                                }
                            } catch(e) {}
                        });
                    }
                }
            } catch(e) { console.log('[Orders] TG error:', e); }

            // Load cart count
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

        function filterByStatus(statusId) {
            const url = new URL(window.location.href);
            if (statusId) {
                url.searchParams.set('status', statusId);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }
    </script>

    <script>
    // --- UIkit Icons Fix (from cart) ---
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

    // Observe DOM changes to force icons
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
