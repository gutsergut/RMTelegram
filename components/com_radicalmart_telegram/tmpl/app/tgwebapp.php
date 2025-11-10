<?php
/**
 * Telegram WebApp layout (UIkit + YOOtheme styles)
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$root = rtrim(Uri::root(), '/');
$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <?php $ymKey = isset($this->params) ? (string) $this->params->get('yandex_maps_api_key', '') : ''; ?>
    <?php if ($ymKey): ?>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?php echo htmlspecialchars($ymKey, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
    <?php
    // Load local IMask via Web Asset Manager
    try {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('com_radicalmart_telegram.imask');
    } catch (\Throwable $e) {}
    ?>
    <style>
        html, body { height: 100%; }
        body { background: var(--tg-theme-bg-color, #fff); color: var(--tg-theme-text-color, #222); }
        .tg-safe-text { color: var(--tg-theme-text-color, inherit); }
        .uk-card { background: var(--tg-theme-bg-color, #fff); }
        .uk-navbar-container { background: var(--tg-theme-bg-color, #fff); }
        .uk-button-primary { background-color: var(--tg-theme-button-color, #1e87f0); color: var(--tg-theme-button-text-color, #fff); }
        .uk-section { padding-top: 16px; padding-bottom: 16px; }
    </style>
    <script>
        (function(){ try{ if(window.Telegram && window.Telegram.WebApp){ const tg=window.Telegram.WebApp; tg.ready(); tg.expand(); tg.BackButton.hide(); } }catch(e){} })();
        function qs(name){ const p=new URLSearchParams(location.search); return p.get(name); }
        function makeNonce(){ return (Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,10)); }
        async function api(method, params={}){
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option','com_radicalmart_telegram');
            url.searchParams.set('task','api.'+method);
            const chat = qs('chat'); if (chat) url.searchParams.set('chat', chat);
            try { if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) { url.searchParams.set('tg_init', window.Telegram.WebApp.initData); } } catch(e){}
            for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);

            console.log('API call:', method, 'params:', params, 'URL:', url.toString());

            const res = await fetch(url.toString(), { credentials: 'same-origin' });
            const json = await res.json();

            console.log('API response:', method, json);

            if (json && json.success === false) throw new Error(json.message||'API error');
            return json.data || {};
        }
        function el(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }
        function val(name){ const el=document.querySelector(`[name="${name}"]`); return el?el.value.trim():''; }
        function cleanPhone(p){ if(!p) return ''; const d=p.replace(/[^0-9+]/g,''); let s=d; if(d[0]==='8' && d.length===11){ s='+7'+d.slice(1);} else if(d[0]==='7' && d.length===11){ s='+7'+d.slice(1);} else if(d.startsWith('+7') && d.length===12){ s=d; } else if(d.startsWith('+')){ s=d; } return s; }
        function isValidRuPhone(p){ const s=cleanPhone(p); return /^\+7\d{10}$/.test(s); }
        function isValidEmail(e){ if(!e) return true; return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }
        async function loadCatalog(){
            try {
                const inStock = document.getElementById('filter-instock')?.checked ? 1 : 0;
                const sort = document.getElementById('filter-sort')?.value || '';
                const from = (document.getElementById('price-from')?.value||'').trim();
                const to   = (document.getElementById('price-to')?.value||'').trim();
                const params = { limit: 12, in_stock: inStock, sort };
                if (from) params.price_from = from; if (to) params.price_to = to;
                document.querySelectorAll('[data-field-alias]').forEach(el => {
                    const alias = el.getAttribute('data-field-alias');
                    const type = el.getAttribute('data-field-type') || 'text';
                    let val = '';
                    if (type === 'checkbox') { val = el.checked ? '1' : ''; }
                    else { val = (el.value||'').trim(); }
                    if (alias && val) params['field_'+alias] = val;
                });
                const { items } = await api('list', params);
                const root = document.getElementById('catalog-list');
                root.innerHTML = '';
                    (items||[]).forEach(p => {
                        const card = el(`
                            <div>
                              <div class="uk-card uk-card-default uk-card-small">
                                <div class="uk-card-media-top">${p.image?`<img src="${p.image}" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">`:`<div class="uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_IMAGE'); ?></div>`}</div>
                                <div class="uk-card-body">
                                  <div class="uk-text-small uk-text-muted">${p.category||'\u00A0'}</div>
                                  <h5 class="uk-margin-remove">${p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>'}</h5>
                                  <div class="uk-margin-small tg-safe-text"><strong>${p.price_final||''}</strong></div>
                                  <div class="uk-flex uk-flex-between">
                                    <button class="uk-button uk-button-primary" data-action="add" data-id="${p.id}"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'); ?></button>
                                    <button class="uk-button uk-button-default" disabled><?php echo Text::_('COM_RADICALMART_TELEGRAM_MORE'); ?></button>
                                  </div>
                                </div>
                              </div>
                            </div>`);
                        root.appendChild(card);
                });
            } catch(e) {
                console.error('Catalog load error:', e);
                UIkit.notification(e.message || 'Ошибка загрузки каталога', {status:'danger'});
            }
        }
        let CART_COUNT = 0;
        async function refreshCart(){
            try {
                const { cart } = await api('cart');
                const box = document.getElementById('cart-box');
                if (!cart || !cart.products) { CART_COUNT = 0; box.innerHTML = '<p class="uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART_EMPTY'); ?></p>'; return; }
                let html = '<ul class="uk-list">';
                let i=1; for (const key in cart.products) {
                    const p = cart.products[key];
                    const title = p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>';
                    const qty = (p.order && (p.order.quantity_string_short||p.order.quantity)) || '1';
                    const sum = (p.order && p.order.sum_final_string) || '';
                    html += `<li>${i++}. ${title} × ${qty} ${sum?('— '+sum):''}</li>`;
                }
                html += '</ul>';
                if (cart.total && cart.total.final_string) html += `<p><strong><?php echo Text::_('COM_RADICALMART_TELEGRAM_TOTAL'); ?>: ${cart.total.final_string}</strong></p>`;
                box.innerHTML = html;
                CART_COUNT = (cart.total && cart.total.quantity) ? Number(cart.total.quantity) : (i-1);
            } catch(e) { /* ignore */ }
        }
        // old summary/submit removed (replaced below)
        let SELECTED_PVZ_ID = null;
        async function refreshSummary(){
            try {
                const data = await api('summary');
                const box = document.getElementById('summary-box');
                const parts = [];
                if (data.shipping_title) parts.push(`<?php echo Text::_('COM_RADICALMART_TELEGRAM_DELIVERY'); ?>: ${data.shipping_title}`);
                if (data.payment_title)  parts.push(`<?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT'); ?>: ${data.payment_title}`);
                if (data.discount) parts.push(`<?php echo Text::_('COM_RADICALMART_TELEGRAM_DISCOUNT'); ?>: ${data.discount}`);
                if (data.order_total) parts.push(`<strong><?php echo Text::_('COM_RADICALMART_TELEGRAM_TOTAL'); ?>: ${data.order_total}</strong>`);
                if (data.pvz && (data.pvz.title || data.pvz.address)) {
                    SELECTED_PVZ_ID = data.pvz.id || null;
                    parts.push(`<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_SHORT'); ?>: <strong>${data.pvz.title||''}</strong>${data.pvz.address?(' — '+data.pvz.address):''} <a href="#pvz-map" class="uk-link"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHANGE'); ?></a>`);
                    const sel = document.getElementById('pvz-selected'); if (sel) { sel.textContent = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_SHORT'); ?>: ' + (data.pvz.title||'') + (data.pvz.address?(' — '+data.pvz.address):''); }
                }
                box.innerHTML = parts.length? parts.join('<br>') : '';
            } catch(e) { /* ignore */ }
        }

        async function loadMethods(){
            try{
                const { shipping, payment } = await api('methods');
                const shipSel = document.querySelector('[name="shipping_id"]');
                const paySel  = document.querySelector('[name="payment_id"]');
                window.PAYMENT_PLUGINS = {};
                if (shipSel){
                    shipSel.innerHTML = '';
                    (shipping?.methods||[]).forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id; opt.textContent = m.title; opt.disabled = !!m.disabled;
                        if (Number(shipping.selected) === Number(m.id)) opt.selected = true;
                        shipSel.appendChild(opt);
                    });
                }
                if (paySel){
                    paySel.innerHTML = '';
                    const chat = qs('chat');
                    const methods = (payment?.methods||[]).filter(m => {
                        const plug = (m.plugin||'');
                        if (!chat && plug.indexOf('telegram') !== -1) return false; // hide TG if no chat
                        return true;
                    });
                    methods.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id; opt.textContent = m.title; opt.disabled = !!m.disabled;
                        if (Number(payment.selected) === Number(m.id)) opt.selected = true;
                        paySel.appendChild(opt);
                        if (m && m.id) { PAYMENT_PLUGINS[m.id] = m.plugin||''; }
                    });
                    updatePaymentHint();
                }
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        async function onShippingChange(ev){
            try{
                const id = ev.target.value; if(!id) return;
                const { payment } = await api('setshipping', { id, nonce: makeNonce() });
                // refresh payment list if returned
                const paySel  = document.querySelector('[name="payment_id"]');
                if (paySel && payment && payment.methods){
                    paySel.innerHTML = '';
                    window.PAYMENT_PLUGINS = {};
                    (payment.methods||[]).forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id; opt.textContent = m.title; opt.disabled = !!m.disabled;
                        if (Number(payment.selected) === Number(m.id)) opt.selected = true;
                        paySel.appendChild(opt);
                        if (m && m.id) { PAYMENT_PLUGINS[m.id] = m.plugin||''; }
                    });
                }
                await refreshSummary();
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        let LAST_ORDER_NUMBER = null;
        function updatePaymentHint(){
            const paySel  = document.querySelector('[name="payment_id"]');
            const hint = document.getElementById('telegram-pay-hint');
            if (!paySel || !hint) return;
            const id = paySel.value;
            const plugin = (PAYMENT_PLUGINS||{})[id] || '';
            const isTg = plugin && (plugin.indexOf('telegram') !== -1);
            hint.hidden = !isTg;
            if (isTg) {
                const isStars = plugin.indexOf('telegramstars') !== -1;
                const text = isStars ? '<?php echo Text::_('COM_RADICALMART_TELEGRAM_STARS_HINT'); ?>' : '<?php echo Text::_('COM_RADICALMART_TELEGRAM_INVOICE_HINT'); ?>';
                hint.innerHTML = text +
                    ' <button type="button" class="uk-button uk-button-small uk-button-default" onclick="resendInvoice()"><?php echo Text::_('COM_RADICALMART_TELEGRAM_RESEND'); ?></button>';
            }
        }
        async function resendInvoice(){
            try{
                const number = LAST_ORDER_NUMBER || '';
                if (!number) { UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_RESEND_NO_ORDER'); ?>', {status:'warning'}); return; }
                await api('invoice', { number, nonce: makeNonce() });
                UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_RESENT'); ?>', {status:'success'});
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }
        async function onPaymentChange(ev){
            try{ const id = ev.target.value; if(!id) return; await api('setpayment', { id, nonce: makeNonce() }); updatePaymentHint(); await refreshSummary(); }
            catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        async function submitCheckout(ev){
            ev.preventDefault();
            const btn = ev.target.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            try{
                const first_name = val('first_name');
                const last_name  = val('last_name');
                const second_name= val('second_name');
                const phoneRaw   = val('phone');
                const email      = val('email');
                const shipping_id= document.querySelector('[name="shipping_id"]')?.value || '';
                const payment_id = document.querySelector('[name="payment_id"]')?.value || '';

                if (!first_name) throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_FIRST_NAME'); ?>');
                if (!last_name)  throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_LAST_NAME'); ?>');
                if (!phoneRaw)   throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_PHONE_REQUIRED'); ?>');
                if (!isValidRuPhone(phoneRaw)) throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_PHONE_FORMAT'); ?>');
                if (!isValidEmail(email)) throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_EMAIL'); ?>');
                if (!shipping_id) throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_SELECT_SHIPPING'); ?>');
                if (!payment_id)  throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_SELECT_PAYMENT'); ?>');
                if (!CART_COUNT || CART_COUNT <= 0) throw new Error('<?php echo Text::_('COM_RADICALMART_TELEGRAM_JS_ERR_CART_EMPTY'); ?>');

                const payload = {
                    action: 'create',
                    first_name,
                    second_name,
                    last_name,
                    phone: cleanPhone(phoneRaw),
                    email,
                    shipping_id,
                    payment_id
                };
                const id = document.querySelector('[name="payment_id"]').value;
                const plugin = (PAYMENT_PLUGINS||{})[id] || '';
                const { pay_url, order_number } = await api('checkout', { ...payload, nonce: makeNonce() });
                if (order_number) { LAST_ORDER_NUMBER = order_number; }
                if (plugin && plugin.indexOf('telegram') !== -1) {
                    UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_INVOICE_SENT'); ?>', {status:'success'});
                } else if (pay_url){ window.location.href = pay_url; }
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
            finally{ if (btn) btn.disabled = false; }
        }

        async function applyPromo(ev){
            ev?.preventDefault?.();
            const input = document.querySelector('[name="promo_code"]');
            if (!input) return;
            const code = (input.value||'').trim();
            if (!code){ UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_ENTER_PROMO'); ?>', {status:'warning'}); return; }
            try{
                const res = await api('promocode', { action:'add', code, nonce: makeNonce() });
                input.value='';
                renderBonuses(res);
                await refreshSummary();
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        async function removePromo(id){
            try{
                const res = await api('promocode', { action:'remove', id, nonce: makeNonce() });
                renderBonuses(res);
                await refreshSummary();
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        async function applyPoints(ev){
            ev?.preventDefault?.();
            const input = document.querySelector('[name="points"]');
            if (!input) return;
            let val = parseFloat((input.value||'').replace(',', '.'))||0;
            const max = parseFloat((document.getElementById('points-available')?.textContent||'0'))||0;
            if (val > max) { val = max; input.value = String(max); UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_CLAMPED'); ?>', {status:'warning'}); }
            try{
                const res = await api('points', { points: val, nonce: makeNonce() });
                renderBonuses(res);
                await refreshSummary();
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        }

        // Orders
        let ORDERS_PAGE = 1; let ORDERS_HAS_MORE = true; let ORDERS_LOADING = false;
        async function loadOrders(reset=false){
            if (ORDERS_LOADING) return;
            if (reset) { ORDERS_PAGE = 1; ORDERS_HAS_MORE = true; const root=document.getElementById('orders-list'); if (root) root.innerHTML=''; }
            if (!ORDERS_HAS_MORE) return;
            ORDERS_LOADING = true;
            try{
                const st = document.getElementById('orders-status')?.value || '';
                const data = await api('orders', { page: ORDERS_PAGE, limit: 10, status: st });
                // populate statuses on first load
                if (reset && data.statuses && Array.isArray(data.statuses)) {
                    const sel = document.getElementById('orders-status');
                    if (sel) {
                        sel.innerHTML = '';
                        const optAll = document.createElement('option'); optAll.value=''; optAll.textContent='<?php echo Text::_('COM_RADICALMART_TELEGRAM_ALL_STATUSES'); ?>'; sel.appendChild(optAll);
                        data.statuses.forEach(s => { const o=document.createElement('option'); o.value=String(s.id); o.textContent=s.title; sel.appendChild(o); });
                    }
                }
                renderOrders(data.items||[], ORDERS_PAGE>1);
                ORDERS_HAS_MORE = !!data.has_more;
                ORDERS_PAGE = (data.page||ORDERS_PAGE)+1;
                const more = document.getElementById('orders-more'); if (more) more.hidden = !ORDERS_HAS_MORE;
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
            finally{ ORDERS_LOADING = false; }
        }
        function renderOrders(items, append=false){
            const root = document.getElementById('orders-list');
            if (!root) return;
            if (!append) root.innerHTML = '';
            if (!items || !items.length){
                root.appendChild(el(`<li class=\"uk-text-meta\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS_EMPTY'); ?></li>`));
                return;
            }
            items.forEach(o => {
                const payBtn = o.pay_url ? `<a class=\"uk-button uk-button-small uk-button-primary\" href=\"${o.pay_url}\" target=\"_blank\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PAY'); ?></a>` : '';
                const resendBtn = o.can_resend ? `<button type=\"button\" class=\"uk-button uk-button-small\" data-invoice=\"${o.number}\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_RESEND'); ?></button>` : '';
                const li = el(`
                    <li class=\"uk-flex uk-flex-middle uk-flex-between\">
                      <div>
                        <div class=\"uk-text-bold\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDER_NUMBER'); ?>: ${o.number||('#'+o.id)}</div>
                        <div class=\"uk-text-meta\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDER_STATUS'); ?>: ${o.status||'-'} · <?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDER_TOTAL'); ?>: ${o.total||'-'}</div>
                      </div>
                      <div class=\"uk-flex uk-flex-middle uk-flex-right\" style=\"gap:8px\">${resendBtn}${payBtn}</div>
                    </li>`);
                root.appendChild(li);
            });
        }

        function renderBonuses(data){
            const list = document.getElementById('applied-codes');
            const pts  = document.getElementById('applied-points');
            if (list && data && Array.isArray(data.codes)){
                list.innerHTML = '';
                data.codes.forEach(c => {
                    const amount = c.amount ? ` — ${c.amount}` : '';
                    const li = el(`<li>${c.code}${amount} <a href="#" data-remove-code="${c.id}">×</a></li>`);
                    list.appendChild(li);
                });
            }
            if (pts && data && typeof data.points !== 'undefined'){
                pts.textContent = data.points > 0 ? (''+data.points) : '0';
            }
        }

        // PVZ Map
        let map, pvzLayer, pvzProviders = [], pvzMarkers = {};
        function initMap(){
            if (!window.ymaps || map) return;
            ymaps.ready(() => {
                map = new ymaps.Map('pvz-map', { center: [55.751244, 37.618423], zoom: 10, controls: [] });
                const provBoxes = document.querySelectorAll('[data-pvz-provider]');
                const readProviders = () => Array.from(document.querySelectorAll('[data-pvz-provider]:checked')).map(cb => cb.value).filter(Boolean);
                pvzProviders = readProviders();
                provBoxes.forEach(cb => cb.addEventListener('change', ()=>{ pvzProviders = readProviders(); fetchPvz(); }));
                map.events.add('boundschange', debounce(fetchPvz, 500));
                fetchPvz();
            });
        }
        function debounce(fn, ms){ let t; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), ms); } }
        async function fetchPvz(){
            if (!map) return;
            const b = map.getBounds(); if (!b) return;
            const sw = b[0], ne = b[1];
            const bbox = [sw[1], sw[0], ne[1], ne[0]].join(','); // lon1,lat1,lon2,lat2
            const params = { bbox };
            if (pvzProviders.length) params.providers = pvzProviders.join(',');
            const list = document.getElementById('pvz-list');
            const count = document.getElementById('pvz-count');
            if (!pvzProviders.length) {
                if (list) list.innerHTML = '';
                if (count) count.textContent = '0';
                return;
            }
            const spinner = document.getElementById('pvz-loading');
            const errBox = document.getElementById('pvz-error');
            try {
                if (spinner) { spinner.hidden = false; }
                const data = await api('pvz', params);
                renderPvz(data.items||[]);
                try { if (count) count.textContent = String((data.items||[]).length); } catch(e){}
                if (errBox) { errBox.hidden = true; errBox.textContent=''; }
            } catch(e) {
                const msg = e.message || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_LOADING'); ?>';
                UIkit.notification(msg, {status:'danger'});
                if (errBox){ errBox.textContent = msg; errBox.hidden = false; }
            } finally {
                if (spinner) { spinner.hidden = true; }
            }
        }
        function renderPvz(items){
            const list = document.getElementById('pvz-list'); if (list) list.innerHTML='';
            if (!window.ymaps || !map) return;
            if (pvzLayer) { map.geoObjects.remove(pvzLayer); }
            const cluster = new ymaps.Clusterer({ preset: 'islands#invertedBlueClusterIcons' });
            pvzMarkers = {};
            (items||[]).forEach(p => {
                const placemark = new ymaps.Placemark([p.lat, p.lon], { balloonContent: `<strong>${p.title||''}</strong><br>${p.address||''}<br><button type="button" data-pvz='${JSON.stringify(p).replace(/'/g, '&apos;')}'><?php echo Text::_('COM_RADICALMART_TELEGRAM_SELECT_PVZ'); ?></button>` });
                cluster.add(placemark);
                try { placemark.options.set('preset', (SELECTED_PVZ_ID && String(p.id)===String(SELECTED_PVZ_ID)) ? 'islands#redIcon' : 'islands#blueIcon'); } catch(e) {}
                pvzMarkers[p.id] = placemark;
                if (list){
                    const li = el(`<li>${p.provider}: ${p.title||''} — ${p.address||''} <a href="#" data-pvz='${JSON.stringify(p).replace(/'/g, '&apos;')}'><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHOOSE'); ?></a></li>`);
                    try { if (SELECTED_PVZ_ID && String(p.id)===String(SELECTED_PVZ_ID)) li.classList.add('uk-text-primary'); } catch(e){}
                    list.appendChild(li);
                }
            });
            pvzLayer = cluster; map.geoObjects.add(cluster);
        }

        document.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('[data-action="add"][data-id]');
            if (!btn) return;
            ev.preventDefault();
            btn.disabled = true;
            try { await api('add', { id: btn.dataset.id, qty: 1, nonce: makeNonce() }); await refreshCart(); UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_ADDED_TO_CART'); ?>'); }
            catch(e){ UIkit.notification(e.message, {status:'danger'}); }
            finally { btn.disabled = false; }
        });
        document.addEventListener('click', (ev) => {
            const a = ev.target.closest('[data-remove-code]');
            if (!a) return;
            ev.preventDefault();
            const id = a.getAttribute('data-remove-code');
            if (id) removePromo(id);
        });
        document.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('[data-invoice]');
            if (!btn) return;
            ev.preventDefault();
            try{
                btn.disabled = true; btn.classList.add('uk-disabled');
                const n = btn.getAttribute('data-invoice');
                await api('invoice', { number: n, nonce: makeNonce() });
                UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_RESENT'); ?>', {status:'success'});
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
            finally{ btn.disabled = false; btn.classList.remove('uk-disabled'); }
        });
        document.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('[data-pvz]');
            if (!btn) return;
            ev.preventDefault();
            try{
                const p = JSON.parse(btn.getAttribute('data-pvz').replace(/&apos;/g, "'"));
                const params = { id: p.id, provider: p.provider, title: p.title||'', address: p.address||'', lat: p.lat, lon: p.lon, nonce: makeNonce() };
                const res = await api('setpvz', params);
                try{ if (map) { map.setCenter([p.lat, p.lon], 13); } if (pvzMarkers[p.id]) { pvzMarkers[p.id].balloon.open(); } }catch(e){}
                await refreshSummary();
                try { await fetchPvz(); } catch(e) {}
                UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_SELECTED'); ?>');
            }catch(e){ UIkit.notification(e.message, {status:'danger'}); }
        });
        document.addEventListener('click', (ev) => {
            const a = ev.target.closest('a.uk-link[href="#pvz-map"]');
            if (!a) return;
            ev.preventDefault();
            const m = document.getElementById('pvz-map'); if (m) m.scrollIntoView({behavior:'smooth', block:'center'});
        });
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOMContentLoaded: starting initialization...');
            loadCatalog();
            refreshCart();
            loadMethods().then(async () => { const sum = await api('summary'); renderBonuses(sum); await refreshSummary(); });
            // Filters listeners
            const fs = document.getElementById('filter-sort');
            const fi = document.getElementById('filter-instock');
            if (fs) fs.addEventListener('change', () => loadCatalog());
            if (fi) fi.addEventListener('change', () => loadCatalog());
            document.addEventListener('change', (ev) => { if (ev.target && ev.target.matches('[data-field-alias]')) loadCatalog(); });
            const fr = document.getElementById('filters-reset'); if (fr) fr.addEventListener('click', () => {
                if (fi) fi.checked = false; if (fs) fs.value = '';
                document.querySelectorAll('[data-field-alias]').forEach(el => {
                    const type = el.getAttribute('data-field-type')||'text';
                    if (type === 'checkbox') el.checked = false; else el.value = '';
                });
                const pf = document.getElementById('price-from'); const pt = document.getElementById('price-to');
                if (pf) pf.value=''; if (pt) pt.value='';
                loadCatalog();
            });
            const fa = document.getElementById('filters-apply'); if (fa) fa.addEventListener('click', () => loadCatalog());
            loadOrders(true);
            // Load available points
            api('bonuses').then(data => {
                const el = document.getElementById('points-available');
                const inp = document.querySelector('[name="points"]');
                if (el && data && typeof data.points_available !== 'undefined') {
                    el.textContent = data.points_available;
                    if (inp) inp.setAttribute('max', data.points_available);
                }
            }).catch(()=>{});
            const sh = document.querySelector('[name="shipping_id"]');
            const pm = document.querySelector('[name="payment_id"]');
            if (sh) sh.addEventListener('change', onShippingChange);
            if (pm) pm.addEventListener('change', onPaymentChange);
            const ph = document.querySelector('[name="phone"]');
            if (ph) {
                if (window.IMask) {
                    IMask(ph, {
                        mask: [
                            { mask: '+{7} (000) 000-00-00' },
                            { mask: '8 (000) 000-00-00' },
                            { mask: '(000) 000-00-00' },
                            { mask: '0000000000' }
                        ],
                        dispatch: function (appended, dynamicMasked) {
                            const raw = (dynamicMasked.value + appended).replace(/\D/g, '');
                            if (!raw) return dynamicMasked.compiledMasks[0];
                            if (raw[0] === '8') return dynamicMasked.compiledMasks[1];
                            if (raw[0] === '9' || raw.length === 10) return dynamicMasked.compiledMasks[2];
                            if (raw.length === 10) return dynamicMasked.compiledMasks[3];
                            return dynamicMasked.compiledMasks[0];
                        }
                    });
                } else {
                    const formatDisplay = (val) => {
                        const digits = (val||'').replace(/\D/g, '');
                        if (!digits) return '';
                        let d = digits;
                        if (d[0] === '8') d = '7' + d.slice(1);
                        if (d[0] === '9') d = '7' + d; // assume local mobile
                        let out = '+7 ';
                        if (d.length > 1) {
                            const rest = d.slice(1);
                            const p1 = rest.slice(0,3);
                            const p2 = rest.slice(3,6);
                            const p3 = rest.slice(6,8);
                            const p4 = rest.slice(8,10);
                            if (p1) out += `(${p1}` + (p1.length===3?') ':'');
                            if (p2) out += p2 + (p2.length===3?'-':'');
                            if (p3) out += p3 + (p3.length===2?'-':'');
                            if (p4) out += p4;
                        }
                        return out.trim();
                    };
                    ph.addEventListener('input', () => { ph.value = formatDisplay(ph.value); });
                    ph.addEventListener('blur', () => { ph.value = formatDisplay(ph.value); });
                }
            }
            // init map if key present
            <?php if (!empty($ymKey)): ?>
            initMap();
            <?php endif; ?>
        });
        document.addEventListener('click', (ev) => { const btn = ev.target.closest('#orders-more'); if (!btn) return; ev.preventDefault(); loadOrders(false); });
        document.addEventListener('change', (ev) => { const sel = ev.target.closest('#orders-status'); if (!sel) return; ev.preventDefault(); loadOrders(true); });
    </script>
</head>
<body>

<nav class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-left">
        <a class="uk-navbar-item uk-logo tg-safe-text" href="#"><?php echo htmlspecialchars($storeTitle, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="uk-navbar-right">
        <ul class="uk-navbar-nav">
            <li><a href="#catalog"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></a></li>
            <li><a href="#cart"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></a></li>
            <li><a href="#checkout"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHECKOUT'); ?></a></li>
            <li><a href="#orders"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></a></li>
        </ul>
    </div>

</nav>

<div class="uk-section uk-section-default">
    <div class="uk-container">

        <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1">
                <h3 id="catalog" class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></h3>
                <div class="uk-grid-small uk-margin-small" uk-grid>
                    <div>
                        <label><input id="filter-instock" class="uk-checkbox" type="checkbox"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_ONLY_IN_STOCK'); ?></label>
                    </div>
                    <div>
                        <select id="filter-sort" class="uk-select" style="min-width:200px">
                            <option value=""><?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_DEFAULT'); ?></option>
                            <option value="new"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_NEW'); ?></option>
                            <option value="price_asc"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_PRICE_ASC'); ?></option>
                            <option value="price_desc"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_PRICE_DESC'); ?></option>
                        </select>
                    </div>
                    <div class="uk-flex uk-flex-middle" style="gap:6px">
                        <input id="price-from" class="uk-input" type="number" min="0" step="0.01" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FROM'); ?>" style="max-width:120px">
                        <input id="price-to" class="uk-input" type="number" min="0" step="0.01" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_TO'); ?>" style="max-width:120px">
                        <button type="button" id="filters-apply" class="uk-button uk-button-primary uk-button-small"><?php echo Text::_('JAPPLY'); ?></button>
                    </div>
                </div>
                <?php $filtersCfg = (array) ($this->params->get('filters_fields') ?: []); if (!empty($filtersCfg)): ?>
                <div class="uk-grid-small uk-margin-small" uk-grid>
                    <?php foreach ($filtersCfg as $f):
                        $f = (array) $f; // Convert stdClass to array
                        if (empty($f['enabled']) || (int)$f['enabled'] !== 1) continue;
                        $alias = trim((string)($f['alias']??''));
                        if ($alias==='') continue;
                        $type = (string)($f['type']??'text');
                        $label = (string)($f['label']??$alias);
                        $ph=(string)($f['placeholder']??'');
                        $phf=(string)($f['placeholder_from']??'');
                        $pht=(string)($f['placeholder_to']??'');
                    ?>
                        <div>
                            <label class="uk-form-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="uk-form-controls">
                                <?php if ($type==='select'): $optsText=(string)($f['options']??''); $lines=preg_split('#\r?\n#',$optsText); ?>
                                    <select class="uk-select" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="select">
                                        <option value=""></option>
                                        <?php foreach ($lines as $ln): $ln=trim($ln); if ($ln==='') continue; $parts=explode('|',$ln,2); $val=trim($parts[0]); $lab=trim($parts[1]??$parts[0]); ?>
                                            <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($type==='checkbox'): ?>
                                    <input type="checkbox" class="uk-checkbox" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="checkbox">
                                <?php elseif ($type==='range'): ?>
                                    <div class="uk-grid-small" uk-grid>
                                        <div><input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="range" data-field-range="from" placeholder="<?php echo htmlspecialchars($phf ?: Text::_('COM_RADICALMART_TELEGRAM_FROM'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width:130px"></div>
                                        <div><input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="range" data-field-range="to" placeholder="<?php echo htmlspecialchars($pht ?: Text::_('COM_RADICALMART_TELEGRAM_TO'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width:130px"></div>
                                    </div>
                                <?php elseif ($type==='number'): ?>
                                    <input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="number" placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php else: ?>
                                    <input type="text" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="text" placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="uk-child-width-1-2 uk-child-width-1-3@s" uk-grid id="catalog-list"></div>
                <div class="uk-margin-small"><button type="button" id="filters-reset" class="uk-button uk-button-default uk-button-small"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTERS_RESET'); ?></button></div>
            </div>

            <div class="uk-width-1-1">
                <h3 id="cart" class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></h3>
                <div id="cart-box" class="uk-card uk-card-default uk-card-body">
                    <p class="uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART_EMPTY'); ?></p>
                </div>
            </div>

            <div class="uk-width-1-1">
                <h3 id="checkout" class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CHECKOUT'); ?></h3>
                <div class="uk-card uk-card-default uk-card-body">
                    <div id="summary-box" class="uk-margin uk-text-small"></div>
                    <form class="uk-form-stacked" onsubmit="submitCheckout(event)">
                        <div class="uk-grid-small" uk-grid>
                            <div class="uk-width-1-1 uk-width-1-3@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LAST_NAME'); ?></label>
                                <div class="uk-form-controls">
                                    <input class="uk-input" type="text" name="last_name" placeholder="Иванов" required>
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-3@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FIRST_NAME'); ?></label>
                                <div class="uk-form-controls">
                                    <input class="uk-input" type="text" name="first_name" placeholder="Иван" required>
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-3@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SECOND_NAME'); ?></label>
                                <div class="uk-form-controls">
                                    <input class="uk-input" type="text" name="second_name" placeholder="Иванович">
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-3@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?></label>
                                <div class="uk-form-controls">
                                    <input class="uk-input" type="tel" name="phone" placeholder="+7 927 123-45-67" required>
                                    <div class="uk-text-meta uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE_HINT'); ?></div>
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-3@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL'); ?></label>
                                <div class="uk-form-controls">
                                    <input class="uk-input" type="email" name="email" placeholder="you@example.com">
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-2@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_DELIVERY'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="shipping_id"></select>
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-2@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT'); ?></label>
                                <div class="uk-form-controls">
                                    <select class="uk-select" name="payment_id"></select>
            <div class="uk-width-1-1 uk-width-1-2@s">
                <div id="telegram-pay-hint" class="uk-alert uk-alert-primary" hidden></div>
            </div>
                                </div>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-2@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO'); ?></label>
                                <div class="uk-form-controls uk-flex">
                                    <input class="uk-input" type="text" name="promo_code" placeholder="CODE-1234" style="max-width: 240px;">
                                    <button type="button" class="uk-button uk-button-default uk-margin-small-left" onclick="applyPromo(event)"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?></button>
                                </div>
                                <ul class="uk-list uk-list-collapse uk-margin-small" id="applied-codes"></ul>
                            </div>
                            <div class="uk-width-1-1 uk-width-1-2@s">
                                <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS'); ?></label>
                                <div class="uk-form-controls uk-flex">
                                    <input class="uk-input" type="number" min="0" step="1" name="points" placeholder="0" style="max-width: 160px;">
                                    <button type="button" class="uk-button uk-button-default uk-margin-small-left" onclick="applyPoints(event)"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY_POINTS'); ?></button>
                                </div>
                                <div class="uk-text-meta uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_APPLIED'); ?>: <span id="applied-points">0</span></div>
                                <div class="uk-text-meta uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_AVAILABLE'); ?>: <span id="points-available">0</span></div>
                            </div>
                            <div class="uk-width-1-1">
                                <button type="submit" class="uk-button uk-button-primary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CREATE_AND_PAY'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="uk-width-1-1">
                <h3 id="orders" class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></h3>
                <div class="uk-card uk-card-default uk-card-body">
                    <div class="uk-margin-small">
                        <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS_STATUS'); ?></label>
                        <div class="uk-form-controls"><select id="orders-status" class="uk-select" style="max-width:220px"></select></div>
                    </div>
                    <ul id="orders-list" class="uk-list uk-list-divider"></ul>
                    <div class="uk-margin-small"><button type="button" id="orders-more" class="uk-button uk-button-default uk-button-small"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?></button></div>
                </div>
            </div>

            <div class="uk-width-1-1">
                <h3 class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_MAP'); ?></h3>
                <div class="uk-card uk-card-default uk-card-body">
                    <p class="uk-text-meta"><?php echo Text::_('COM_RADICALMART_TELEGRAM_MAP_HINT'); ?></p>
                    <div class="uk-grid-small" uk-grid>
                        <div class="uk-width-1-1 uk-width-1-3@s">
                            <label class="uk-form-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROVIDERS'); ?></label>
                            <div class="uk-form-controls">
                                <?php $prov = isset($this->params)?(string)$this->params->get('apiship_providers','yataxi,cdek,x5'):'yataxi,cdek,x5'; $provList = array_filter(array_map('trim', explode(',', $prov))); ?>
                                <?php foreach ($provList as $p): ?>
                                    <label class="uk-display-block"><input class="uk-checkbox" type="checkbox" data-pvz-provider value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" checked> <?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></label>
                                <?php endforeach; ?>
                                <div id="pvz-selected" class="uk-text-meta uk-margin-small-top"></div>
                                <div class="uk-text-meta uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PVZ_SHORT'); ?>: <span id="pvz-count">0</span></div>
                            </div>
                        </div>
                        <div class="uk-width-1-1 uk-width-2-3@s">
                            <div id="pvz-map" style="height: 360px;" class="uk-background-muted"></div>
                            <div id="pvz-loading" class="uk-text-meta uk-margin-small" hidden><span uk-spinner></span> <?php echo Text::_('COM_RADICALMART_TELEGRAM_LOADING'); ?></div>
                            <div id="pvz-error" class="uk-alert uk-alert-danger uk-margin-small" hidden></div>
                        </div>
                        <div class="uk-width-1-1">
                            <ul id="pvz-list" class="uk-list uk-list-divider"></ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>
