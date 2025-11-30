<?php
/**
 * Telegram WebApp Cart View
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

$root = rtrim(Uri::root(), '/');
$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';

// Данные пользователя из View (через TelegramUserHelper)
$tgUser = $this->tgUser ?? null;
$userId = $tgUser['user_id'] ?? 0;
$chatId = $tgUser['chat_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeTitle . ' - ' . Text::_('COM_RADICALMART_TELEGRAM_CART'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        html, body { background-color: var(--tg-theme-bg-color, #ffffff); color: var(--tg-theme-text-color, #222); }
        body { padding-bottom: 70px; } /* Space for bottom nav */
        body.contentpane { padding: 0 !important; margin: 0 !important; }

        /* Bottom fixed navigation */
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; background: var(--tg-theme-bg-color, #fff); border-top: 1px solid rgba(0,0,0,0.1); }
        #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
        #app-bottom-nav .tg-safe-text { display: inline-flex; align-items: center; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
        #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
        #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }

        /* Cart specific styles */
        .cart-item { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 10px 0; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-title { font-weight: 500; font-size: 1.1em; margin-bottom: 5px; }
        .cart-item-meta { font-size: 0.9em; color: #666; }
        .cart-qty-control { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .cart-qty-btn { width: 30px; height: 30px; border-radius: 50%; border: 1px solid #ddd; background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; }
        .cart-qty-val { font-weight: bold; min-width: 20px; text-align: center; }
        .cart-total-block { background: rgba(0,0,0,0.03); padding: 15px; border-radius: 8px; margin-top: 20px; }
        .cart-total-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .cart-total-final { font-size: 1.2em; font-weight: bold; margin-top: 10px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 10px; }

        /* Dark mode adjustments */
        .rmt-dark #app-bottom-nav { background: #1b1c1d; border-top-color: rgba(255,255,255,0.1); }
        .rmt-dark .cart-qty-btn { border-color: #444; color: #fff; }
        .rmt-dark .cart-total-block { background: rgba(255,255,255,0.05); }
        .rmt-dark .cart-item-meta { color: #aaa; }

        #cart-badge { position: absolute; top: 2px; right: 6px; background: #f0506e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; }
    </style>
    <script>
        // Basic API helper
        function qs(name){ const p=new URLSearchParams(location.search); return p.get(name); }
        function makeNonce(){ return (Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,10)); }

        async function api(method, params={}){
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option','com_radicalmart_telegram');
            url.searchParams.set('task','api.'+method);
            const chat = qs('chat'); if (chat) url.searchParams.set('chat', chat);
            try { if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) { url.searchParams.set('tg_init', window.Telegram.WebApp.initData); } } catch(e){}
            for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);

            const res = await fetch(url.toString(), { credentials: 'same-origin' });
            const json = await res.json();
            if (json && json.success === false) throw new Error(json.message||'API error');
            return json.data || {};
        }

        // Theme handling
        function getUserTheme(){ try { return localStorage.getItem('rmt_theme') || null; } catch(e){ return null; } }
        function applyTheme(mode){
            const root = document.documentElement;
            const tp = (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.themeParams) ? window.Telegram.WebApp.themeParams : {};
            const cs = (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.colorScheme) ? window.Telegram.WebApp.colorScheme : 'light';
            let bg, text, btn, btnText;
            if (mode === 'tg'){
                bg = tp.bg_color || (cs==='dark' ? '#1f1f1f' : '#ffffff');
                text = tp.text_color || (cs==='dark' ? '#ffffff' : '#222222');
                btn = tp.button_color || '#1e87f0';
                btnText = tp.button_text_color || '#ffffff';
            } else if (mode === 'dark'){
                bg = '#1b1c1d'; text = '#ffffff'; btn = '#1e87f0'; btnText = '#ffffff';
            } else { // light
                bg = '#ffffff'; text = '#222222'; btn = '#1e87f0'; btnText = '#ffffff';
            }
            root.style.setProperty('--tg-theme-bg-color', bg);
            root.style.setProperty('--tg-theme-text-color', text);
            root.style.setProperty('--tg-theme-button-color', btn);
            root.style.setProperty('--tg-theme-button-text-color', btnText);

            const isDark = (mode==='dark') || (mode==='tg' && (cs==='dark'));
            try { root.classList.toggle('rmt-dark', !!isDark); document.body.classList.toggle('rmt-dark', !!isDark); } catch(e){}
        }
        function initTheme(){
            // Force light theme by default (ignore localStorage)
            applyTheme('light');
            // Still listen for Telegram theme changes but don't apply them automatically
            // try { window.Telegram?.WebApp?.onEvent?.('themeChanged', () => {}); } catch(e){}
        }

        // Initialize Telegram BackButton
        function initBackButton(){
            try {
                if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.BackButton) {
                    const tg = window.Telegram.WebApp;
                    tg.BackButton.show();
                    tg.BackButton.onClick(function() {
                        // Navigate to catalog
                        const chat = new URLSearchParams(location.search).get('chat') || '';
                        let url = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app';
                        if (chat) url += '&chat=' + encodeURIComponent(chat);
                        window.location.href = url;
                    });
                }
            } catch(e) { console.log('BackButton init error:', e); }
        }

        // Cart Logic
        async function loadCart(){
            const container = document.getElementById('cart-container');
            const spinner = document.getElementById('cart-loading');
            const empty = document.getElementById('cart-empty');
            const content = document.getElementById('cart-content');

            try {
                spinner.hidden = false;
                content.hidden = true;
                empty.hidden = true;

                const { cart } = await api('cart');
                console.log('Cart loaded:', cart);

                if (!cart || !cart.products || Object.keys(cart.products).length === 0) {
                    spinner.hidden = true;
                    empty.hidden = false;
                    updateBadge(0);
                    return;
                }

                renderCartItems(cart);
                renderCartTotal(cart);
                updateBadge(cart.total ? cart.total.quantity : 0);

                spinner.hidden = true;
                content.hidden = false;

            } catch(e) {
                console.error('Load cart error:', e);
                UIkit.notification('Ошибка загрузки корзины', {status:'danger'});
                spinner.hidden = true;
            }
        }

        function renderCartItems(cart) {
            const list = document.getElementById('cart-items-list');
            list.innerHTML = '';

            for (const key in cart.products) {
                const p = cart.products[key];
                const id = p.id;
                const title = p.title || 'Товар';
                const img = p.image || '';
                const price = (p.order && p.order.price_final_string) ? p.order.price_final_string : '';
                const qty = parseFloat(p.order ? p.order.quantity : 1);
                const sum = (p.order && p.order.sum_final_string) ? p.order.sum_final_string : '';

                const li = document.createElement('div');
                li.className = 'cart-item';
                li.innerHTML = `
                    <div class="uk-grid-small uk-flex-middle" uk-grid>
                        <div class="uk-width-auto">
                            ${img ? `<img src="${img}" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">` : `<div style="width:60px;height:60px;background:#f5f5f5;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc"><span uk-icon="image"></span></div>`}
                        </div>
                        <div class="uk-width-expand">
                            <div class="cart-item-title">${title}</div>
                            <div class="cart-item-meta">${price}</div>
                            <div class="cart-qty-control">
                                <button class="cart-qty-btn" onclick="changeQty(${id}, ${qty - 1})"><span uk-icon="minus"></span></button>
                                <span class="cart-qty-val">${qty}</span>
                                <button class="cart-qty-btn" onclick="changeQty(${id}, ${qty + 1})"><span uk-icon="plus"></span></button>
                                <div class="uk-width-expand uk-text-right">
                                    <span class="uk-text-bold">${sum}</span>
                                </div>
                            </div>
                        </div>
                        <div class="uk-width-auto">
                            <button class="uk-icon-button uk-text-danger" uk-icon="trash" onclick="removeItem(${id})"></button>
                        </div>
                    </div>
                `;
                list.appendChild(li);
            }
            // Force UIkit update for dynamic content
            try { if(window.UIkit) UIkit.update(); } catch(e){}
        }

        function renderCartTotal(cart) {
            const block = document.getElementById('cart-total-block');
            if (!cart.total) {
                block.innerHTML = '';
                return;
            }

            let html = '';
            if (cart.total.discount_string) {
                html += `<div class="cart-total-row"><span>Скидка:</span> <span>${cart.total.discount_string}</span></div>`;
            }
            if (cart.total.shipping_string) {
                html += `<div class="cart-total-row"><span>Доставка:</span> <span>${cart.total.shipping_string}</span></div>`;
            }

            html += `<div class="cart-total-row cart-total-final"><span>Итого:</span> <span>${cart.total.final_string}</span></div>`;

            block.innerHTML = html;
        }

        async function changeQty(id, newQty) {
            if (newQty < 1) return; // Use remove for 0
            try {
                const { cart } = await api('qty', { id, qty: newQty, nonce: makeNonce() });
                if (cart) {
                    renderCartItems(cart);
                    renderCartTotal(cart);
                    updateBadge(cart.total ? cart.total.quantity : 0);
                }
            } catch(e) {
                UIkit.notification('Ошибка обновления количества', {status:'danger'});
            }
        }

        async function removeItem(id) {
            if (!confirm('Удалить товар из корзины?')) return;
            try {
                const { cart } = await api('remove', { id, nonce: makeNonce() });
                if (!cart || !cart.products || Object.keys(cart.products).length === 0) {
                    document.getElementById('cart-content').hidden = true;
                    document.getElementById('cart-empty').hidden = false;
                    updateBadge(0);
                } else {
                    renderCartItems(cart);
                    renderCartTotal(cart);
                    updateBadge(cart.total ? cart.total.quantity : 0);
                }
            } catch(e) {
                UIkit.notification('Ошибка удаления товара', {status:'danger'});
            }
        }

        function updateBadge(count) {
            const badge = document.getElementById('cart-badge');
            if (badge) {
                if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = count;
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            try { document.body.classList.remove('contentpane'); } catch(e){}

            // Set WebApp cookie for redirect protection
            try {
                document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax';
            } catch(e) {}

            // Initialize Telegram WebApp and store user data
            try {
                if (window.Telegram && window.Telegram.WebApp) {
                    Telegram.WebApp.ready();
                    Telegram.WebApp.expand();

                    const tgUser = Telegram.WebApp.initDataUnsafe?.user;
                    const chatId = tgUser?.id;
                    console.log('[Cart] TG User:', tgUser, 'chatId:', chatId);

                    // Store globally
                    window.TG_CHAT_ID = chatId || 0;
                    window.TG_USER = tgUser || null;

                    // Update navigation links with chat param
                    if (chatId) {
                        document.querySelectorAll('#app-bottom-nav a, a[href*="com_radicalmart_telegram"]').forEach(link => {
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
            } catch(e) { console.log('[Cart] TG error:', e); }

            initTheme();
            initBackButton();
            loadCart();
        });
    </script>
</head>
<body>

<div class="uk-container uk-padding-small">
    <h2 class="uk-heading-small uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></h2>

    <div id="cart-loading" class="uk-flex uk-flex-center uk-margin-large-top">
        <div uk-spinner="ratio: 2"></div>
    </div>

    <div id="cart-empty" class="uk-text-center uk-margin-large-top" hidden>
        <div uk-icon="icon: cart; ratio: 3" class="uk-text-muted"></div>
        <p class="uk-text-lead"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART_EMPTY'); ?></p>
        <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=app" class="uk-button uk-button-primary uk-margin-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_GO_CATALOG'); ?></a>
    </div>

    <div id="cart-content" hidden>
        <div id="cart-items-list"></div>

        <div id="cart-total-block" class="cart-total-block"></div>

        <div class="uk-margin-top">
            <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=checkout" class="uk-button uk-button-primary uk-width-1-1 uk-button-large"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHECKOUT'); ?></a>
        </div>
    </div>
</div>

<!-- Bottom fixed nav -->
<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li>
                <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=app" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li class="uk-active">
                <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=cart" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                    <span id="cart-badge" style="display:none;">0</span>
                </a>
            </li>
            <li>
                <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=orders" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=profile" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                </a>
            </li>
        </ul>
    </div>
</div>

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
