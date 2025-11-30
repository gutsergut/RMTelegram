<?php
/**
 * Telegram WebApp Checkout View
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

// Force load language file for the component
Factory::getLanguage()->load('com_radicalmart_telegram', JPATH_COMPONENT_SITE);

$root = rtrim(Uri::root(), '/');
$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';

// Данные пользователя из View (через TelegramUserHelper)
$tgUser = $this->tgUser ?? null;
$userId = $tgUser['user_id'] ?? 0;
$chatId = $tgUser['chat_id'] ?? 0;

// Получаем иконки служб доставки из настроек
$pvzIcons = [
    'cdek' => isset($this->params) ? (string) $this->params->get('pvz_icon_cdek', '') : '',
    'x5' => isset($this->params) ? (string) $this->params->get('pvz_icon_x5', '') : '',
    'yataxi' => isset($this->params) ? (string) $this->params->get('pvz_icon_yataxi', '') : '',
    'boxberry' => isset($this->params) ? (string) $this->params->get('pvz_icon_boxberry', '') : '',
    'dpd' => isset($this->params) ? (string) $this->params->get('pvz_icon_dpd', '') : '',
];
// Настройка скрытия неактивных ПВЗ
$hideInactivePvz = isset($this->params) ? (int) $this->params->get('hide_inactive_pvz', 1) : 1;

// Обрабатываем пути иконок: оставляем только относительный путь
foreach ($pvzIcons as $k => $v) {
    if (!$v) continue;
    // Убираем суффикс #joomlaImage://... от Joomla Media Manager
    if (($hashPos = strpos($v, '#')) !== false) {
        $v = substr($v, 0, $hashPos);
    }
    // Если путь содержит полный URL с доменом, извлекаем только path
    if (preg_match('#^https?://[^/]+(/.*?)$#i', $v, $m)) {
        $v = $m[1]; // Только path часть URL
    }
    // Убираем протокол без домена (если вдруг такой формат)
    $v = preg_replace('#^https?://#i', '', $v);
    // Убираем ВСЕ начальные слеши
    $v = ltrim($v, '/');
    // Добавляем ровно один слеш в начало (если путь не пустой)
    if ($v !== '') {
        // $v = '/' . $v;
    }
    $pvzIcons[$k] = $v;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeTitle . ' - ' . Text::_('COM_RADICALMART_TELEGRAM_CHECKOUT'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=7866d4e2-e700-48ba-94e6-a53d93c03e96"></script>
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

        /* Checkout specific styles */
        .checkout-section { margin-bottom: 20px; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; }
        .checkout-section h3 { font-size: 1.1rem; margin-bottom: 10px; font-weight: 500; }
        .checkout-summary { font-size: 1.1rem; font-weight: bold; margin-top: 10px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 10px; }

        /* Points & Promo styling */
        #points-input { width: 80px; }
        #points-block .uk-button-small { min-width: 36px; height: 36px; padding: 0; }
        #promo-result.uk-alert-success { background: #e8f5e9; color: #2e7d32; }
        #promo-result.uk-alert-danger { background: #ffebee; color: #c62828; }
        #points-result.uk-alert-success { background: #e8f5e9; color: #2e7d32; }
        #points-result.uk-alert-danger { background: #ffebee; color: #c62828; }
        .points-discount-line { color: #4CAF50; }

        /* Payment methods styling */
        .payment-method-item { background: rgba(0,0,0,0.02); border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .payment-method-item:hover { background: rgba(0,0,0,0.05); }
        .payment-method-item input[type="radio"]:checked + * { color: var(--tg-theme-button-color, #1e87f0); }

        /* Dark mode adjustments */
        .rmt-dark #app-bottom-nav { background: #1b1c1d; border-top-color: rgba(255,255,255,0.1); }
        .rmt-dark .checkout-section { background: rgba(255,255,255,0.05); }
        .rmt-dark .payment-method-item { background: rgba(255,255,255,0.05); }
        .rmt-dark .payment-method-item:hover { background: rgba(255,255,255,0.1); }

        /* Yandex Maps Balloon Fixes */
        .ymaps-2-1-79-balloon { z-index: 10000 !important; }
        .ymaps-2-1-79-balloon__content { padding: 12px !important; min-width: 200px; }
        /* Hide "Create your map" footer link in balloon */
        .ymaps-2-1-79-balloon__footer { display: none !important; }
        .ymaps-2-1-79-balloon__layout { padding-bottom: 0 !important; }
        .select-pvz-btn { margin-top: 10px; width: 100%; }
        .pvz-type-label { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .pvz-type-label.pvz { background: #e3f2fd; color: #1565c0; }
        .pvz-type-label.postamat { background: #e8f5e9; color: #2e7d32; }

        /* Provider filter buttons */
        .provider-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .provider-filter-btn { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border: 2px solid #e0e0e0; border-radius: 20px; background: #fff; cursor: pointer; transition: all 0.2s; min-width: 44px; height: 36px; }
        .provider-filter-btn:hover { border-color: #bbb; }
        .provider-filter-btn.active { border-color: var(--tg-theme-button-color, #1e87f0); background: rgba(30, 135, 240, 0.1); }
        .provider-filter-btn img { width: 24px; height: 24px; object-fit: contain; }
        .provider-filter-btn .filter-label { font-size: 13px; font-weight: 500; }
        .rmt-dark .provider-filter-btn { background: #2a2a2a; border-color: #444; }
        .rmt-dark .provider-filter-btn:hover { border-color: #666; }
        .rmt-dark .provider-filter-btn.active { border-color: var(--tg-theme-button-color, #1e87f0); background: rgba(30, 135, 240, 0.2); }

        /* PVZ Info Panel (below map) */
        .pvz-info-panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            margin-top: 12px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .pvz-info-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .pvz-provider { display: flex; align-items: center; font-weight: 500; color: #333; }
        .pvz-provider img { margin-right: 8px; }
        .pvz-type-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
        .pvz-type-badge.pvz { background: #e3f2fd; color: #1565c0; }
        .pvz-type-badge.postamat { background: #e8f5e9; color: #2e7d32; }
        .pvz-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; color: #222; }
        .pvz-address { color: #666; font-size: 13px; margin-bottom: 10px; }
        .pvz-price { font-size: 14px; color: #4CAF50; font-weight: 500; margin-bottom: 4px; }
        .pvz-price-loading { color: #999; font-style: italic; }
        /* Keep light style even in dark mode for this panel */
        .rmt-dark .pvz-info-panel { background: #ffffff; border-color: #e0e0e0; }
        .rmt-dark .pvz-info-panel .pvz-provider { color: #333; }
        .rmt-dark .pvz-info-panel .pvz-title { color: #222; }
        .rmt-dark .pvz-info-panel .pvz-address { color: #666; }
    </style>
    <script>
        // PVZ Icons configuration from settings
        const pvzIcons = <?php echo json_encode($pvzIcons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        // Setting: hide PVZ without tariffs
        const hideInactivePvz = <?php echo $hideInactivePvz ? 'true' : 'false'; ?>;

        // Basic API helper
        function qs(name){ const p=new URLSearchParams(location.search); return p.get(name); }
        function makeNonce(){ return (Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,10)); }

        async function api(method, params={}){
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option','com_radicalmart_telegram');
            url.searchParams.set('task','api.'+method);
            const chat = qs('chat'); if (chat) url.searchParams.set('chat', chat);
            try { if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) { url.searchParams.set('tg_init', window.Telegram.WebApp.initData); } } catch(e){}
            for (const [k,v] of Object.entries(params)) {
                if (v !== null && v !== undefined) {
                    url.searchParams.set(k, String(v));
                }
            }

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
            let t=getUserTheme();
            if(!t){ t = 'light'; }
            applyTheme(t);
            try { window.Telegram?.WebApp?.onEvent?.('themeChanged', () => {
                const userPref = getUserTheme();
                if (!userPref || userPref === 'tg') { applyTheme('tg'); }
            }); } catch(e){}
        }

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

        // Checkout Logic
        async function loadCheckout(){
            const spinner = document.getElementById('checkout-loading');
            const form = document.getElementById('checkout-form');
            const empty = document.getElementById('checkout-empty');

            try {
                spinner.hidden = false;
                form.hidden = true;
                empty.hidden = true;

                // Load cart, methods, profile in parallel
                const [cartRes, methodsRes, profileRes] = await Promise.all([
                    api('cart'),
                    api('methods'),
                    api('profile')
                ]);

                const cart = cartRes.cart;
                if (!cart || !cart.products || Object.keys(cart.products).length === 0) {
                    spinner.hidden = true;
                    empty.hidden = false;
                    return;
                }

                // Save cart globally for later use (e.g., preserving shipping info)
                window.currentCart = cart;

                renderSummary(cart);
                renderMethods(methodsRes);
                prefillProfile(profileRes);

                spinner.hidden = true;
                form.hidden = false;

            } catch(e) {
                console.error('Load checkout error:', e);
                UIkit.notification('Ошибка загрузки оформления заказа', {status:'danger'});
                spinner.hidden = true;
            }
        }

        // Helper to parse number from string with separators like "3 600" or "3600"
        function parseNumber(val) {
            if (typeof val === 'number') return val;
            if (typeof val === 'string') {
                // Remove spaces and non-breaking spaces, replace comma with dot
                const cleaned = val.replace(/[\s\u00A0]/g, '').replace(',', '.');
                return parseFloat(cleaned) || 0;
            }
            return 0;
        }

        function renderSummary(cart) {
            const el = document.getElementById('checkout-summary-block');
            if (!cart.total) return;

            console.log('renderSummary called, cart.total:', JSON.stringify(cart.total));

            // Subtotal (before discounts)
            const sumStr = cart.total.sum_string || cart.total.base_string || cart.total.final_string;

            let html = `<div class="uk-flex uk-flex-between"><span>Товары (${cart.total.quantity}):</span> <span>${sumStr}</span></div>`;

            // Promo code discount (from plugins.bonuses if available)
            if (cart.plugins?.bonuses?.codes_discount_string) {
                html += `<div class="uk-flex uk-flex-between uk-text-success"><span>Промокод:</span> <span>-${cart.plugins.bonuses.codes_discount_string}</span></div>`;
            }

            // Points discount
            if (cart.plugins?.bonuses?.points_discount_string) {
                html += `<div class="uk-flex uk-flex-between uk-text-success"><span>Баллы:</span> <span>-${cart.plugins.bonuses.points_discount_string}</span></div>`;
            }

            // General discount (may include promo if not separated)
            if (cart.total.discount_string && !cart.plugins?.bonuses?.codes_discount_string) {
                html += `<div class="uk-flex uk-flex-between uk-text-success"><span>Скидка:</span> <span>-${cart.total.discount_string}</span></div>`;
            }

            // Shipping
            const shippingCost = parseNumber(cart.total.shipping);
            if (cart.total.shipping_string) {
                html += `<div class="uk-flex uk-flex-between"><span>Доставка:</span> <span>${cart.total.shipping_string}</span></div>`;
            }

            // Total = final (товары - скидка) + shipping
            const finalAmount = parseNumber(cart.total.final);
            const grandTotal = finalAmount + shippingCost;
            const grandTotalStr = grandTotal.toLocaleString('ru-RU') + ' ₽';

            console.log('renderSummary: finalAmount=', finalAmount, 'shippingCost=', shippingCost, 'grandTotal=', grandTotal);

            html += `<div class="checkout-summary uk-flex uk-flex-between uk-margin-small-top" style="font-size:1.2em;font-weight:bold;border-top:1px solid #eee;padding-top:8px;"><span>Итого:</span> <span>${grandTotalStr}</span></div>`;

            el.innerHTML = html;
        }

        let mapInstance = null;
        let mapObjectManager = null;
        let defaultShippingId = 0;
        let providerToShippingId = {}; // Mapping: provider -> shipping_id
        let selectedPvzProvider = '';   // Currently selected PVZ provider
        let activeProviderFilters = new Set(); // Active provider filters (empty = all)
        let allPvzFeatures = []; // Cache all loaded PVZ features
        let availableProviders = {}; // Available providers with names: { code: name }
        let hidePostamats = true; // Hide postamats by default (pvz_type === '2')
        let boundsChangeDebounceTimer = null; // Debounce timer for map bounds change
        const BOUNDS_CHANGE_DEBOUNCE_MS = 5000; // 5 seconds debounce for tariff calculation

        // Tariff cache in sessionStorage
        const TARIFF_CACHE_KEY = 'rmt_tariff_cache';
        const TARIFF_CACHE_TTL = 30 * 60 * 1000; // 30 minutes
        let tariffFetchQueue = []; // Queue of {id, provider} objects to fetch tariffs
        let tariffFetchInProgress = false;

        function getTariffCache() {
            try {
                const raw = sessionStorage.getItem(TARIFF_CACHE_KEY);
                if (!raw) return {};
                const data = JSON.parse(raw);
                // Check TTL
                if (data._ts && Date.now() - data._ts > TARIFF_CACHE_TTL) {
                    sessionStorage.removeItem(TARIFF_CACHE_KEY);
                    return {};
                }
                return data;
            } catch(e) { return {}; }
        }

        function setTariffCache(cache) {
            try {
                cache._ts = Date.now();
                sessionStorage.setItem(TARIFF_CACHE_KEY, JSON.stringify(cache));
            } catch(e) {}
        }

        function getCachedTariff(pvzId) {
            const cache = getTariffCache();
            return cache[pvzId] || null;
        }

        function setCachedTariff(pvzId, data) {
            const cache = getTariffCache();
            cache[pvzId] = data;
            setTariffCache(cache);
        }

        // Queue tariff fetching for PVZ points
        // pvzItems: array of {id, provider} objects
        function queueTariffFetch(pvzItems) {
            const cache = getTariffCache();
            const newItems = pvzItems.filter(item =>
                !cache[item.id] && !tariffFetchQueue.find(q => q.id === item.id)
            );
            if (newItems.length > 0) {
                console.log(`[Tariff] Queued ${newItems.length} PVZ for tariff calculation:`, newItems.slice(0, 5).map(i => i.id).join(', ') + (newItems.length > 5 ? '...' : ''));
            }
            tariffFetchQueue.push(...newItems);
            processTariffQueue();
        }

        async function processTariffQueue() {
            if (tariffFetchInProgress || tariffFetchQueue.length === 0) return;

            // Check if providerToShippingId mapping is loaded
            if (Object.keys(providerToShippingId).length === 0) {
                console.warn('[Tariff] providerToShippingId is empty, using defaultShippingId for all providers');
            }

            tariffFetchInProgress = true;

            // Group queue items by provider
            const byProvider = {};
            tariffFetchQueue.forEach(item => {
                const provider = item.provider || 'unknown';
                if (!byProvider[provider]) byProvider[provider] = [];
                byProvider[provider].push(item);
            });

            // Process one provider at a time (max 20 per batch)
            const providerKeys = Object.keys(byProvider);
            const currentProvider = providerKeys[0];
            const providerItems = byProvider[currentProvider];
            const batch = providerItems.splice(0, 20);

            // Remove processed items from main queue
            batch.forEach(batchItem => {
                const idx = tariffFetchQueue.findIndex(q => q.id === batchItem.id);
                if (idx !== -1) tariffFetchQueue.splice(idx, 1);
            });

            // Get shipping_id for this provider
            const shippingIdForBatch = providerToShippingId[currentProvider] || defaultShippingId;
            console.log(`[Tariff] Fetching batch of ${batch.length} PVZ for provider ${currentProvider}, shipping_id=${shippingIdForBatch}, remaining: ${tariffFetchQueue.length}`);

            try {
                const res = await api('tariffs', {
                    pvz_ids: batch.map(b => b.id).join(','),
                    shipping_id: shippingIdForBatch
                });

                if (res.results) {
                    let withTariff = 0, noTariff = 0;
                    for (const [pvzId, data] of Object.entries(res.results)) {
                        setCachedTariff(pvzId, data);
                        if (data && data.min_price !== undefined) {
                            withTariff++;
                            console.log(`[Tariff] ✓ ${pvzId}: от ${data.min_price}₽ (${data.provider || currentProvider})`);
                        } else if (data && data.error) {
                            noTariff++;
                            // Show FULL debug info for failed tariffs
                            const debugInfo = data._debug || {};
                            console.warn(`[Tariff] ✗ ${pvzId} (${data.provider || currentProvider}): ${data.error}`);
                            console.warn(`[Tariff]   FULL DEBUG:`, debugInfo);
                            if (debugInfo.sender_keys) {
                                console.warn(`[Tariff]   Sender keys in config: ${debugInfo.sender_keys.join(', ')}`);
                            }
                            if (debugInfo.request_data) {
                                console.warn(`[Tariff]   Request data:`, debugInfo.request_data);
                            }
                            if (debugInfo.full_response) {
                                console.warn(`[Tariff]   Full API response:`, debugInfo.full_response);
                            }
                            // Show full content of deliveryToPoint[0] if exists
                            if (debugInfo.delivery_to_point_0) {
                                console.warn(`[Tariff]   deliveryToPoint[0] FULL:`, debugInfo.delivery_to_point_0);
                            }
                        } else {
                            noTariff++;
                            console.log(`[Tariff] ✗ ${pvzId}: no data`);
                        }
                        // Update map feature with price
                        updateFeatureWithPrice(pvzId, data);
                    }
                    console.log(`[Tariff] Batch complete for ${currentProvider}: ${withTariff} with tariff, ${noTariff} without`);

                    // Refresh map after batch to hide inactive PVZ
                    if (noTariff > 0 && hideInactivePvz) {
                        applyProviderFilter();
                        console.log('[Tariff] Map refreshed (hidden inactive PVZ)');
                    }
                }
            } catch(e) {
                console.error('[Tariff] Fetch error:', e);
            }

            tariffFetchInProgress = false;

            // Continue processing queue
            if (tariffFetchQueue.length > 0) {
                setTimeout(processTariffQueue, 100);
            }
        }

        // Update map feature with tariff price
        function updateFeatureWithPrice(pvzId, tariffData) {
            if (!mapObjectManager) return;

            const feature = allPvzFeatures.find(f => f.id === pvzId);
            if (!feature) return;

            if (tariffData && tariffData.min_price !== undefined) {
                feature.properties._minPrice = tariffData.min_price;
                feature.properties._hasTariff = true;

                // Update balloon content with price
                const p = feature.properties.data;
                if (p) {
                    const iconHref = pvzIcons[p.provider] || '';
                    const providerIcon = iconHref ? `<img src="${iconHref}" alt="${p.provider_name || p.provider}" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;">` : '';
                    const providerName = p.provider_name || p.provider || '';
                    const typeLabel = p.pvz_type === '2' ? 'Постамат' : 'Пункт выдачи заказов';
                    const typeClass = p.pvz_type === '2' ? 'postamat' : 'pvz';
                    const priceLabel = `<div style="margin-bottom:8px;color:#4CAF50;font-weight:600;">Доставка от ${tariffData.min_price} ₽</div>`;

                    feature.properties.balloonContentBody = `
                        <div style="font-weight:600;font-size:14px;margin-bottom:8px;">${p.title}</div>
                        <div style="margin-bottom: 10px; display: flex; align-items: center;">
                            ${providerIcon}
                            <span style="font-weight: 500;">${providerName}</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span class="pvz-type-label ${typeClass}">${typeLabel}</span>
                        </div>
                        ${priceLabel}
                        <div style="margin-bottom: 10px; color: #666;">${p.address}</div>
                        <button class="uk-button uk-button-small uk-button-primary select-pvz-btn" onclick="event.stopPropagation(); handlePvzSelect('${p.id}')">Выбрать этот пункт</button>
                    `;

                    // Update hint with price
                    feature.properties.hintContent = p.title + (p.provider_name ? ' (' + p.provider_name + ')' : '') + ' — от ' + tariffData.min_price + ' ₽';
                }

                // Update info panel if this PVZ is currently previewed
                if (window._previewPvzId === pvzId) {
                    const obj = mapObjectManager.objects.getById(pvzId);
                    if (obj) showPvzInfo(obj);
                }
            } else {
                feature.properties._minPrice = null;
                feature.properties._hasTariff = false;
                // Mark as inactive and hide if setting enabled
                if (hideInactivePvz) {
                    feature.properties._inactive = true;
                    console.log(`[Tariff] Marked PVZ ${pvzId} as inactive (no tariff)`);
                    // Will be hidden on next applyProviderFilter call
                } else {
                    console.log(`[Tariff] No tariff for PVZ ${pvzId} (hiding disabled)`);
                }
            }

            // DON'T refresh map here - wait until batch is complete to avoid balloon issues
            // applyProviderFilter(true);
        }

        // Handle PVZ selection from balloon button
        function initMap() {
            if (mapInstance || typeof ymaps === 'undefined') return;

            // Show loading state on map container
            const mapContainer = document.getElementById('shipping-map-container');
            mapContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;"><div uk-spinner="ratio: 2"></div><span style="margin-left:10px;">Определение местоположения...</span></div>';

            ymaps.ready(() => {
                // First, try to get user's location
                getUserLocation().then(userCoords => {
                    createMap(userCoords);
                }).catch(() => {
                    // Fallback to Moscow if geolocation fails
                    createMap([55.76, 37.64]);
                });
            });
        }

        function getUserLocation() {
            return new Promise((resolve, reject) => {
                // Try Telegram WebApp location first (if available)
                if (window.Telegram?.WebApp?.LocationManager) {
                    try {
                        Telegram.WebApp.LocationManager.getLocation((location) => {
                            if (location && location.latitude && location.longitude) {
                                console.log('[Map] Got location from Telegram:', location);
                                resolve([location.latitude, location.longitude]);
                                return;
                            }
                            // Fall through to browser geolocation
                            getBrowserLocation().then(resolve).catch(reject);
                        });
                        return;
                    } catch(e) {
                        console.log('[Map] Telegram location not available:', e);
                    }
                }

                // Try browser geolocation
                getBrowserLocation().then(resolve).catch(reject);
            });
        }

        function getBrowserLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    // Try Yandex geolocation by IP as fallback
                    ymaps.geolocation.get({ provider: 'yandex' }).then(result => {
                        const coords = result.geoObjects.get(0).geometry.getCoordinates();
                        console.log('[Map] Got location from Yandex IP:', coords);
                        resolve(coords);
                    }).catch(reject);
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        console.log('[Map] Got location from browser:', pos.coords);
                        resolve([pos.coords.latitude, pos.coords.longitude]);
                    },
                    (err) => {
                        console.log('[Map] Browser geolocation error:', err.message);
                        // Try Yandex geolocation by IP as fallback
                        ymaps.geolocation.get({ provider: 'yandex' }).then(result => {
                            const coords = result.geoObjects.get(0).geometry.getCoordinates();
                            console.log('[Map] Got location from Yandex IP:', coords);
                            resolve(coords);
                        }).catch(reject);
                    },
                    { enableHighAccuracy: false, timeout: 5000, maximumAge: 300000 }
                );
            });
        }

        function createMap(centerCoords) {
            const mapContainer = document.getElementById('shipping-map-container');
            mapContainer.innerHTML = ''; // Clear loading state

            mapInstance = new ymaps.Map('shipping-map-container', {
                center: centerCoords,
                zoom: 12, // Closer zoom for user's location
                controls: ['zoomControl', 'geolocationControl']
            });

            mapObjectManager = new ymaps.ObjectManager({
                clusterize: true,
                gridSize: 32,
                clusterDisableClickZoom: false,
                // Disable balloons - we show info below the map
                geoObjectOpenBalloonOnClick: false,
                clusterOpenBalloonOnClick: false
            });
            mapInstance.geoObjects.add(mapObjectManager);

            // Handle click on PVZ point - show info below map
            mapObjectManager.objects.events.add('click', (e) => {
                const objectId = e.get('objectId');
                const obj = mapObjectManager.objects.getById(objectId);
                if (obj) {
                    showPvzInfo(obj);
                }
            });

            mapInstance.events.add('boundschange', (e) => {
                // Debounce: load PVZ immediately, but delay tariff calculation
                const newBounds = e.get('newBounds');
                fetchPvzWithoutTariffs(newBounds);

                // Clear previous timer
                if (boundsChangeDebounceTimer) {
                    clearTimeout(boundsChangeDebounceTimer);
                }

                // Set new timer for tariff calculation after 5 seconds of no map movement
                boundsChangeDebounceTimer = setTimeout(() => {
                    console.log('[Map] Debounce complete, processing tariff queue...');
                    processTariffQueue();
                }, BOUNDS_CHANGE_DEBOUNCE_MS);
            });

            // Initial fetch
            fetchPvz(mapInstance.getBounds());

            // Add user location marker
            const userPlacemark = new ymaps.Placemark(centerCoords, {
                hintContent: 'Вы здесь'
            }, {
                preset: 'islands#blueCircleIcon'
            });
            mapInstance.geoObjects.add(userPlacemark);
        }
        async function fetchPvz(bounds) {
            if (!bounds) return;
            // Send bbox as lon1,lat1,lon2,lat2 (Yandex returns [lat,lon])
            const bbox = [bounds[0][1], bounds[0][0], bounds[1][1], bounds[1][0]].join(',');
            try {
                const res = await api('pvz', { bbox: bbox, limit: 500 });
                if (res.items) {
                    // Collect available providers
                    res.items.forEach(p => {
                        if (p.provider && !availableProviders[p.provider]) {
                            availableProviders[p.provider] = p.provider_name || p.provider;
                        }
                    });
                    updateProviderFilters();

                    const features = res.items.map(p => {
                        // Skip postamats if hidePostamats is enabled
                        if (hidePostamats && p.pvz_type === '2') {
                            return null;
                        }

                        // Get icon by provider (if configured)
                        const iconHref = pvzIcons[p.provider] || '';

                        // Check cached tariff for this PVZ
                        const cachedTariff = getCachedTariff(p.id);
                        const minPrice = (cachedTariff && cachedTariff.min_price !== undefined) ? cachedTariff.min_price : null;
                        const priceLabel = minPrice !== null ? `<div style="margin-bottom:8px;color:#4CAF50;font-weight:600;">Доставка от ${minPrice} ₽</div>` : '';

                        // Build balloon content with provider icon and type info
                        const providerIcon = iconHref ? `<img src="${iconHref}" alt="${p.provider_name || p.provider}" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;">` : '';
                        const providerName = p.provider_name || p.provider || '';
                        const typeLabel = p.pvz_type === '2' ? 'Постамат' : 'Пункт выдачи заказов';
                        const typeClass = p.pvz_type === '2' ? 'postamat' : 'pvz';

                        // Build complete balloon content (header + body in one, no standard footer)
                        const balloonContent = `
                            <div style="font-weight:600;font-size:14px;margin-bottom:8px;">${p.title}</div>
                            <div style="margin-bottom: 10px; display: flex; align-items: center;">
                                ${providerIcon}
                                <span style="font-weight: 500;">${providerName}</span>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <span class="pvz-type-label ${typeClass}">${typeLabel}</span>
                            </div>
                            ${priceLabel}
                            <div style="margin-bottom: 10px; color: #666;">${p.address}</div>
                            <button class="uk-button uk-button-small uk-button-primary select-pvz-btn" onclick="event.stopPropagation(); handlePvzSelect('${p.id}')">Выбрать этот пункт</button>
                        `;

                        // Build hint with price if available
                        let hintText = p.title + (p.provider_name ? ' (' + p.provider_name + ')' : '');
                        if (minPrice !== null) {
                            hintText += ' — от ' + minPrice + ' ₽';
                        }

                        const feature = {
                            type: 'Feature',
                            id: p.id,
                            geometry: { type: 'Point', coordinates: [p.lat, p.lon] },
                            properties: {
                                balloonContentBody: balloonContent,
                                hintContent: hintText,
                                data: p,
                                _minPrice: minPrice,
                                _hasTariff: minPrice !== null,
                                _inactive: cachedTariff === null ? false : (cachedTariff && !cachedTariff.tariffs)
                            }
                        };

                        // Add custom icon if available
                        if (iconHref) {
                            feature.options = {
                                iconLayout: 'default#image',
                                iconImageHref: iconHref,
                                iconImageSize: [32, 32],
                                iconImageOffset: [-16, -32]
                            };
                        }

                        return feature;
                    });

                    // Cache features with deduplication by id (filter out nulls from postamats)
                    const validFeatures = features.filter(f => f !== null);
                    const existingIds = new Set(allPvzFeatures.map(f => f.id));
                    const newFeatures = validFeatures.filter(f => !existingIds.has(f.id));
                    allPvzFeatures = allPvzFeatures.concat(newFeatures);
                    applyProviderFilter();

                    // Queue tariff fetching for new PVZ points (with provider info)
                    if (newFeatures.length > 0) {
                        const newItems = newFeatures.map(f => ({
                            id: f.id,
                            provider: f.properties.data?.provider || 'unknown'
                        }));
                        queueTariffFetch(newItems);
                    }
                }
            } catch(e) { console.error(e); }
        }

        // Fetch PVZ without triggering tariff calculation (for debounced map movement)
        async function fetchPvzWithoutTariffs(bounds) {
            if (!bounds) return;
            // Send bbox as lon1,lat1,lon2,lat2 (Yandex returns [lat,lon])
            const bbox = [bounds[0][1], bounds[0][0], bounds[1][1], bounds[1][0]].join(',');
            try {
                const res = await api('pvz', { bbox: bbox, limit: 500 });
                if (res.items) {
                    // Collect available providers
                    res.items.forEach(p => {
                        if (p.provider && !availableProviders[p.provider]) {
                            availableProviders[p.provider] = p.provider_name || p.provider;
                        }
                    });
                    updateProviderFilters();

                    const features = res.items.map(p => {
                        // Skip postamats if hidePostamats is enabled
                        if (hidePostamats && p.pvz_type === '2') {
                            return null;
                        }

                        // Get icon by provider (if configured)
                        const iconHref = pvzIcons[p.provider] || '';

                        // Check cached tariff for this PVZ
                        const cachedTariff = getCachedTariff(p.id);
                        const minPrice = (cachedTariff && cachedTariff.min_price !== undefined) ? cachedTariff.min_price : null;
                        const priceLabel = minPrice !== null ? `<div style="margin-bottom:8px;color:#4CAF50;font-weight:600;">Доставка от ${minPrice} ₽</div>` : '';

                        // Build balloon content with provider icon and type info
                        const providerIcon = iconHref ? `<img src="${iconHref}" alt="${p.provider_name || p.provider}" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;">` : '';
                        const providerName = p.provider_name || p.provider || '';
                        const typeLabel = p.pvz_type === '2' ? 'Постамат' : 'Пункт выдачи заказов';
                        const typeClass = p.pvz_type === '2' ? 'postamat' : 'pvz';

                        // Build complete balloon content (header + body in one, no standard footer)
                        const balloonContent = `
                            <div style="font-weight:600;font-size:14px;margin-bottom:8px;">${p.title}</div>
                            <div style="margin-bottom: 10px; display: flex; align-items: center;">
                                ${providerIcon}
                                <span style="font-weight: 500;">${providerName}</span>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <span class="pvz-type-label ${typeClass}">${typeLabel}</span>
                            </div>
                            ${priceLabel}
                            <div style="margin-bottom: 10px; color: #666;">${p.address}</div>
                            <button class="uk-button uk-button-small uk-button-primary select-pvz-btn" onclick="event.stopPropagation(); handlePvzSelect('${p.id}')">Выбрать этот пункт</button>
                        `;

                        // Build hint with price if available
                        let hintText = p.title + (p.provider_name ? ' (' + p.provider_name + ')' : '');
                        if (minPrice !== null) {
                            hintText += ' — от ' + minPrice + ' ₽';
                        }

                        const feature = {
                            type: 'Feature',
                            id: p.id,
                            geometry: { type: 'Point', coordinates: [p.lat, p.lon] },
                            properties: {
                                balloonContentBody: balloonContent,
                                hintContent: hintText,
                                data: p,
                                _minPrice: minPrice,
                                _hasTariff: minPrice !== null,
                                _inactive: cachedTariff === null ? false : (cachedTariff && !cachedTariff.tariffs)
                            }
                        };

                        // Add custom icon if available
                        if (iconHref) {
                            feature.options = {
                                iconLayout: 'default#image',
                                iconImageHref: iconHref,
                                iconImageSize: [32, 32],
                                iconImageOffset: [-16, -32]
                            };
                        }

                        return feature;
                    });

                    // Cache features with deduplication by id (filter out nulls from postamats)
                    const validFeatures = features.filter(f => f !== null);
                    const existingIds = new Set(allPvzFeatures.map(f => f.id));
                    const newFeatures = validFeatures.filter(f => !existingIds.has(f.id));
                    allPvzFeatures = allPvzFeatures.concat(newFeatures);
                    applyProviderFilter();

                    // NOTE: Unlike fetchPvz(), we do NOT queue tariff fetching here
                    // Tariffs will be fetched after debounce timer completes
                    if (newFeatures.length > 0) {
                        // Just add to queue without processing (processTariffQueue will be called by debounce timer)
                        const newItems = newFeatures.map(f => ({
                            id: f.id,
                            provider: f.properties.data?.provider || 'unknown'
                        }));
                        // Add to queue but don't process
                        const cache = getTariffCache();
                        const itemsToQueue = newItems.filter(item =>
                            !cache[item.id] && !tariffFetchQueue.find(q => q.id === item.id)
                        );
                        if (itemsToQueue.length > 0) {
                            console.log(`[Tariff] Queued ${itemsToQueue.length} PVZ (debounced, waiting for map to stop)`);
                            tariffFetchQueue.push(...itemsToQueue);
                        }
                    }
                }
            } catch(e) { console.error(e); }
        }

        function applyProviderFilter(forceRefresh = false) {
            if (!mapObjectManager) return;

            // Filter features by active providers and exclude inactive
            let filteredFeatures = allPvzFeatures.filter(f => {
                // Skip inactive PVZ (no tariffs)
                if (f.properties._inactive === true) return false;
                return true;
            });

            if (activeProviderFilters.size > 0) {
                filteredFeatures = filteredFeatures.filter(f =>
                    activeProviderFilters.has(f.properties.data.provider)
                );
            }

            // Clear current objects and add filtered
            mapObjectManager.removeAll();
            if (filteredFeatures.length > 0) {
                mapObjectManager.add({ type: 'FeatureCollection', features: filteredFeatures });
            }
            console.log(`[Map] Refreshed: ${filteredFeatures.length} features`);
        }

        // Show PVZ info in panel below map (instead of balloon)
        function showPvzInfo(obj) {
            const p = obj.properties.data;
            if (!p) return;

            const infoPanel = document.getElementById('pvz-info-panel');
            const iconHref = pvzIcons[p.provider] || '';
            const providerIcon = iconHref ? `<img src="${iconHref}" alt="${p.provider_name || p.provider}" style="width:28px;height:28px;vertical-align:middle;margin-right:8px;">` : '';
            const providerName = p.provider_name || p.provider || '';
            const typeLabel = p.pvz_type === '2' ? 'Постамат' : 'Пункт выдачи';
            const typeClass = p.pvz_type === '2' ? 'postamat' : 'pvz';

            // Get cached tariff
            const cachedTariff = getCachedTariff(p.id);
            const minPrice = cachedTariff?.min_price;
            const priceHtml = minPrice !== undefined
                ? `<div class="pvz-price">Доставка от <strong>${minPrice} ₽</strong></div>`
                : `<div class="pvz-price pvz-price-loading">Расчёт стоимости...</div>`;

            infoPanel.innerHTML = `
                <div class="pvz-info-header">
                    <div class="pvz-provider">${providerIcon}<span>${providerName}</span></div>
                    <span class="pvz-type-badge ${typeClass}">${typeLabel}</span>
                </div>
                <div class="pvz-title">${p.title}</div>
                <div class="pvz-address">${p.address}</div>
                ${priceHtml}
                <button type="button" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-top" onclick="confirmPvzSelection('${p.id}')">
                    Выбрать этот пункт
                </button>
            `;
            infoPanel.hidden = false;

            // Scroll to info panel
            infoPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Store current preview (not confirmed yet)
            window._previewPvzId = p.id;

            console.log(`[PVZ] Showing info for ${p.id}: ${p.title}`);
        }

        // Confirm PVZ selection (when user clicks "Select" button)
        window.confirmPvzSelection = function(pvzId) {
            const obj = mapObjectManager.objects.getById(pvzId);
            if (obj) {
                // Hide info panel
                const infoPanel = document.getElementById('pvz-info-panel');
                infoPanel.hidden = true;
                window._previewPvzId = null;

                // Select the PVZ
                selectPvz(obj);

                // Scroll to tariffs container
                setTimeout(() => {
                    const tariffsContainer = document.getElementById('tariffs-container');
                    if (tariffsContainer && !tariffsContainer.hidden) {
                        tariffsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        // If tariffs not shown yet, scroll to selected-pvz-info
                        const selectedInfo = document.getElementById('selected-pvz-info');
                        if (selectedInfo && !selectedInfo.hidden) {
                            selectedInfo.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                }, 100);
            }
        };

        function updateProviderFilters() {
            const container = document.getElementById('provider-filters');
            if (!container) return;

            // Check if we have any providers with icons
            const providersWithData = Object.keys(availableProviders).filter(code => pvzIcons[code]);
            if (providersWithData.length === 0) {
                container.hidden = true;
                return;
            }

            container.hidden = false;
            let html = '';

            // "All" button
            const allActive = activeProviderFilters.size === 0 ? 'active' : '';
            html += `<button type="button" class="provider-filter-btn ${allActive}" onclick="toggleProviderFilter('all')">
                <span class="filter-label">Все</span>
            </button>`;

            // Provider buttons (only those with icons)
            providersWithData.forEach(code => {
                const name = availableProviders[code];
                const icon = pvzIcons[code];
                const isActive = activeProviderFilters.has(code) ? 'active' : '';

                if (icon) {
                    html += `<button type="button" class="provider-filter-btn ${isActive}" onclick="toggleProviderFilter('${code}')" title="${name}">
                        <img src="${icon}" alt="${name}">
                    </button>`;
                } else {
                    html += `<button type="button" class="provider-filter-btn ${isActive}" onclick="toggleProviderFilter('${code}')">
                        <span class="filter-label">${name}</span>
                    </button>`;
                }
            });

            container.innerHTML = html;
        }

        function toggleProviderFilter(provider) {
            if (provider === 'all') {
                // Clear all filters - show all
                activeProviderFilters.clear();
            } else {
                if (activeProviderFilters.has(provider)) {
                    activeProviderFilters.delete(provider);
                } else {
                    activeProviderFilters.add(provider);
                }
            }

            updateProviderFilters();
            applyProviderFilter(true); // Force refresh when user changes filter
        }

        function selectPvz(obj) {
            const p = obj.properties.data;
            selectedPvzProvider = p.provider; // Save provider for later use
            document.getElementById('pvz-title').innerText = p.title;
            // Show provider and type info in address line
            let addressInfo = p.address;
            if (p.provider_name || p.pvz_type_name) {
                const meta = [p.provider_name, p.pvz_type_name].filter(Boolean).join(' • ');
                addressInfo = meta + '\n' + p.address;
            }
            document.getElementById('pvz-address').innerText = addressInfo;
            document.getElementById('selected-pvz-info').hidden = false;
            document.getElementById('pvz_id_input').value = p.id;
            document.getElementById('pvz_provider_input').value = p.provider;

            // Show loading state for tariffs
            const tariffsContainer = document.getElementById('tariffs-container');
            if (tariffsContainer) {
                tariffsContainer.innerHTML = '<div uk-spinner="ratio: 0.5"></div> Расчёт стоимости доставки...';
                tariffsContainer.hidden = false;
            }

            // Determine shipping_id by provider
            const shippingIdForProvider = providerToShippingId[p.provider] || defaultShippingId;
            console.log('[setpvz] Sending request:', { shipping_id: shippingIdForProvider, id: p.id, provider: p.provider, providerMapping: providerToShippingId });

            api('setpvz', {
                shipping_id: shippingIdForProvider,
                id: p.id,
                provider: p.provider,
                title: p.title,
                address: p.address,
                lat: p.lat,
                lon: p.lon,
                nonce: makeNonce()
            }).then(res => {
                console.log('[setpvz] Response:', res);

                // Update full summary block if order data available
                if (res.order) {
                    // Parse shipping value from string if not provided as number
                    if (res.order.total.shipping_string && !res.order.total.shipping) {
                        res.order.total.shipping = parseNumber(res.order.total.shipping_string);
                    }
                    renderSummary({ total: res.order.total, plugins: window.currentCart?.plugins });
                    // Save shipping info to currentCart for later use (e.g., after promo apply)
                    if (!window.currentCart) window.currentCart = { total: {} };
                    if (!window.currentCart.total) window.currentCart.total = {};
                    if (res.order.total.shipping_string) {
                        window.currentCart.total.shipping_string = res.order.total.shipping_string;
                        window.currentCart.total.shipping = parseNumber(res.order.total.shipping_string);
                    }
                } else if (res.order_total) {
                    // Fallback: just update total line
                    const el = document.querySelector('.checkout-summary span:last-child');
                    if(el) el.innerText = res.order_total;
                }

                // Render tariffs if available
                console.log('[setpvz] Tariffs count:', res.tariffs?.length || 0);
                renderTariffs(res.tariffs, res.selected_tariff);
            }).catch(e => {
                console.error('setpvz error:', e);
                if (tariffsContainer) {
                    tariffsContainer.innerHTML = '<span class="uk-text-warning">Не удалось рассчитать стоимость доставки</span>';
                }
            });
        }

        function renderTariffs(tariffs, selectedTariffId) {
            const container = document.getElementById('tariffs-container');
            if (!container) return;

            console.log('[renderTariffs] tariffs:', tariffs, 'selectedTariffId:', selectedTariffId);

            if (!tariffs || tariffs.length === 0) {
                container.innerHTML = '<span class="uk-text-muted">Тарифы недоступны</span>';
                return;
            }

            let html = '<div class="uk-margin-small-top"><strong>Выберите тариф доставки:</strong></div>';
            tariffs.forEach(t => {
                const checked = (selectedTariffId && String(t.tariffId) === String(selectedTariffId)) ? 'checked' : '';
                const price = t.deliveryCost ? t.deliveryCost + ' ₽' : '';
                const days = t.daysMin && t.daysMax ? `(${t.daysMin}-${t.daysMax} дн.)` : '';
                html += `
                    <label class="uk-display-block uk-margin-small-bottom">
                        <input class="uk-radio" type="radio" name="tariff_id" value="${t.tariffId}" ${checked} onchange="onTariffChange('${t.tariffId}')">
                        ${t.tariffName || 'Тариф'} ${price} ${days}
                    </label>
                `;
            });
            container.innerHTML = html;
            container.hidden = false;
        }

        async function onTariffChange(tariffId) {
            try {
                // Re-send setpvz with selected tariff
                const pvzId = document.getElementById('pvz_id_input').value;
                const pvzProvider = document.getElementById('pvz_provider_input').value;
                if (!pvzId) return;

                // Use correct shipping_id for the selected PVZ provider
                const shippingIdForProvider = providerToShippingId[selectedPvzProvider] || defaultShippingId;

                const res = await api('setpvz', {
                    shipping_id: shippingIdForProvider,
                    id: pvzId,
                    provider: pvzProvider,
                    tariff_id: tariffId,
                    nonce: makeNonce()
                });

                // Update full summary block if order data available
                if (res.order) {
                    // Parse shipping value from string if not provided as number
                    if (res.order.total.shipping_string && !res.order.total.shipping) {
                        res.order.total.shipping = parseNumber(res.order.total.shipping_string);
                    }
                    const orderData = { total: res.order.total, plugins: window.currentCart?.plugins };
                    renderSummary(orderData);
                    // Save shipping info to currentCart for later use (e.g., after promo apply)
                    if (!window.currentCart) window.currentCart = { total: {} };
                    if (!window.currentCart.total) window.currentCart.total = {};
                    if (res.order.total.shipping_string) {
                        window.currentCart.total.shipping_string = res.order.total.shipping_string;
                        window.currentCart.total.shipping = parseNumber(res.order.total.shipping_string);
                    }
                } else if (res.order_total) {
                    // Fallback: just update total line
                    const el = document.querySelector('.checkout-summary span:last-child');
                    if(el) el.innerText = res.order_total;
                }
            } catch(e) {
                console.error('onTariffChange error:', e);
                UIkit.notification('Ошибка выбора тарифа', {status:'danger'});
            }
        }

        function renderMethods(data) {
            // Store default shipping ID and build provider -> shipping_id mapping
            providerToShippingId = {};
            if (data.shipping && data.shipping.methods && data.shipping.methods.length > 0) {
                if (data.shipping.selected) {
                    defaultShippingId = data.shipping.selected;
                } else {
                    defaultShippingId = data.shipping.methods[0].id;
                }
                // Build provider to shipping_id mapping
                data.shipping.methods.forEach(m => {
                    if (m.providers && Array.isArray(m.providers)) {
                        m.providers.forEach(prov => {
                            providerToShippingId[prov] = m.id;
                        });
                    }
                });
                console.log('[renderMethods] providerToShippingId:', providerToShippingId);
            }

            // Payment
            const payContainer = document.getElementById('payment-methods');
            payContainer.innerHTML = '';
            if (data.payment && data.payment.methods) {
                data.payment.methods.forEach(m => {
                    if (m.disabled) return; // Skip disabled methods
                    const checked = (Number(data.payment.selected) === Number(m.id)) ? 'checked' : '';
                    const iconHtml = m.icon ? `<img src="${m.icon}" alt="${m.title}" style="width:32px;height:32px;object-fit:contain;margin-right:10px;vertical-align:middle;">` : '';
                    // Если есть иконка, не показываем текстовое название
                    const titleHtml = m.icon ? '' : `<span style="font-weight:500;">${m.title}</span>`;
                    const descHtml = m.description ? `<div class="uk-text-small uk-text-muted uk-margin-small-left" style="padding-left:42px;">${m.description}</div>` : '';
                    payContainer.innerHTML += `
                        <label class="uk-display-block uk-margin-small-bottom uk-padding-small" style="background:rgba(0,0,0,0.02);border-radius:8px;cursor:pointer;">
                            <div style="display:flex;align-items:center;">
                                <input class="uk-radio" type="radio" name="payment_id" value="${m.id}" ${checked} onchange="onPaymentChange(${m.id})" style="margin-right:10px;">
                                ${iconHtml}
                                ${titleHtml}
                            </div>
                            ${descHtml}
                        </label>
                    `;
                });
            }

            // Init map
            setTimeout(initMap, 100);
        }

        function prefillProfile(data) {
            if (data.user) {
                const u = data.user;
                if (u.name) {
                    const parts = u.name.split(' ');
                    if (parts[0]) document.querySelector('[name="first_name"]').value = parts[0];
                    if (parts[1]) document.querySelector('[name="last_name"]').value = parts[1];
                }
                if (u.phone) document.querySelector('[name="phone"]').value = u.phone;
                if (u.email) document.querySelector('[name="email"]').value = u.email;
            }
        }

        async function onShippingChange(id) {
            try {
                const res = await api('setshipping', { id, nonce: makeNonce() });
                if (res.cart) renderSummary(res.cart);
                if (res.payment) renderMethods({ shipping: { methods: [], selected: id }, payment: res.payment }); // Update payment methods if dependent
            } catch(e) {
                UIkit.notification('Ошибка выбора доставки', {status:'danger'});
            }
        }

        async function onPaymentChange(id) {
            try {
                await api('setpayment', { id, nonce: makeNonce() });
            } catch(e) {
                UIkit.notification('Ошибка выбора оплаты', {status:'danger'});
            }
        }

        async function submitOrder() {
            const btn = document.getElementById('submit-btn');
            const form = document.getElementById('checkout-form-el');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Validate PVZ is selected
            const pvzId = document.getElementById('pvz_id_input').value;
            if (!pvzId) {
                UIkit.notification('Выберите пункт выдачи заказа', {status:'warning'});
                return;
            }

            // Validate tariff is selected (if there are multiple)
            const tariffRadio = document.querySelector('input[name="tariff_id"]:checked');
            const tariffsContainer = document.getElementById('tariffs-container');
            if (tariffsContainer && !tariffsContainer.hidden && !tariffRadio) {
                UIkit.notification('Выберите тариф доставки', {status:'warning'});
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            data.action = 'create';
            data.nonce = makeNonce();

            try {
                btn.disabled = true;
                btn.innerHTML = '<div uk-spinner="ratio: 0.5"></div> Создание заказа...';

                const res = await api('checkout', data);

                if (res.pay_url) {
                    // Redirect to payment page
                    btn.innerHTML = '<div uk-spinner=\"ratio: 0.5\"></div> Переход к оплате...';
                    window.location.href = res.pay_url;
                } else if (res.html) {
                    // Show payment form HTML
                    document.open();
                    document.write(res.html);
                    document.close();
                } else if (res.redirect) {
                    window.location.href = res.redirect;
                } else if (res.order_number) {
                    // Order created but no payment needed (e.g. cash on delivery)
                    UIkit.modal.alert(`Заказ №${res.order_number} успешно создан!`).then(() => {
                        window.location.href = '<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=orders';
                    });
                } else {
                    UIkit.modal.alert('Заказ успешно создан!').then(() => {
                        window.location.href = '<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=orders';
                    });
                }

            } catch(e) {
                console.error(e);
                UIkit.notification(e.message || 'Ошибка оформления заказа', {status:'danger'});
                btn.disabled = false;
                btn.innerText = 'Оплатить';
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

                    // BackButton - navigate to cart
                    try {
                        Telegram.WebApp.BackButton.show();
                        Telegram.WebApp.BackButton.onClick(function() {
                            window.location.href = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart' + (window.TG_CHAT_ID ? '&chat=' + window.TG_CHAT_ID : '');
                        });
                    } catch(e) { console.log('BackButton error:', e); }

                    const tgUser = Telegram.WebApp.initDataUnsafe?.user;
                    const chatId = tgUser?.id;
                    console.log('[Checkout] TG User:', tgUser, 'chatId:', chatId);

                    // Store globally
                    window.TG_CHAT_ID = chatId || 0;
                    window.TG_USER = tgUser || null;

                    // Update navigation links with chat param
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
            } catch(e) { console.log('[Checkout] TG error:', e); }

            initTheme();
            RMT_FORCE_UKIT_ICONS();
            RMT_OBSERVE_ICONS();
            loadCheckout();
            initBonuses();
        });

        // ========== BONUSES (POINTS & PROMO) ==========
        function initBonuses() {
            const pointsBlock = document.getElementById('points-block');
            const pointsInput = document.getElementById('points-input');
            const pointsMinus = document.getElementById('points-minus');
            const pointsPlus = document.getElementById('points-plus');
            const useAllPoints = document.getElementById('use-all-points');
            const applyPointsBtn = document.getElementById('apply-points-btn');
            const promoInput = document.getElementById('promo-code-input');
            const applyPromoBtn = document.getElementById('apply-promo-btn');

            if (!pointsBlock) return;

            const maxPoints = parseInt(pointsBlock.dataset.points) || 0;

            // Points +/- buttons
            if (pointsMinus && pointsInput) {
                pointsMinus.addEventListener('click', function() {
                    let val = parseInt(pointsInput.value) || 0;
                    val = Math.max(0, val - 10);
                    pointsInput.value = val;
                    useAllPoints.checked = (val >= maxPoints);
                });
            }

            if (pointsPlus && pointsInput) {
                pointsPlus.addEventListener('click', function() {
                    let val = parseInt(pointsInput.value) || 0;
                    val = Math.min(maxPoints, val + 10);
                    pointsInput.value = val;
                    useAllPoints.checked = (val >= maxPoints);
                });
            }

            // Use all points checkbox
            if (useAllPoints && pointsInput) {
                useAllPoints.addEventListener('change', function() {
                    if (this.checked) {
                        pointsInput.value = maxPoints;
                    } else {
                        pointsInput.value = 0;
                    }
                });
            }

            // Points input manual change
            if (pointsInput) {
                pointsInput.addEventListener('change', function() {
                    let val = parseInt(this.value) || 0;
                    val = Math.max(0, Math.min(maxPoints, val));
                    this.value = val;
                    useAllPoints.checked = (val >= maxPoints);
                });
            }

            // Apply points button
            if (applyPointsBtn) {
                applyPointsBtn.addEventListener('click', applyPoints);
            }

            // Apply promo button
            if (applyPromoBtn) {
                applyPromoBtn.addEventListener('click', applyPromo);
            }
        }

        // Apply points via AJAX
        async function applyPoints() {
            const pointsInput = document.getElementById('points-input');
            const pointsResult = document.getElementById('points-result');
            const points = parseInt(pointsInput?.value) || 0;

            try {
                pointsResult.hidden = true;

                const chatId = window.TG_CHAT_ID || qs('chat') || 0;
                const url = `<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&task=checkout.applyPoints&format=json&chat=${chatId}&points=${points}`;

                const res = await fetch(url).then(r => r.json());

                if (res.success) {
                    pointsResult.className = 'uk-alert uk-alert-success uk-padding-small';
                    pointsResult.innerHTML = res.message || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_APPLIED'); ?>';
                    pointsResult.hidden = false;

                    // Update summary if we got new totals
                    if (res.cart) {
                        renderSummary(res.cart);
                    }
                } else {
                    pointsResult.className = 'uk-alert uk-alert-danger uk-padding-small';
                    pointsResult.innerHTML = res.message || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ERROR'); ?>';
                    pointsResult.hidden = false;
                }
            } catch(e) {
                console.error('Apply points error:', e);
                pointsResult.className = 'uk-alert uk-alert-danger uk-padding-small';
                pointsResult.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ERROR'); ?>';
                pointsResult.hidden = false;
            }
        }

        // Apply promo code via AJAX
        async function applyPromo() {
            const promoInput = document.getElementById('promo-code-input');
            const promoResult = document.getElementById('promo-result');
            const applyBtn = document.getElementById('apply-promo-btn');
            const code = promoInput?.value?.trim() || '';

            if (!code) {
                promoResult.className = 'uk-alert uk-alert-danger uk-padding-small';
                promoResult.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_REQUIRED'); ?>';
                promoResult.hidden = false;
                return;
            }

            try {
                promoResult.hidden = true;
                if (applyBtn) {
                    applyBtn.disabled = true;
                    applyBtn.innerHTML = '<span uk-spinner="ratio: 0.5"></span>';
                }

                const chatId = window.TG_CHAT_ID || qs('chat') || 0;
                const url = `<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&task=api.applyPromo&format=json&chat=${chatId}&code=${encodeURIComponent(code)}`;

                const res = await fetch(url).then(r => r.json());

                // Handle Joomla JsonResponse format: {success: true, data: {success: false, ...}}
                const result = res.data || res;
                const isSuccess = result.success === true;

                if (isSuccess) {
                    // Build detailed message with discount info from response
                    let msg = '<strong>✓ Промокод применён!</strong>';
                    if (result.discount_string) {
                        msg += `<br><span class="uk-text-bold">Скидка: -${result.discount_string}</span>`;
                    } else if (result.discount && result.discount > 0) {
                        const discountType = result.discount_type === 'percent' ? '%' : ' ₽';
                        msg += `<br><span class="uk-text-bold">Скидка: -${result.discount}${discountType}</span>`;
                    }
                    msg += `<br><span class="uk-text-muted uk-text-small">Код: ${code}</span>`;

                    promoResult.className = 'uk-alert uk-alert-success uk-padding-small';
                    promoResult.innerHTML = msg;
                    promoResult.hidden = false;

                    // Disable input and change button
                    if (promoInput) promoInput.disabled = true;
                    if (applyBtn) {
                        applyBtn.innerHTML = '✓';
                        applyBtn.disabled = true;
                        applyBtn.classList.remove('uk-button-default');
                        applyBtn.classList.add('uk-button-success');
                    }

                    // Reload cart to get updated totals with promo discount
                    try {
                        // Save current shipping info before reload
                        const savedShipping = window.currentCart?.total?.shipping_string || null;
                        const savedShippingValue = parseFloat(window.currentCart?.total?.shipping) || 0;

                        console.log('Before cart reload: savedShipping=', savedShipping, 'savedShippingValue=', savedShippingValue);

                        const cartRes = await api('cart');
                        if (cartRes && cartRes.cart) {
                            // Preserve shipping info if it was selected before
                            if (savedShippingValue > 0) {
                                cartRes.cart.total.shipping_string = savedShipping || (savedShippingValue.toLocaleString('ru-RU') + ' ₽');
                                cartRes.cart.total.shipping = savedShippingValue;
                                console.log('Restored shipping:', cartRes.cart.total.shipping, cartRes.cart.total.shipping_string);
                            }
                            window.currentCart = cartRes.cart;
                            renderSummary(cartRes.cart);
                        }
                    } catch(e) {
                        console.error('Failed to reload cart after promo:', e);
                    }
                } else {
                    promoResult.className = 'uk-alert uk-alert-danger uk-padding-small';
                    promoResult.innerHTML = result.message || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_NOT_FOUND'); ?>';
                    promoResult.hidden = false;
                    if (applyBtn) {
                        applyBtn.disabled = false;
                        applyBtn.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?>';
                    }
                }
            } catch(e) {
                console.error('Apply promo error:', e);
                promoResult.className = 'uk-alert uk-alert-danger uk-padding-small';
                promoResult.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ERROR'); ?>';
                promoResult.hidden = false;
                if (applyBtn) {
                    applyBtn.disabled = false;
                    applyBtn.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?>';
                }
            }
        }
        // ========== END BONUSES ==========
    </script>
</head>
<body>

<div class="uk-container uk-padding-small">
    <h1 class="uk-h2 uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHECKOUT'); ?></h1>

    <div id="checkout-loading" class="uk-flex uk-flex-center uk-margin-large-top">
        <div uk-spinner="ratio: 2"></div>
    </div>

    <div id="checkout-empty" class="uk-text-center uk-margin-large-top" hidden>
        <div uk-icon="icon: cart; ratio: 3" class="uk-text-muted"></div>
        <p class="uk-text-lead"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART_EMPTY'); ?></p>
        <a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>index.php?option=com_radicalmart_telegram&view=app" class="uk-button uk-button-primary uk-margin-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_GO_CATALOG'); ?></a>
    </div>

    <div id="checkout-form" hidden>
        <form id="checkout-form-el" onsubmit="event.preventDefault(); submitOrder();">

            <!-- Contacts -->
            <div class="checkout-section">
                <h3><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONTACTS'); ?></h3>
                <div class="uk-margin-small">
                    <input class="uk-input" type="text" name="first_name" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FIRST_NAME'); ?>" required>
                </div>
                <div class="uk-margin-small">
                    <input class="uk-input" type="text" name="last_name" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_NAME'); ?>" required>
                </div>
                <div class="uk-margin-small">
                    <input class="uk-input" type="tel" name="phone" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?>" required>
                </div>
                <div class="uk-margin-small">
                    <input class="uk-input" type="email" name="email" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL'); ?>">
                </div>
            </div>

            <!-- Shipping -->
            <div class="checkout-section">
                <h3><?php echo Text::_('COM_RADICALMART_TELEGRAM_DELIVERY'); ?></h3>
                <div id="provider-filters" class="provider-filters" hidden></div>
                <div id="shipping-map-container" style="height: 350px; width: 100%; background: #eee;"></div>

                <!-- PVZ Info Panel (shown when user clicks on a point) -->
                <div id="pvz-info-panel" class="pvz-info-panel" hidden></div>

                <!-- Selected PVZ confirmation -->
                <div id="selected-pvz-info" class="uk-alert uk-alert-success" hidden>
                    <div class="uk-text-bold" id="pvz-title"></div>
                    <div class="uk-text-small" id="pvz-address" style="white-space: pre-line;"></div>
                </div>
                <div id="tariffs-container" class="uk-margin-small-top" hidden></div>
                <input type="hidden" name="pvz_id" id="pvz_id_input">
                <input type="hidden" name="pvz_provider" id="pvz_provider_input">
            </div>

            <!-- Payment -->
            <div class="checkout-section">
                <h3><?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT'); ?></h3>
                <div id="payment-methods">
                    <div uk-spinner></div>
                </div>
            </div>

            <!-- Discounts & Points -->
            <div class="checkout-section" id="bonuses-section">
                <h3><?php echo Text::_('COM_RADICALMART_TELEGRAM_DISCOUNTS_POINTS'); ?></h3>

                <!-- Promo Code -->
                <div class="uk-margin-small" id="promo-code-block">
                    <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO'); ?></label>
                    <div class="uk-flex uk-flex-middle">
                        <input class="uk-input uk-width-expand" type="text" name="bonuses_codes" id="promo-code-input" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_ENTER_PROMO'); ?>" value="<?php echo htmlspecialchars($this->appliedCode); ?>">
                        <button type="button" class="uk-button uk-button-default uk-margin-small-left" id="apply-promo-btn"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?></button>
                    </div>
                    <div id="promo-result" class="uk-margin-small-top" <?php if (empty($this->appliedCode)): ?>hidden<?php endif; ?>><?php if (!empty($this->appliedCode)): ?><span class="uk-text-success"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO_APPLIED'); ?></span><?php endif; ?></div>
                </div>

                <!-- Points -->
                <div class="uk-margin-small" id="points-block" data-points="<?php echo $this->points; ?>" data-points-equivalent="<?php echo htmlspecialchars($this->pointsEquivalent); ?>" data-customer-id="<?php echo $this->customerId; ?>" data-applied-points="<?php echo $this->appliedPoints; ?>" <?php if (!$this->pointsEnabled || $this->points <= 0): ?>hidden<?php endif; ?>>
                    <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS'); ?></label>
                    <div class="uk-text-small uk-text-muted uk-margin-small-bottom">
                        <?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_AVAILABLE'); ?>:
                        <strong id="points-available"><?php echo number_format($this->points, 0, ',', ' '); ?></strong>
                        <?php if ($this->pointsEquivalent): ?>
                        <span>(= <?php echo $this->pointsEquivalent; ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="uk-flex uk-flex-middle uk-margin-small-bottom">
                        <button type="button" class="uk-button uk-button-default uk-button-small" id="points-minus">−</button>
                        <input class="uk-input uk-width-small uk-text-center uk-margin-small-left uk-margin-small-right" type="number" name="bonuses_points" id="points-input" value="<?php echo (int) $this->appliedPoints; ?>" min="0" max="<?php echo (int) $this->points; ?>">
                        <button type="button" class="uk-button uk-button-default uk-button-small" id="points-plus">+</button>
                        <button type="button" class="uk-button uk-button-default uk-margin-small-left" id="apply-points-btn"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?></button>
                    </div>
                    <div class="uk-margin-small">
                        <label>
                            <input class="uk-checkbox" type="checkbox" id="use-all-points" <?php if ($this->appliedPoints > 0 && $this->appliedPoints >= $this->points): ?>checked<?php endif; ?>>
                            <?php echo Text::_('COM_RADICALMART_TELEGRAM_USE_ALL_POINTS'); ?>
                        </label>
                    </div>
                    <div id="points-result" class="uk-margin-small-top" <?php if ($this->appliedPoints <= 0): ?>hidden<?php endif; ?>><?php if ($this->appliedPoints > 0): ?><span class="uk-text-success"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_APPLIED'); ?></span><?php endif; ?></div>
                </div>

                <!-- No bonuses available message for guests -->
                <?php if (!$this->pointsEnabled || ($this->customerId <= 0 && $this->points <= 0)): ?>
                <div class="uk-text-muted uk-text-small" id="bonuses-login-hint">
                    <?php if ($this->customerId <= 0): ?>
                    <?php echo Text::_('COM_RADICALMART_TELEGRAM_BONUSES_LOGIN_HINT'); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div class="checkout-section">
                <h3><?php echo Text::_('COM_RADICALMART_TELEGRAM_TOTAL'); ?></h3>
                <div id="checkout-summary-block"></div>
            </div>

            <button type="submit" id="submit-btn" class="uk-button uk-button-primary uk-width-1-1 uk-button-large uk-margin-bottom">Оплатить</button>
        </form>
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
            <li>
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

</body>
</html>
