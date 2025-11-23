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
        <!--RMT_TEMPLATE_ACTIVE:default.php:UNIQUE_MARKER:<?php echo date('YmdHis'); ?>-->
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
                    if (window.RMT_DEBUG) console.log('[RMT][icons] Loading UIkit icons from CDN...');
                    var s=document.createElement('script');
                    s.src='https://cdn.jsdelivr.net/npm/uikit@3.17.11/dist/js/uikit-icons.min.js';
                    s.async=true;
                    s.onload=function(){ try{ if (window.RMT_DEBUG) console.log('[RMT][icons] UIkit icons loaded from CDN'); window.UIkit && UIkit.update && UIkit.update(); }catch(e){ if (window.RMT_DEBUG) console.log('[RMT][icons] update error', e); } };
                    s.onerror=function(e){ if (window.RMT_DEBUG) console.log('[RMT][icons] CDN load error', e); };
                    document.head.appendChild(s);
                } catch(e){ if (window.RMT_DEBUG) console.log('[RMT][icons] loadCdnIcons error', e); }
            }
            try { if (window.RMT_DEBUG) console.log('[RMT][icons] UIkit present:', !!window.UIkit, 'version:', (window.UIkit && UIkit.version)||''); } catch(e){}
            try { if (window.RMT_DEBUG) console.log('[RMT][icons] Has UIkit.icon:', hasIcons()); } catch(e){}
            if (!hasIcons()){
                if (document.readyState==='loading'){
                    document.addEventListener('DOMContentLoaded', function(){ if (!hasIcons()) loadCdnIcons(); });
                } else {
                    loadCdnIcons();
                }
            }
        })();
        </script>
        <?php $ymKey = isset($this->params) ? (string) $this->params->get('yandex_maps_api_key', '') : ''; ?>
        <?php $ymEnabled = isset($this->params) ? (int) $this->params->get('ym_enabled', 1) : 1; ?>
        <?php $ymCounterId = isset($this->params) ? (string) $this->params->get('ym_counter_id', '') : ''; ?>
        <?php if ($ymKey): ?>
        <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?php echo htmlspecialchars($ymKey, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php endif; ?>
        <?php
        try {
                $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
                $wa->registerAndUseScript('com_radicalmart_telegram.app', 'media/com_radicalmart_telegram/js/app.js', ['version' => 'auto', 'defer' => true]);
                $wa->useScript('com_radicalmart_telegram.imask');
        } catch (\Throwable $e) {}
        ?>
        <style>
            /* Base theming: ensure visible background/text change */
            html, body { background-color: var(--tg-theme-bg-color, #ffffff); color: var(--tg-theme-text-color, #222); }
            /* Neutralize Joomla contentpane padding/margins */
            body.contentpane { padding: 0 !important; margin: 0 !important; }
            /* Fullscreen consent overlay hidden by default */
            #consent-overlay { position: fixed; inset: 0; background: var(--tg-theme-bg-color, rgba(255,255,255,.96)); z-index: 9999; overflow: auto; display: none; }
            body.consent-block { overflow: hidden; }
            /* Ensure UIkit modal is above overlay */
            .uk-modal { z-index: 10010 !important; }
            .uk-modal.uk-open { z-index: 10010 !important; }
            #doc-modal { z-index: 10011 !important; }
            #doc-modal .uk-modal-dialog { z-index: 10012 !important; }
            /* Bottom fixed navigation */
            #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
            /* Reserve space so content is not hidden under the bottom nav */
            body { padding-bottom: 52px; }
            /* Cookies banner */
            #cookie-banner { position: fixed; left: 8px; right: 8px; bottom: 72px; z-index: 10006; display: none; }
            #cookie-settings-btn { position: fixed; right: 10px; bottom: 120px; z-index: 10006; display: none; }
            /* Bottom tabs: compact icon + caption */
            #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
            #app-bottom-nav .tg-safe-text {  display: inline-flex; align-items: center; }
            #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
            #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
            #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }
            /* Cart badge */
            #cart-badge { position: absolute; top: 2px; right: 6px; background: #f0506e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; }
        </style>
        <script>
            // Переводы для внешнего JS
            window.RMT_LANG = {
                COM_RADICALMART_TELEGRAM_PROFILE_NO_USER: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_NO_USER'); ?>',
                COM_RADICALMART_TELEGRAM_EMAIL: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_EMAIL'); ?>',
                COM_RADICALMART_TELEGRAM_PHONE: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PHONE'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_POINTS: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_POINTS'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_REFERRALS: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_REFERRALS'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_PARENT: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_PARENT'); ?>',
                JLINK: '<?php echo Text::_('JLINK'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_EXPIRES_UNTIL: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_EXPIRES_UNTIL'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CODES_EMPTY: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CODES_EMPTY'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CODE_PLACEHOLDER: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CODE_PLACEHOLDER'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CURRENCY_PLACEHOLDER: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CURRENCY_PLACEHOLDER'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CREATE_CODE: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CREATE_CODE'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CODE_CREATED: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CODE_CREATED'); ?>',
                COM_RADICALMART_TELEGRAM_PROFILE_CODE_ERROR: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE_CODE_ERROR'); ?>',
                COM_RADICALMART_TELEGRAM_SEARCH_ENTER_QUERY: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_ENTER_QUERY'); ?>',
                COM_RADICALMART_TELEGRAM_SEARCH_NO_RESULTS: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_NO_RESULTS'); ?>',
                COM_RADICALMART_TELEGRAM_ADD_TO_CART: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'); ?>',
                COM_RADICALMART_TELEGRAM_SEARCH_ERROR: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_ERROR'); ?>'
            };

            // Icons debug helper: expose a function to inspect the state
            window.RMT_DEBUG_ICONS = function(){
                try {
                    const hasUI = !!window.UIkit;
                    const ver = hasUI ? (UIkit.version||'') : '';
                    const hasIcon = hasUI && !!UIkit.icon;
                    const cnt = document.querySelectorAll('[uk-icon]').length;
                    RMT_DBG('icons: UIkit', hasUI, 'version', ver, 'iconPlugin', hasIcon, 'elementsWithUkIcon', cnt);
                    if (hasUI) { try { UIkit.update(); RMT_DBG('icons: UIkit.update() called'); } catch(e){ RMT_DBG('icons: update error', e && e.message); } }
                    // Try inject a test icon and see if SVG appears
                    try {
                        const test = document.createElement('span');
                        test.setAttribute('uk-icon','search');
                        document.body.appendChild(test);
                        if (hasUI && UIkit.update) UIkit.update();
                        const ok = !!test.querySelector('svg');
                        RMT_DBG('icons: test injection ok', ok);
                        document.body.removeChild(test);
                    } catch(e){ RMT_DBG('icons: test injection error', e && e.message); }
                } catch(e){ console.log('[RMT][icons] debug error', e); }
            };
            // Переводы для cookies/analytics
            window.RMT_COOKIE_LANG = {
                TITLE: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_BANNER_TITLE'); ?>',
                TEXT: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_BANNER_TEXT'); ?>',
                SETTINGS: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_SETTINGS'); ?>',
                ACCEPT_ALL: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_ACCEPT_ALL'); ?>',
                SAVE: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_SAVE'); ?>',
                CAT_ESSENTIAL: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_ESSENTIAL'); ?>',
                CAT_ANALYTICS: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_ANALYTICS'); ?>',
                CAT_MARKETING: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_MARKETING'); ?>',
                DNT: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_DNT_ACTIVE'); ?>',
                YM_DISABLED: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_METRIKA_DISABLED'); ?>',
                YM_LOADED: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_METRIKA_LOADED'); ?>'
            };

            // Card view configuration (resolve selected field IDs to aliases for badge/subtitle)
            <?php
            $cvEnabled = (int)($this->params->get('cardview_enabled', 0));
            $badgeRaw = $this->params->get('card_badge_fields', []);
            $subtitleRaw = $this->params->get('card_subtitle_fields', []);
            if (!is_array($badgeRaw)) { $badgeRaw = $badgeRaw !== '' ? explode(',', (string)$badgeRaw) : []; }
            if (!is_array($subtitleRaw)) { $subtitleRaw = $subtitleRaw !== '' ? explode(',', (string)$subtitleRaw) : []; }
            $specialTokens = ['in_stock','discount'];
            $ids = [];
            foreach (array_merge($badgeRaw,$subtitleRaw) as $v) { if ($v !== '' && !in_array($v,$specialTokens,true)) { $ids[] = (int)$v; } }
            $idToAlias = [];
            $aliasToTitle = [];
            if ($ids) {
                try {
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select([$db->quoteName('id'), $db->quoteName('alias'), $db->quoteName('title')])
                        ->from($db->quoteName('#__radicalmart_fields'))
                        ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval',$ids)) . ')');
                    $rows = $db->setQuery($q)->loadAssocList();
                    foreach ($rows as $r) {
                        $idToAlias[(int)$r['id']] = trim((string)$r['alias']);
                        $aliasToTitle[trim((string)$r['alias'])] = trim((string)$r['title']);
                    }
                } catch (\Throwable $e) {}
            }
            $badgeAliases = [];
            foreach ($badgeRaw as $v) { if (in_array($v,$specialTokens,true)) { $badgeAliases[] = $v; } elseif (!empty($idToAlias[(int)$v])) { $badgeAliases[] = $idToAlias[(int)$v]; } }
            $subtitleAliases = [];
            foreach ($subtitleRaw as $v) { if (in_array($v,$specialTokens,true)) { $subtitleAliases[] = $v; } elseif (!empty($idToAlias[(int)$v])) { $subtitleAliases[] = $idToAlias[(int)$v]; } }
            ?>
            window.RMT_CARDVIEW = {
                enabled: <?php echo $cvEnabled; ?>,
                badge_fields: '<?php echo addslashes(implode(',', $badgeAliases)); ?>',
                subtitle_fields: '<?php echo addslashes(implode(',', $subtitleAliases)); ?>',
                field_titles: <?php echo json_encode($aliasToTitle, JSON_UNESCAPED_UNICODE); ?>,
                variant_show_weight: <?php echo (int)$this->params->get('card_variant_show_weight', 1); ?>,
                variant_show_discount: <?php echo (int)$this->params->get('card_variant_show_discount', 1); ?>,
                variant_show_final_price: <?php echo (int)$this->params->get('card_variant_show_final_price', 1); ?>
            };

            function qs(name){ const p=new URLSearchParams(location.search); return p.get(name); }
            function makeNonce(){ return (Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,10)); }
            window.RMT_DEBUG = false;
            function RMT_DBG(){ if (!window.RMT_DEBUG) return; try { const t=new Date().toISOString(); console.log('[RMT]', t, ...arguments); } catch(e){} }
            function rmtDecodeHtml(s){ try { const d=document.createElement('textarea'); d.innerHTML = s; return d.value; } catch(e){ return s; } }

            // Функции согласий
            function updateConsentSubmitState(){
                const pd = document.getElementById('consent-personal')?.checked;
                const tm = document.getElementById('consent-terms')?.checked;
                const btn = document.getElementById('consent-submit');
                if (btn) btn.disabled = !(pd && tm);
                const all = document.getElementById('consent-all');
                if (all) {
                    const mk = document.getElementById('consent-marketing')?.checked;
                    all.checked = !!pd && !!tm && (!!mk || !document.getElementById('consent-marketing'));
                }
                RMT_DBG('consents:updateState', { pd, tm, btnEnabled: !btn?.disabled });
            }
            function toggleAcceptAll(ev){
                const v = !!(ev && ev.target && ev.target.checked);
                const ids = ['consent-personal','consent-terms','consent-marketing'];
                ids.forEach(id => { const el=document.getElementById(id); if (el) el.checked = v; });
                updateConsentSubmitState();
                RMT_DBG('consents:toggleAll', { v });
            }
            let CURRENT_DOC_TYPE=null;
            async function openDocModal(type){
                try{
                    CURRENT_DOC_TYPE = type||null;
                    RMT_DBG('doc:open start', { type, hasAPI: !!window.RMT_API });
                    let data = { html: '' };
                    if (window.RMT_API) {
                        try {
                            data = await window.RMT_API('legal', { type });
                        } catch(e1) {
                            RMT_DBG('doc:api error legal', e1 && e1.message ? e1.message : e1);
                            try {
                                data = await window.RMT_API('dochtml', { type });
                            } catch(e2) {
                                RMT_DBG('doc:api error dochtml', e2 && e2.message ? e2.message : e2);
                                data = { html: '' };
                            }
                        }
                    }
                    const rawHtml = (data && typeof data.html === 'string') ? data.html : '';
                    RMT_DBG('doc:api result', { type, hasData: !!data, keys: Object.keys(data||{}), htmlLen: rawHtml.length });
                    const box = document.getElementById('doc-modal-body');
                    if (box){
                        // Первичная вставка
                        box.innerHTML = rawHtml;
                        let txt = box.textContent || '';
                        let hasEscaped = /&lt;|&gt;|&amp;lt;|&amp;gt;/.test(txt);
                        let hasTags = /<[^>]+>/.test(box.innerHTML||'');
                        RMT_DBG('doc:content inserted.pre', { childNodes: box.childNodes?.length||0, hasEscaped, hasTags, snippet: (txt||'').slice(0,120) });
                        // Авто-декод, если пришёл экранированный HTML
                        if (!hasTags && hasEscaped && rawHtml){
                            const decoded = rmtDecodeHtml(rawHtml);
                            box.innerHTML = decoded;
                            txt = box.textContent || '';
                            hasTags = /<[^>]+>/.test(box.innerHTML||'');
                            RMT_DBG('doc:content decoded', { applied:true, newHasTags: hasTags, snippet: (txt||'').slice(0,120) });
                        }
                    }
                    if (box){
                        const txt = box.textContent || '';
                        const hasEscaped = /&lt;|&gt;/.test(txt);
                        const hasTags = /<[^>]+>/.test(box.innerHTML||'');
                        RMT_DBG('doc:content inserted', { childNodes: box.childNodes?.length||0, hasEscaped, hasTags, snippet: (txt||'').slice(0,120) });
                    }
                    // Ensure modal element is a direct child of body to avoid stacking-context issues
                    try {
                        const modalEl = document.getElementById('doc-modal');
                        if (modalEl && modalEl.parentElement !== document.body) {
                            document.body.appendChild(modalEl);
                            RMT_DBG('doc:modal moved-to-body');
                        }
                    } catch(e){ RMT_DBG('doc:move-error', e && e.message); }
                    const modal = UIkit.modal('#doc-modal'); modal.show();
                    try {
                        const el = modal.$el || document.querySelector('#doc-modal');
                        const U = UIkit.util || UIkit;
                        if (U && el && U.on){
                            U.on(el, 'beforeshow', () => RMT_DBG('doc:event beforeshow'));
                            U.on(el, 'show',      () => RMT_DBG('doc:event show'));
                            U.on(el, 'shown',     () => RMT_DBG('doc:event shown'));
                            U.on(el, 'hide',      () => RMT_DBG('doc:event hide'));
                            U.on(el, 'hidden',    () => RMT_DBG('doc:event hidden'));
                        }
                    } catch(e){ RMT_DBG('doc:events hook error', e.message); }
                    const accept = document.getElementById('doc-accept-btn');
                    const decline = document.getElementById('doc-decline-btn');
                    if (accept){ accept.onclick = function(){
                        const map={ privacy:'consent-personal', terms:'consent-terms', marketing:'consent-marketing' };
                        const id = map[CURRENT_DOC_TYPE]||null; if (id){ const el=document.getElementById(id); if (el) el.checked = true; }
                        updateConsentSubmitState(); modal.hide();
                    }; }
                    if (decline){ decline.onclick = function(){ modal.hide(); } }
                }catch(e){ RMT_DBG('doc:open error', e && e.message ? e.message : e); UIkit.notification(e.message||'<?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?>', {status:'danger'}); }
            }
        </script>
        <script>
        // --- Cookies & Analytics (Yandex Metrika) ---
        // --- UIkit Icons: force render + fallback ---
        function RMT_FORCE_UKIT_ICONS(){
            try{
                if (!window.UIkit || !UIkit.icon) return false;
                const nodes = document.querySelectorAll('[uk-icon]');
                let forced = 0;
                nodes.forEach(el => {
                    // If already has svg, skip forcing
                    if (el.querySelector('svg')) return;
                    const name = RMT_EXTRACT_ICON_NAME(el.getAttribute('uk-icon'));
                    try { UIkit.icon(el, { icon: name }); forced++; } catch(_){}
                });
                if (forced>0) console.log('[RMT][icons] UIkit.icon forced for', forced, 'elements');
                // Also trigger UIkit.update to catch components
                try { UIkit.update(); } catch(_){}
                return forced>0;
            }catch(e){ return false; }
        }

        // --- UIkit Icons Fallback (inline glyphs) ---
        // master switch: disable fallback by default if SVG icons load fine
        const RMT_ICON_FALLBACK_ENABLED = false;
        // If UIkit icons fail to render (no <svg> inside [uk-icon]),
        // we substitute small text glyphs to keep UI usable.
        const RMT_ICON_FALLBACK_MAP = {
            'thumbnails': '▦',
            'cart': '🛒',
            'credit-card': '💳',
            'list': '≡',
            'user': '👤',
            'cog': '⚙️',
            'moon': '🌙',
        };
        function RMT_EXTRACT_ICON_NAME(attr){
            if (!attr) return '';
            let s = String(attr);
            // supports formats: "icon: name" or just "name"
            const m = s.match(/icon\s*:\s*([^;]+)/);
            if (m && m[1]) return m[1].trim();
            return s.trim();
        }
        function RMT_APPLY_ICON_FALLBACK(){
            try{
                const nodes = document.querySelectorAll('[uk-icon]');
                let applied = 0;
                nodes.forEach(el => {
                    // Already has SVG? skip
                    if (el.querySelector('svg')) return;
                    const name = RMT_EXTRACT_ICON_NAME(el.getAttribute('uk-icon'));
                    const glyph = RMT_ICON_FALLBACK_MAP[name] || '';
                    if (!glyph) return;
                    // Avoid double-apply
                    if (el.querySelector('[data-rmt-fallback-icon]')) return;
                    el.innerHTML = `<span data-rmt-fallback-icon aria-hidden="true">${glyph}</span>`;
                    applied++;
                });
                if (applied>0) {
                    console.log('[RMT][icons] fallback applied for', applied, 'elements');
                }
            }catch(err){ /* noop */ }
        }

        function RMT_REMOVE_ICON_FALLBACK(){
            try{
                const nodes = document.querySelectorAll('[uk-icon]');
                let removed = 0;
                nodes.forEach(el => {
                    // Skip managing fallback for theme toggle here; it's handled by RMT_ENSURE_THEME_TOGGLE_ICON
                    if (el.id === 'theme-toggle') return;
                    if (el.querySelector('svg')){
                        const fb = el.querySelector('[data-rmt-fallback-icon]');
                        if (fb) { fb.remove(); removed++; }
                    }
                });
                if (removed>0) console.log('[RMT][icons] fallback removed for', removed, 'elements');
            }catch(e){}
        }

        function RMT_ICONS_MISSING_COUNT(){
            try{
                let c = 0; document.querySelectorAll('[uk-icon]').forEach(el => { if (!el.querySelector('svg')) c++; });
                return c;
            }catch(e){ return 0; }
        }

        // Observe DOM changes to remove fallback spans as soon as SVG appears
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
                    if (needsCheck) { try { RMT_REMOVE_ICON_FALLBACK(); } catch(e){} }
                });
                RMT_ICON_OBSERVER.observe(document.documentElement || document.body, { childList: true, subtree: true });
            }catch(e){}
        }

        // Ensure theme toggle icon is visible; if SVG injection fails, add a tiny glyph
        function RMT_ENSURE_THEME_TOGGLE_ICON(){
            try{
                const el = document.getElementById('theme-toggle');
                if (!el) return;
                const hasSvg = !!el.querySelector('svg');
                if (hasSvg) { const fb = el.querySelector('[data-rmt-fallback-icon]'); if (fb) fb.remove(); return; }
                const attr = el.getAttribute('uk-icon')||'';
                const name = RMT_EXTRACT_ICON_NAME(attr);
                const map = { moon:'🌙', sun:'☀️', bolt:'⚡' };
                const glyph = map[name] || '⚙️';
                if (!el.querySelector('[data-rmt-fallback-icon]')){
                    el.innerHTML = `<span data-rmt-fallback-icon aria-hidden="true">${glyph}</span>`;
                }
                // Re-check shortly in case UIkit injects SVG a bit later
                setTimeout(() => { try { const s = el.querySelector('svg'); if (s) { const fb = el.querySelector('[data-rmt-fallback-icon]'); if (fb) fb.remove(); } } catch(_){} }, 120);
            }catch(e){}
        }

        // Fallback styles (lightweight, scoped via attribute selector)
        try{
            const style = document.createElement('style');
            style.textContent = `
                [data-rmt-fallback-icon]{
                    display:inline-block; width:20px; height:20px; line-height:20px;
                    text-align:center; font-size:18px; vertical-align:middle;
                }
                .uk-navbar-nav .bottom-tab [data-rmt-fallback-icon]{
                    width:22px; height:22px; line-height:22px; font-size:18px;
                }
            `;
            document.head.appendChild(style);
        }catch(e){}

        // --- Cookies & Analytics (Yandex Metrika) ---
        const YM_ENABLED_CFG = <?php echo (int)$ymEnabled; ?> === 1;
        const YM_COUNTER_ID = '<?php echo htmlspecialchars($ymCounterId ?? '', ENT_QUOTES, 'UTF-8'); ?>';
        let YM_LOADED = false;
        function isDNTEnabled(){ try { const dnt = (navigator.doNotTrack||window.doNotTrack||navigator.msDoNotTrack||'').toString(); return dnt==='1' || dnt==='yes'; } catch(e){ return false; } }
        function getCookiePrefs(){ try { return JSON.parse(localStorage.getItem('rmt_cookie_prefs')||'{}'); } catch(e){ return {}; } }
        function setCookiePrefs(p){ try { localStorage.setItem('rmt_cookie_prefs', JSON.stringify(p||{})); } catch(e){} }
        function ensureCookieBanner(){
            const prefs = getCookiePrefs();
            const decided = typeof prefs.essential !== 'undefined' || typeof prefs.analytics !== 'undefined' || typeof prefs.marketing !== 'undefined';
            // always hide banner if overlay is active
            const overlayActive = document.body.classList.contains('consent-block');
            const banner = document.getElementById('cookie-banner');
            if (!banner) return;
            if (!decided && !overlayActive) { banner.style.display = ''; }
            else { banner.style.display = 'none'; }
            const btn = document.getElementById('cookie-settings-btn'); if (btn && !overlayActive) btn.style.display = '';
        }
        function openCookieModal(){ try { UIkit.modal('#cookie-modal').show(); } catch(e){} }
        function applyCookieUIFromPrefs(){
            const prefs = getCookiePrefs();
            const dnt = isDNTEnabled();
            const a = document.getElementById('cookie-analytics');
            if (a){ a.checked = !!prefs.analytics && !dnt; a.disabled = dnt; }
            const m = document.getElementById('cookie-marketing'); if (m){ m.checked = !!prefs.marketing; }
            const note = document.getElementById('cookie-dnt-note'); if (note){ note.hidden = !dnt; }
        }
        function saveCookieSettings(){
            const dnt = isDNTEnabled();
            const analytics = dnt ? false : !!document.getElementById('cookie-analytics')?.checked;
            const marketing = !!document.getElementById('cookie-marketing')?.checked;
            setCookiePrefs({ essential: true, analytics, marketing });
            UIkit.modal('#cookie-modal').hide();
            document.getElementById('cookie-banner')?.setAttribute('style','display:none');
            maybeLoadYM();
        }
        function acceptAllCookies(){ setCookiePrefs({ essential:true, analytics: !isDNTEnabled(), marketing:true }); document.getElementById('cookie-banner')?.setAttribute('style','display:none'); maybeLoadYM(); }
        function maybeLoadYM(){
            if (YM_LOADED) return;
            if (!YM_ENABLED_CFG) { RMT_DBG('ym:disabled cfg'); return; }
            if (!YM_COUNTER_ID) { RMT_DBG('ym:no counter id'); return; }
            if (isDNTEnabled()) { RMT_DBG('ym:dnt active'); return; }
            const prefs = getCookiePrefs(); if (!prefs.analytics) { RMT_DBG('ym:analytics not permitted'); return; }
            // Load YM
            try {
                (function(m,e,t,r,i,k,a){ m[i]=m[i]||function(){ (m[i].a=m[i].a||[]).push(arguments); }; m[i].l=1*new Date(); k=e.createElement(t), a=e.getElementsByTagName(t)[0]; k.async=1; k.src=r; a.parentNode.insertBefore(k,a); })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
                window.ym = window.ym || function(){ (window.ym.a = window.ym.a || []).push(arguments); };
                window.ym(parseInt(YM_COUNTER_ID,10), 'init', { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true });
                YM_LOADED = true;
                try { UIkit.notification(window.RMT_COOKIE_LANG.YM_LOADED||''); } catch(e){}
                RMT_DBG('ym:loaded');
            } catch(e) { RMT_DBG('ym:error', e && e.message ? e.message : e); }
        }
        function initCookiesAndAnalytics(){
            applyCookieUIFromPrefs();
            ensureCookieBanner();
            maybeLoadYM();
        }
        function getUserTheme(){ try { return localStorage.getItem('rmt_theme') || null; } catch(e){ return null; } }
        function setUserTheme(m){ try { if (m) localStorage.setItem('rmt_theme', m); else localStorage.removeItem('rmt_theme'); } catch(e){} }
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
            const toggle = document.getElementById('theme-toggle');
            if (toggle){
                const titleMap = {
                    light: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_THEME_LIGHT'); ?>',
                    dark: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_THEME_DARK'); ?>',
                    tg: '<?php echo Text::_('COM_RADICALMART_TELEGRAM_THEME_TG'); ?>'
                };
                const icon = (mode==='dark') ? 'sun' : (mode==='light' ? 'moon' : 'bolt');
                toggle.setAttribute('uk-icon', 'icon: ' + icon);
                toggle.setAttribute('title', titleMap[mode] || '');
                try { if (window.UIkit && UIkit.icon) { UIkit.icon(toggle, { icon }); UIkit.update(); } } catch(e){}
                try { RMT_ENSURE_THEME_TOGGLE_ICON && RMT_ENSURE_THEME_TOGGLE_ICON(); } catch(e){}
            }
            // Maintain dark class for logo/theme-dependent UI
            const isDark = (mode==='dark') || (mode==='tg' && (cs==='dark'));
            try { root.classList.toggle('rmt-dark', !!isDark); document.body.classList.toggle('rmt-dark', !!isDark); } catch(e){}
            try { RMT_UPDATE_LOGO_FOR_THEME(); } catch(e){}
        }
        function initTheme(){
            let t=getUserTheme();
            // По умолчанию используем режим 'tg' (следовать теме Telegram)
            if(!t){ t = 'tg'; }
            applyTheme(t);
            const btn=document.getElementById('theme-toggle');
            if(btn){ btn.addEventListener('click', (ev)=>{
                ev.preventDefault();
                const pref=getUserTheme()||'tg';
                // Цикл: light -> dark -> tg -> light
                let next='light';
                if (pref==='light') next='dark'; else if (pref==='dark') next='tg'; else next='light';
                setUserTheme(next);
                applyTheme(next);
            }); }
            try { window.Telegram?.WebApp?.onEvent?.('themeChanged', () => {
                const cs = window.Telegram.WebApp.colorScheme;
                const desired = (cs === 'dark') ? 'dark' : 'light';
                const userPref = getUserTheme();
                if (!userPref || userPref === 'tg') { applyTheme('tg'); }
            }); } catch(e){}
        }
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
        // Экспорт API для внешнего JS
        window.RMT_API = api;
        async function ensureConsents(){
            try {
                RMT_DBG('consents:check start');
                const data = await api('consents');
                const statuses = data.statuses || { personal_data:false, marketing:false, terms:false };
                const need = !statuses.personal_data || !statuses.terms; // обязательные
                RMT_DBG('consents:statuses', statuses, 'needOverlay=', need);
                if (!need) {
                    RMT_DBG('consents:ok no overlay');
                    const ovx = document.getElementById('consent-overlay');
                    if (ovx) ovx.style.display = 'none';
                    document.body.classList.remove('consent-block');
                    const bn = document.getElementById('app-bottom-nav'); if (bn) bn.style.display = '';
                    return true;
                }
                const ov = document.getElementById('consent-overlay');
                const list = document.getElementById('consent-docs');
                if (list) list.innerHTML = '';
                const cbp = document.getElementById('consent-personal'); if (cbp) cbp.checked = !!statuses.personal_data;
                const cbt = document.getElementById('consent-terms'); if (cbt) cbt.checked = !!statuses.terms;
                const cbm = document.getElementById('consent-marketing'); if (cbm && typeof statuses.marketing !== 'undefined') cbm.checked = !!statuses.marketing;
                updateConsentSubmitState();
                cbp?.addEventListener('change', updateConsentSubmitState);
                cbt?.addEventListener('change', updateConsentSubmitState);
                cbm?.addEventListener('change', updateConsentSubmitState);
                if (ov) { ov.style.display = 'block'; RMT_DBG('consents:overlay shown'); }
                document.body.classList.add('consent-block');
                const bn = document.getElementById('app-bottom-nav'); if (bn) bn.style.display = 'none';
                return false;
            } catch(e){
                RMT_DBG('consents:error', e && e.message ? e.message : e);
                UIkit.notification(e.message||'<?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?>',{status:'danger'});
                return false;
            }
        }
        async function submitConsents(){
            try{
                const pd = document.getElementById('consent-personal')?.checked;
                const tm = document.getElementById('consent-terms')?.checked;
                const mk = document.getElementById('consent-marketing')?.checked;
                if (!pd || !tm) { UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_ACCEPT_REQUIRED'); ?>', {status:'warning'}); return; }
                await api('setconsent', { type:'personal_data', value: pd?1:0, nonce: makeNonce() });
                await api('setconsent', { type:'terms', value: tm?1:0, nonce: makeNonce() });
                if (typeof mk !== 'undefined') { await api('setconsent', { type:'marketing', value: mk?1:0, nonce: makeNonce() }); }
                document.getElementById('consent-overlay').style.display='none';
                document.body.classList.remove('consent-block');
                // После согласия — загрузка данных
                await initApp();
            }catch(e){ UIkit.notification(e.message||'<?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?>',{status:'danger'}); }
        }
        async function initApp(){
            loadCatalog();
            refreshCart();
            if (window.loadProfile) window.loadProfile();
            loadMethods().then(async () => { const sum = await api('summary'); renderBonuses(sum); await refreshSummary(); });
            // Filters listeners
            const fs = document.getElementById('filter-sort');
            const fi = document.getElementById('filter-instock');
            if (fs) fs.addEventListener('change', () => loadCatalog());
            if (fi) fi.addEventListener('change', () => loadCatalog());
            const bf = document.getElementById('btn-filters'); if (bf) bf.addEventListener('click', () => { try { UIkit.modal('#filters-modal').show(); } catch(e){} });
            const bs = document.getElementById('btn-sort'); if (bs) bs.addEventListener('click', () => {
                try {
                    const current = (document.getElementById('filter-sort')?.value||'');
                    const radios = document.querySelectorAll('input[name="sort-radio"]');
                    radios.forEach(r => { r.checked = (r.value === current); });
                    UIkit.modal('#sort-modal').show();
                } catch(e){}
            });
            const sa = document.getElementById('sort-apply'); if (sa) sa.addEventListener('click', () => {
                const r = document.querySelector('input[name="sort-radio"]:checked');
                const v = r ? r.value : '';
                const fld = document.getElementById('filter-sort'); if (fld) fld.value = v;
                try { UIkit.modal('#sort-modal').hide(); } catch(e){}
                loadCatalog();
            });
            document.addEventListener('change', (ev) => { if (ev.target && ev.target.matches('[data-field-alias]')) loadCatalog(); });
            document.addEventListener('click', (ev) => {
                const btn = ev.target.closest('.rmt-filter-buttons .rmt-filter-btn, .rmt-filter-buttons .rmt-filter-btn-all');
                if (!btn) return;
                const group = btn.closest('.rmt-filter-buttons');
                if (!group) return;
                const isAll = btn.classList.contains('rmt-filter-btn-all');
                if (isAll) {
                    // Clear selection
                    group.setAttribute('data-value','');
                    group.querySelectorAll('.rmt-filter-btn').forEach(b => { b.classList.remove('uk-button-primary'); b.setAttribute('aria-pressed','false'); });
                    btn.classList.remove('uk-button-primary');
                    loadCatalog();
                    return;
                }
                const val = (btn.getAttribute('data-value')||'').trim();
                if (!val) return;
                let current = group.getAttribute('data-value')||'';
                let arr = current? current.split(',').filter(x=>x) : [];
                if (arr.includes(val)) {
                    arr = arr.filter(x => x!==val);
                    btn.classList.remove('uk-button-primary');
                    btn.setAttribute('aria-pressed','false');
                } else {
                    arr.push(val);
                    btn.classList.add('uk-button-primary');
                    btn.setAttribute('aria-pressed','true');
                }
                // Update data-value
                const newVal = arr.join(',');
                group.setAttribute('data-value', newVal);
                // Visual state for 'All' button: highlight if nothing selected
                const allBtn = group.querySelector('.rmt-filter-btn-all');
                if (allBtn) {
                    if (arr.length===0) allBtn.classList.add('uk-button-primary'); else allBtn.classList.remove('uk-button-primary');
                }
                loadCatalog();
            });
            // Clear via tag close
            document.addEventListener('click', (ev) => {
                const tag = ev.target.closest('[data-clear-filter]');
                if (!tag) return;
                const type = tag.getAttribute('data-type');
                const alias = tag.getAttribute('data-alias')||'';
                const val = tag.getAttribute('data-value')||'';
                if (type === 'in_stock') {
                    const el = document.getElementById('filter-instock'); if (el) el.checked = false;
                } else if (type === 'price') {
                    const pf = document.getElementById('price-from'); const pt = document.getElementById('price-to'); if (pf) pf.value=''; if (pt) pt.value='';
                } else if (type === 'field' && alias) {
                    const sel = document.querySelector(`select[data-field-alias="${alias}"]`);
                    if (sel) { sel.value = ''; }
                    const grp = document.querySelector(`.rmt-filter-buttons[data-field-alias="${alias}"]`);
                    if (grp) {
                        let cur = (grp.getAttribute('data-value')||'');
                        let arr = cur? cur.split(',').filter(x=>x) : [];
                        if (val) arr = arr.filter(x => x!==val); else arr = [];
                        grp.setAttribute('data-value', arr.join(','));
                        grp.querySelectorAll('.rmt-filter-btn').forEach(b => {
                            const v=(b.getAttribute('data-value')||'');
                            if (!val || v===val) { b.classList.remove('uk-button-primary'); b.setAttribute('aria-pressed','false'); }
                        });
                        const allBtn = grp.querySelector('.rmt-filter-btn-all'); if (allBtn && (!arr || arr.length===0)) allBtn.classList.add('uk-button-primary');
                    }
                    const chk = document.querySelector(`input[type="checkbox"][data-field-alias="${alias}"]`); if (chk) chk.checked = false;
                }
                loadCatalog();
            });
            const fr = document.getElementById('filters-reset'); if (fr) fr.addEventListener('click', () => {
                if (fi) fi.checked = false; if (fs) fs.value = '';
                document.querySelectorAll('[data-field-alias]').forEach(el => {
                    const type = el.getAttribute('data-field-type')||'text';
                    if (type === 'checkbox') el.checked = false;
                    else if (type === 'buttons') { /* handled below */ }
                    else el.value = '';
                });
                document.querySelectorAll('.rmt-filter-buttons').forEach(grp => {
                    grp.setAttribute('data-value','');
                    grp.querySelectorAll('.rmt-filter-btn').forEach(b => { b.classList.remove('uk-button-primary'); b.setAttribute('aria-pressed','false'); });
                    const allBtn = grp.querySelector('.rmt-filter-btn-all'); if (allBtn) allBtn.classList.add('uk-button-primary');
                });
                const pf = document.getElementById('price-from'); const pt = document.getElementById('price-to');
                if (pf) pf.value=''; if (pt) pt.value='';
                loadCatalog();
            });
            const fa = document.getElementById('filters-apply'); if (fa) fa.addEventListener('click', () => {
                try { UIkit.modal('#filters-modal').hide(); } catch(e){}
                loadCatalog();
            });
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
                    IMask(ph, { mask: [{ mask: '+{7} (000) 000-00-00' },{ mask: '8 (000) 000-00-00' },{ mask: '(000) 000-00-00' },{ mask: '0000000000' }] });
                }
            }
            <?php if (!empty($ymKey)): ?>
            initMap();
            <?php endif; ?>
            // Init cookies & analytics after UI is ready
            initCookiesAndAnalytics();
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
                // limit: 0 => запросить все мета‑товары и их варианты
                const params = { limit: 0, in_stock: inStock, sort };
                if (from) params.price_from = from; if (to) params.price_to = to;
                document.querySelectorAll('[data-field-alias]').forEach(el => {
                    const alias = el.getAttribute('data-field-alias');
                    const type = el.getAttribute('data-field-type') || 'text';
                    let val = '';
                    if (type === 'checkbox') {
                        val = el.checked ? '1' : '';
                    } else if (type === 'buttons') {
                        val = (el.getAttribute('data-value')||'').trim();
                    } else {
                        val = (el.value||'').trim();
                    }
                    if (alias && val) {
                        params['field_'+alias] = val;
                        console.log('[FILTER] field:', alias, 'type:', type, 'value:', val);
                    }
                });
                console.log('[FILTER] Final params:', params);
                                const { items } = await api('list', params);
                                if (window.RMT_DEBUG) {
                                    console.log('[FILTER] in_stock param:', params.in_stock, 'items count:', items ? items.length : 0);
                                    if (items && items.length > 0) {
                                        console.log('[API] First item from server:', items[0]);
                                        if (items[0].children && items[0].children.length > 0) {
                                            console.log('[API] First child:', items[0].children[0]);
                                        }
                                    }
                                }
                                const root = document.getElementById('catalog-list');
                                root.innerHTML = '';
                                (items||[]).forEach(p => {
                                        if (p && p.is_meta) {
                                                const cfg = window.RMT_CARDVIEW || {};
                                                const enabled = cfg.enabled === 1;
                                                const showWeight = cfg.variant_show_weight === 1;
                                                const showDiscount = cfg.variant_show_discount === 1;
                                                const showFinal = cfg.variant_show_final_price === 1;
                                                const badgeFields = (cfg.badge_fields||'').split(/\s*,\s*/).filter(Boolean);
                                                const subtitleFields = (cfg.subtitle_fields||'').split(/\s*,\s*/).filter(Boolean);
                                                const fieldTitles = cfg.field_titles || {};
                                                const children = Array.isArray(p.children) ? p.children : [];
                                                const hasVariants = children.length > 0;
                                                const first = hasVariants ? children[0] : null;
                                                // Проверяем, есть ли хоть один вариант в наличии
                                                const allOutOfStock = hasVariants && children.every(ch => !ch.in_stock);

                                                if (!hasVariants) {
                                                    // Пустая карточка мета без вариантов — показываем заглушку
                                                    const emptyCard = el(`
                                                        <div>
                                                            <div class="uk-card uk-card-default uk-card-small">
                                                                <div class="uk-card-media-top" style="position:relative;">${p.image?`<img src="${p.image}" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">`:`<div class="uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_IMAGE'); ?></div>`}</div>
                                                                <div class="uk-card-body" data-card="${p.id}">
                                                                    <div class="uk-text-small uk-text-muted">${p.category||'\u00A0'}</div>
                                                                    <h5 class="uk-margin-remove">${p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>'}</h5>
                                                                    <div class="uk-text-meta uk-margin-xsmall-top js-subtitle"></div>
                                                                    <div class="uk-margin-small tg-safe-text"><span class="js-final"><strong>${(p.price_min && p.price_max)?`${p.price_min} – ${p.price_max}`:(p.price_min||p.price_max||'')}</strong></span></div>
                                                                    <div class="uk-text-small uk-text-warning uk-margin-small">Нет вариантов</div>
                                                                </div>
                                                            </div>
                                                        </div>`);
                                                    root.appendChild(emptyCard);
                                                    return;
                                                }

                                                // Если все варианты не в наличии - показываем карточку "Нет в наличии"
                                                if (allOutOfStock) {
                                                    const outOfStockCard = el(`
                                                        <div>
                                                            <div class="uk-card uk-card-default uk-card-small">
                                                                <div class="uk-card-media-top" style="position:relative;">${p.image?`<img src="${p.image}" alt="" class="uk-width-1-1 uk-object-cover" style="height:160px">`:`<div class="uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_IMAGE'); ?></div>`}</div>
                                                                <div class="uk-card-body" data-card="${p.id}">
                                                                    <div class="uk-text-small uk-text-muted">${p.category||'\u00A0'}</div>
                                                                    <h5 class="uk-margin-remove">${p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>'}</h5>
                                                                    <div class="uk-text-meta uk-margin-xsmall-top js-subtitle"></div>
                                                                    <div class="uk-margin-small uk-text-danger uk-text-center">
                                                                        <strong><?php echo Text::_('COM_RADICALMART_NOT_IN_STOCK'); ?></strong>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>`);
                                                    root.appendChild(outOfStockCard);
                                                    return;
                                                }

                                                // first уже определён выше
                                                const priceRange = (p.price_min && p.price_max) ? `${p.price_min} – ${p.price_max}` : (p.price_min||p.price_max||'');

                                                function makeBadge(ch){
                                                        if (!enabled || !badgeFields.length) return [];
                                                        const vals = [];
                                                        badgeFields.forEach(bf => {
                                                                if (bf==='in_stock' && typeof ch.in_stock!=='undefined') vals.push(ch.in_stock?'В наличии':'Нет');
                                                                else if (bf==='discount' && ch.discount_percent) vals.push('-' + ch.discount_percent + '%');
                                                                else if (ch[bf]) vals.push(ch[bf]);
                                                        });
                                                        return vals;
                                                }
                                                function makeSubtitle(ch){
                                                        if (!enabled || !subtitleFields.length) return '';
                                                        const lines = [];
                                                        subtitleFields.forEach(sf => {
                                                                let value = '';
                                                                if (sf==='discount' && ch.discount_percent) {
                                                                        value = '-'+ch.discount_percent+'%';
                                                                } else if (ch[sf]) {
                                                                        value = ch[sf];
                                                                }
                                                                if (value) {
                                                                        const label = fieldTitles[sf] || sf;
                                                                        lines.push(`${label}: ${value}`);
                                                                }
                                                        });
                                                        return lines.join('<br>');
                                                }
                                                if (location.search.indexOf('tgdebug=1')!==-1) { window.RMT_DEBUG = true; }

                                                // DEBUG: вывести children для первого мета-товара
                                                if (window.RMT_DEBUG && p.id === 2) {
                                                    console.log('[DEBUG] Meta product:', p.id, p.title);
                                                    console.log('[DEBUG] Children array:', children);
                                                    console.log('[DEBUG] Config:', {showWeight, showDiscount, showFinal});
                                                }

                                                function variantLabel(ch){
                                                    // Показываем только значение выбранного доп. поля (вес). Fallback: распарсить вес из title.
                                                    let label = '';
                                                    if (showWeight && ch.field_weight) {
                                                        label = String(ch.field_weight).trim();
                                                    }
                                                    if (!label) {
                                                        const t = ch.title||'';
                                                        const m = /([0-9]+(?:[.,][0-9]+)?\s?(?:кг|г))$/i.exec(t);
                                                        if (m) label = m[1].replace(',', '.');
                                                    }
                                                    if (!label) {
                                                        label = (ch.title||'').replace(/\(ID\s+\d+\)/i,'').trim();
                                                    }
                                                    if (!label) label = String(ch.id);
                                                    if (window.RMT_DEBUG) console.log('[variantLabel]', {id: ch.id, field_weight: ch.field_weight, title: ch.title, resolved: label});
                                                    return label;
                                                }
                                                // Создаём кнопки только для детей из списка (уже отфильтрованных по цене/наличию)
                                                const variantBtns = children.map(ch=>`<button class=\"uk-button uk-button-default uk-button-small rmt-variant\" data-vid=\"${ch.id}\" title=\"${(ch.title||'').replace(/\"/g,'&quot;')}\">${variantLabel(ch)}</button>`).join(' ');

                                                if (window.RMT_DEBUG && p.id === 2) {
                                                    console.log('[DEBUG] Generated buttons HTML:', variantBtns);
                                                }

                                                const card = el(`
                                                    <div><!--RMT_TEMPLATE_DEFAULT_META-->
                                                        <div>
                                                            <div class=\"uk-card uk-card-default uk-card-small\">
                                                                <div class=\"uk-card-media-top\" style=\"position:relative;\">${p.image?`<img src=\"${p.image}\" alt=\"\" class=\"uk-width-1-1 uk-object-cover\" style=\"height:160px\">`:`<div class=\"uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_IMAGE'); ?></div>`}
                                                                    <div class=\"rmt-card-badges\" style=\"position:absolute;left:6px;top:6px;display:flex;flex-direction:column;gap:4px;align-items:flex-start;\"></div>
                                                                </div>
                                                                <div class=\"uk-card-body\" data-card=\"${p.id}\">
                                                                    <div class=\"uk-text-small uk-text-muted\">${p.category||'\u00A0'}</div>
                                                                    <h5 class=\"uk-margin-remove\">${p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>'}</h5>
                                                                    <div class=\"uk-text-meta uk-margin-xsmall-top js-subtitle\"></div>
                                                                    <div class=\"uk-margin-small js-variants\">${variantBtns}</div>
                                                                    <div class=\"uk-margin-small tg-safe-text js-price-block\">
                                                                        <div class=\"js-price-base uk-text-muted uk-text-small\" style=\"text-decoration:line-through;display:none;\"></div>
                                                                        <div class=\"js-price-final\" style=\"font-size:1.1em;\"><strong></strong></div>
                                                                        <div class=\"js-price-discount uk-text-danger uk-text-small\" style=\"display:none;\"></div>
                                                                    </div>
                                                                    <div class=\"uk-flex uk-flex-between uk-margin-small-top\">
                                                                        <button class=\"uk-button uk-button-primary js-add\" data-vid=\"${first.id}\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'); ?></button>
                                                                        <a class=\"uk-button uk-button-default js-link\" href=\"<?php echo $root; ?>/index.php?option=com_radicalmart&view=product&id=${first.id}\" target=\"_blank\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_MORE'); ?></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>`);
                                                const badgesContainer = card.querySelector('.rmt-card-badges');
                                                const subtitleEl = card.querySelector('.js-subtitle');
                                                const priceFinalEl = card.querySelector('.js-price-final');
                                                const priceDiscEl = card.querySelector('.js-price-discount');
                                                const priceBaseEl = card.querySelector('.js-price-base');
                                                const addBtn = card.querySelector('.js-add');
                                                const linkEl = card.querySelector('.js-link');

                                                function applyVariant(ch){
                                                        if (window.RMT_DEBUG) console.log('[applyVariant]', {id: ch.id, price_final: ch.price_final, price_base: ch.price_base, base_string: ch.base_string, discount_percent: ch.discount_percent, discount_value: ch.discount_value, discount_enable: ch.discount_enable, in_stock: ch.in_stock});

                                                        // Обновляем badges
                                                        const badgeValues = makeBadge(ch);
                                                        badgesContainer.innerHTML = '';
                                                        badgeValues.forEach(val => {
                                                                const badge = document.createElement('div');
                                                                badge.className = 'rmt-card-badge';
                                                                badge.style.cssText = 'background:#f0506e;color:#fff;font-size:11px;padding:2px 6px;border-radius:4px;line-height:1;';
                                                                badge.textContent = val;
                                                                badgesContainer.appendChild(badge);
                                                        });
                                                        subtitleEl.innerHTML = makeSubtitle(ch) || '';

                                                        // Обновляем цены
                                                        const finalPrice = ch.price_final || '';
                                                        const basePrice = ch.base_string || ch.price_base || '';
                                                        const hasDiscount = !!(ch.discount_enable && basePrice && finalPrice !== basePrice);

                                                        // Финальная цена
                                                        if (finalPrice) {
                                                            priceFinalEl.innerHTML = `<strong>${finalPrice}</strong>`;
                                                        } else {
                                                            priceFinalEl.innerHTML = '';
                                                        }

                                                        // Старая цена (зачёркнутая) и скидка
                                                        if (hasDiscount){
                                                            priceBaseEl.style.display='block';
                                                            priceBaseEl.textContent = basePrice;

                                                            let discountText = '';
                                                            if (ch.discount_string && ch.discount_value) {
                                                                discountText = `Скидка ${ch.discount_value} (${ch.discount_string})`;
                                                            } else if (ch.discount_value) {
                                                                discountText = `Скидка ${ch.discount_value}`;
                                                            } else if (ch.discount_string) {
                                                                discountText = ch.discount_string;
                                                            } else if (ch.discount_percent) {
                                                                discountText = `-${ch.discount_percent}%`;
                                                            }
                                                            priceDiscEl.textContent = discountText;
                                                            priceDiscEl.style.display = discountText ? 'block' : 'none';
                                                        } else {
                                                            priceBaseEl.style.display='none';
                                                            priceBaseEl.textContent='';
                                                            priceDiscEl.style.display='none';
                                                            priceDiscEl.textContent='';
                                                        }

                                                        // Обновляем кнопку в зависимости от наличия
                                                        if (ch.in_stock) {
                                                            addBtn.disabled = false;
                                                            addBtn.classList.remove('uk-button-muted');
                                                        } else {
                                                            addBtn.disabled = true;
                                                            addBtn.classList.add('uk-button-muted');
                                                        }

                                                        addBtn.dataset.vid = ch.id;
                                                        linkEl.href = `<?php echo $root; ?>/index.php?option=com_radicalmart&view=product&id=${ch.id}`;
                                                    }
                                                // Применяем первый вариант и выделяем его кнопку
                                                if (window.RMT_DEBUG) console.log('[INIT] Applying first variant:', {id: first.id, price_final: first.price_final, base_string: first.base_string, discount_enable: first.discount_enable, children: children.length});
                                                applyVariant(first);
                                                const firstBtn = card.querySelector('.rmt-variant[data-vid="' + first.id + '"]');
                                                if (firstBtn) firstBtn.classList.add('uk-button-primary');

                                                card.querySelectorAll('.rmt-variant').forEach(btn=>{
                                                        btn.addEventListener('click', () => {
                                                                const vid = parseInt(btn.dataset.vid,10);
                                                                const ch = children.find(x=>parseInt(x.id,10)===vid);
                                                                if (ch) applyVariant(ch);
                                                                card.querySelectorAll('.rmt-variant').forEach(b=>b.classList.remove('uk-button-primary'));
                                                                btn.classList.add('uk-button-primary');
                                                        });
                                                });
                                                addBtn.addEventListener('click', async ()=>{
                                                        const vid = addBtn.dataset.vid;
                                                        addBtn.disabled = true;
                                                        try { 
                                                            await api('cartAdd', { product_id: vid, qty: 1 }); 
                                                            await refreshCart();
                                                            showAddedToCartModal(p.title || 'Товар');
                                                        } catch(e){ 
                                                            UIkit.notification(e.message||'Ошибка', {status:'danger'}); 
                                                        } finally {
                                                            addBtn.disabled = false;
                                                        }
                                                });
                                                root.appendChild(card);
                                                return;
                                        }
                                        // Simple product fallback
                                        const card = el(`
                                            <div><!--RMT_TEMPLATE_DEFAULT_SIMPLE-->
                                                <div>
                                                    <div class=\"uk-card uk-card-default uk-card-small\">
                                                        <div class=\"uk-card-media-top\">${p.image?`<img src=\"${p.image}\" alt=\"\" class=\"uk-width-1-1 uk-object-cover\" style=\"height:160px\">`:`<div class=\"uk-height-small uk-flex uk-flex-middle uk-flex-center uk-background-muted\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_IMAGE'); ?></div>`}</div>
                                                        <div class=\"uk-card-body\">
                                                            <div class=\"uk-text-small uk-text-muted\">${p.category||'\u00A0'}</div>
                                                            <h5 class=\"uk-margin-remove\">${p.title || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>'}</h5>
                                                            <div class=\"uk-margin-small tg-safe-text\"><strong>${p.price_final||''}</strong></div>
                                                            <div class=\"uk-flex uk-flex-between\">
                                                                <button class=\"uk-button uk-button-primary\" data-action=\"add\" data-id=\"${p.id}\"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'); ?></button>
                                                                <button class=\"uk-button uk-button-default\" disabled><?php echo Text::_('COM_RADICALMART_TELEGRAM_MORE'); ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>`);
                                        root.appendChild(card);
                                });
                        // После загрузки каталога — обновим фасеты и активные теги
                        try { await updateDynamicFilterOptions(params); } catch(e){ console.warn('facets update failed:', e); }
                        try { updateActiveFilterTags(params); } catch(e){ console.warn('tags update failed:', e); }
            } catch(e) {
                console.error('Catalog load error:', e);
                UIkit.notification(e.message || '<?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?>', {status:'danger'});
            }
        }
            async function updateDynamicFilterOptions(baseParams){
                const params = Object.assign({}, baseParams||{});
                const data = await api('facets', params);
                const facets = (data && data.facets) ? data.facets : {};
                console.log('[FACETS] Received facets:', facets);
                if (!facets || typeof facets !== 'object') return;
                // Обновляем select и buttons по каждому alias
                Object.keys(facets).forEach(alias => {
                    const list = facets[alias] || [];
                    const allowed = new Set(list.map(o => String(o.value)));
                    console.log('[FACETS] alias:', alias, 'allowed values:', Array.from(allowed));
                    // Selects
                    document.querySelectorAll(`select[data-field-alias="${alias}"]`).forEach(sel => {
                        let selectedVal = sel.value;
                        Array.from(sel.options).forEach((opt, idx) => {
                            if (idx === 0 && opt.value === '') { opt.disabled = false; opt.hidden = false; return; }
                            const ok = allowed.has(String(opt.value));
                            opt.disabled = !ok;
                            opt.hidden = !ok;
                        });
                        // Если выбранное значение больше недоступно — сбросим
                        if (selectedVal && !allowed.has(String(selectedVal))) { sel.value = ''; }
                    });
                    // Buttons groups
                    document.querySelectorAll(`[data-field-type="buttons"][data-field-alias="${alias}"]`).forEach(group => {
                        const currentRaw = (group.getAttribute('data-value')||'').trim();
                        let currentArr = currentRaw ? currentRaw.split(',').filter(x=>x) : [];
                        group.querySelectorAll('button').forEach(btn => {
                            const v = (btn.getAttribute('data-value')||'').trim();
                            if (btn.classList.contains('rmt-filter-btn-all')) { // "All" pseudo option
                                btn.classList.remove('uk-disabled'); return;
                            }
                            const ok = allowed.has(String(v));
                            // НЕ делаем кнопку disabled, только визуально показываем
                            btn.classList.toggle('uk-disabled', !ok);
                            // If value was selected but now not allowed -> НЕ сбрасываем автоматически
                            // Пользователь сам решает, какие фильтры комбинировать
                        });
                        // Sync active classes based on current selection
                        group.querySelectorAll('.rmt-filter-btn').forEach(btn => {
                            const v = (btn.getAttribute('data-value')||'').trim();
                            if (currentArr.includes(v)) { btn.classList.add('uk-button-primary'); btn.setAttribute('aria-pressed','true'); }
                            else { btn.classList.remove('uk-button-primary'); btn.setAttribute('aria-pressed','false'); }
                        });
                        const allBtn = group.querySelector('.rmt-filter-btn-all');
                        if (allBtn) {
                            if (currentArr.length === 0) allBtn.classList.add('uk-button-primary'); else allBtn.classList.remove('uk-button-primary');
                        }
                    });
                });
            }
        function updateActiveFilterTags(baseParams){
            const cont = document.getElementById('active-filter-tags'); if (!cont) return;
            cont.innerHTML = '';
            const addTag = (label, cfg) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'uk-label uk-label-success';
                btn.style.margin = '2px';
                btn.setAttribute('data-clear-filter','1');
                Object.keys(cfg||{}).forEach(k => btn.setAttribute('data-'+k, cfg[k]));
                btn.innerHTML = `<span>${label}</span> <span uk-icon="close"></span>`;
                cont.appendChild(btn);
                // Инициализируем иконку UIkit
                try {
                    const iconEl = btn.querySelector('[uk-icon]');
                    if (iconEl && window.UIkit && UIkit.icon) {
                        UIkit.icon(iconEl);
                    }
                } catch(e){}
            };
            const params = Object.assign({}, baseParams||{});
            if (params.in_stock === 1 || params.in_stock === '1') {
                addTag('<?php echo Text::_('COM_RADICALMART_TELEGRAM_ONLY_IN_STOCK'); ?>', {type:'in_stock'});
            }
            if (params.price_from || params.price_to) {
                const from = params.price_from||''; const to = params.price_to||'';
                let lab = 'Цена: ';
                if (from && to) lab += from + '–' + to;
                else if (from) lab += 'от ' + from;
                else if (to) lab += 'до ' + to;
                addTag(lab, {type:'price'});
            }
            // Fields
            Object.keys(params).forEach(k => {
                if (!k.startsWith('field_')) return;
                const alias = k.substring(6);
                const val = (params[k]||'').toString(); if (!val) return;
                const control = document.querySelector(`[data-field-alias="${alias}"]`);
                const fieldLabel = control?.getAttribute('data-field-label')||alias;
                const values = val.split(',').filter(x=>x);
                values.forEach(v => {
                    let vLab = v;
                    const sel = document.querySelector(`select[data-field-alias="${alias}"]`);
                    if (sel) { const opt = Array.from(sel.options).find(o => o.value === v); if (opt) vLab = opt.textContent.trim(); }
                    const grp = document.querySelector(`.rmt-filter-buttons[data-field-alias="${alias}"]`);
                    if (grp) { const b = grp.querySelector(`.rmt-filter-btn[data-value="${CSS.escape(v)}"]`); if (b) vLab = b.textContent.trim(); }
                    addTag(fieldLabel + ': ' + vLab, {type:'field', alias: alias, value: v});
                });
            });
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
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    if (CART_COUNT > 0) { badge.hidden = false; badge.textContent = String(CART_COUNT); }
                    else { badge.hidden = true; }
                }
            } catch(e) { /* ignore */ }
        }
        // Профиль/Поиск — вынесены во внешний JS (app.js)
        let SEARCH_TIMER=null; let LAST_SEARCH_Q='';
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
        function RMT_UPDATE_LOGO_FOR_THEME(){
            try{
                const img = document.getElementById('brand-logo'); if (!img) return;
                // Determine dark mode by body attribute/class we apply in applyTheme
                const isDark = document.documentElement.classList.contains('rmt-dark') || document.body.classList.contains('rmt-dark');
                const base = isDark ? '/images/logo/cacao_logo_white.svg' : '/images/logo/cacao_logo.svg';
                if (img.getAttribute('src') !== base) img.setAttribute('src', base);
            }catch(e){}
        }
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
            try { 
                await api('add', { id: btn.dataset.id, qty: 1, nonce: makeNonce() }); 
                await refreshCart(); 
                const productTitle = btn.closest('.uk-card-body')?.querySelector('h5')?.textContent || '<?php echo Text::_('COM_RADICALMART_TELEGRAM_PRODUCT'); ?>';
                showAddedToCartModal(productTitle);
            }
            catch(e){ UIkit.notification(e.message, {status:'danger'}); }
            finally { btn.disabled = false; }
        });

        function showAddedToCartModal(productTitle) {
            const infoEl = document.getElementById('added-product-info');
            if (infoEl) {
                infoEl.textContent = productTitle;
            }
            try {
                UIkit.modal('#added-to-cart-modal').show();
            } catch(e) {
                // Fallback to notification
                UIkit.notification('<?php echo Text::_('COM_RADICALMART_TELEGRAM_ADDED_TO_CART'); ?>: ' + productTitle, {status:'success'});
            }
        }
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
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('DOMContentLoaded: starting initialization...');
            // Remove Joomla's contentpane class to avoid unwanted paddings
            try { document.body.classList.remove('contentpane'); } catch(e){}
            try { RMT_DEBUG_ICONS && RMT_DEBUG_ICONS(); } catch(e){}
            // Try to force UIkit SVG icons first
            try { RMT_FORCE_UKIT_ICONS && RMT_FORCE_UKIT_ICONS(); } catch(e){}
            // Remove any previously applied fallbacks if SVG appeared
            try { RMT_REMOVE_ICON_FALLBACK && RMT_REMOVE_ICON_FALLBACK(); } catch(e){}
            // Ensure theme toggle has a visible icon
            try { RMT_ENSURE_THEME_TOGGLE_ICON && RMT_ENSURE_THEME_TOGGLE_ICON(); } catch(e){}
            // If icons still not rendered by UIkit and fallback is allowed, apply text-based fallback
            try { if (RMT_ICON_FALLBACK_ENABLED && (RMT_ICONS_MISSING_COUNT&&RMT_ICONS_MISSING_COUNT())>0) { RMT_APPLY_ICON_FALLBACK && RMT_APPLY_ICON_FALLBACK(); } } catch(e){}
            // Start observer to auto-remove fallbacks on future SVG insertions
            try { RMT_OBSERVE_ICONS(); } catch(e){}
            initTheme();
            const ok = await ensureConsents();
            if (ok) { await initApp(); }
            // Re-check icons after app init
            setTimeout(() => {
                try { RMT_DEBUG_ICONS && RMT_DEBUG_ICONS(); } catch(e){}
                try { RMT_FORCE_UKIT_ICONS && RMT_FORCE_UKIT_ICONS(); } catch(e){}
                try { RMT_REMOVE_ICON_FALLBACK && RMT_REMOVE_ICON_FALLBACK(); } catch(e){}
                try { RMT_ENSURE_THEME_TOGGLE_ICON && RMT_ENSURE_THEME_TOGGLE_ICON(); } catch(e){}
                try { if (RMT_ICON_FALLBACK_ENABLED && (RMT_ICONS_MISSING_COUNT&&RMT_ICONS_MISSING_COUNT())>0) { RMT_APPLY_ICON_FALLBACK && RMT_APPLY_ICON_FALLBACK(); } } catch(e){}
            }, 1000);
        });
        document.addEventListener('click', (ev) => { const btn = ev.target.closest('#orders-more'); if (!btn) return; ev.preventDefault(); loadOrders(false); });
        document.addEventListener('change', (ev) => { const sel = ev.target.closest('#orders-status'); if (!sel) return; ev.preventDefault(); loadOrders(true); });
    </script>
</head>
<body>

<!-- Fullscreen Document Modal (вынесен НАД оверлеем, чтобы быть поверх) -->
<div id="doc-modal" class="uk-modal-full" uk-modal>
    <div class="uk-modal-dialog uk-modal-body uk-height-viewport" style="z-index: 100000;">
        <button class="uk-modal-close-full" type="button" uk-close></button>
        <div id="doc-modal-body" class="uk-overflow-auto uk-padding-small" style="height: calc(100vh - 120px);"></div>
        <div class="uk-margin-top uk-flex uk-flex-between">
            <button id="doc-decline-btn" class="uk-button uk-button-default"><?php echo Text::_('COM_RADICALMART_TELEGRAM_DOC_DECLINE'); ?></button>
            <button id="doc-accept-btn" class="uk-button uk-button-primary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_DOC_ACCEPT'); ?></button>
        </div>
    </div>
</div>

<div id="consent-overlay">
    <div class="consent-wrap">
        <div id="consent-card" class="uk-card uk-card-default uk-card-body">
            <h4 class="uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_TITLE'); ?></h4>
            <p class="uk-text-meta"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_TEXT'); ?></p>
            <ul id="consent-docs" class="uk-list uk-list-divider uk-margin-small"></ul>
            <div class="uk-margin-small">
                <label class="uk-display-block"><input id="consent-personal" class="uk-checkbox" type="checkbox" onchange="updateConsentSubmitState()"> Согласен на обработку <a href="#" onclick="openDocModal('privacy');return false;" class="uk-link uk-text-bold"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LINK_PERSONAL_DATA'); ?></a></label>
            </div>
            <div class="uk-margin-small">
                <label class="uk-display-block"><input id="consent-terms" class="uk-checkbox" type="checkbox" onchange="updateConsentSubmitState()"> Принимаю <a href="#" onclick="openDocModal('terms');return false;" class="uk-link uk-text-bold"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LINK_TERMS'); ?></a> / оферту</label>
            </div>
            <div class="uk-margin-small">
                <label class="uk-display-block"><input id="consent-marketing" class="uk-checkbox" type="checkbox"> Хочу получать <a href="#" onclick="openDocModal('marketing');return false;" class="uk-link uk-text-bold"><?php echo Text::_('COM_RADICALMART_TELEGRAM_LINK_MARKETING'); ?></a> <span class="uk-text-meta">(опционально)</span></label>
            </div>
            <div class="uk-margin">
                <label><input id="consent-all" class="uk-checkbox" type="checkbox" onchange="toggleAcceptAll(event)"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_ACCEPT_ALL'); ?></label>
            </div>
            <div class="uk-margin-top">
                <button id="consent-submit" class="uk-button uk-button-primary" onclick="submitConsents()" disabled><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONSENT_ACCEPT'); ?></button>
            </div>
        </div>
    </div>
    </div>

<style>
  #app-top-nav { min-height: 44px; }
  #app-top-nav .uk-navbar-right {margin-right: 10px; }
  #app-top-nav .uk-navbar-item { min-height: 44px; padding-top: 4px; padding-bottom: 4px; }
  #app-top-nav .uk-logo { margin-left: 10px; }
  #app-top-nav .uk-logo img { height: 32px; display: block; }

</style>
<nav id="app-top-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-left">
        <a class="uk-navbar-item uk-logo" href="#" title="cacao.land">
            <img id="brand-logo" src="/images/logo/cacao_logo.svg" alt="cacao.land">
        </a>
    </div>
    <div class="uk-navbar-right">
        <div class="uk-navbar-item">
            <a href="#" id="top-search-toggle" class="uk-icon-link" uk-icon="icon: search" title="<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_TITLE'); ?>" onclick="toggleTopSearch(); return false;"></a>
        </div>
        <div class="uk-navbar-item">
            <a href="#" id="profile-top" class="uk-icon-link" uk-icon="icon: user" title="<?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?>" onclick="document.getElementById('profile')?.scrollIntoView({behavior:'smooth'}); return false;"></a>
        </div>
    </div>
    <script>
        function toggleTopSearch(force){
            try{
                const box = document.getElementById('top-search');
                if (!box) return;
                const toShow = (typeof force === 'boolean') ? force : box.hasAttribute('hidden');
                if (toShow) { box.removeAttribute('hidden'); setTimeout(()=>{ document.getElementById('top-search-input')?.focus(); }, 0); }
                else { box.setAttribute('hidden',''); }
                try { RMT_FORCE_UKIT_ICONS && RMT_FORCE_UKIT_ICONS(); RMT_REMOVE_ICON_FALLBACK && RMT_REMOVE_ICON_FALLBACK(); } catch(e){}
            }catch(e){}
            return false;
        }
        function openSearchFromTop(ev){
            try{ ev?.preventDefault?.(); }catch(e){}
            try{
                const q = (document.getElementById('top-search-input')?.value||'').trim();
                const input = document.getElementById('search-input');
                if (input) { input.value = q; try { onSearchInput({ target: input }); } catch(e){} }
                // Open modal to show results if exists
                try{ UIkit.modal('#search-modal').show(); }catch(e){}
            }catch(e){}
            return false;
        }
    </script>
</nav>

<!-- Collapsible top search bar -->
<div id="top-search" class="uk-container uk-padding-small" hidden>
    <form class="uk-form" onsubmit="openSearchFromTop(event)">
        <div class="uk-flex uk-flex-middle" style="gap:8px">
            <span uk-icon="search"></span>
            <input id="top-search-input" class="uk-input" type="text" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_INPUT_PLACEHOLDER'); ?>" />
            <button type="button" class="uk-button uk-button-default" onclick="toggleTopSearch(false)" title="<?php echo Text::_('JCLOSE'); ?>"><span uk-icon="close"></span></button>
        </div>
    </form>
    <div class="uk-margin-small" id="top-search-hint" class="uk-text-meta"></div>
    <!-- Результаты показываем в модальном окне search-modal -->

</div>

<div class="uk-section uk-section-default">
    <div class="uk-container">
        <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1">
                <h3 id="catalog" class="tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></h3>
                <div class="uk-flex uk-flex-middle uk-margin-small" style="gap:8px">
                    <button type="button" id="btn-sort" class="uk-icon-button" uk-tooltip="title: <?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT'); ?>" uk-icon="list"></button>
                    <button type="button" id="btn-filters" class="uk-icon-button" uk-tooltip="title: <?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTERS'); ?>" uk-icon="settings"></button>
                    <div id="active-filter-tags" class="uk-flex uk-flex-wrap" style="gap:6px"></div>
                    <input type="hidden" id="filter-sort" value="">
                </div>
                <!-- Filters Modal -->
                <div id="filters-modal" class="uk-flex-top" uk-modal>
                    <div class="uk-modal-dialog uk-modal-body uk-margin-auto-vertical">
                        <button class="uk-modal-close-default" type="button" uk-close></button>
                        <h4 class="uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTERS'); ?></h4>
                        <div class="uk-grid-small uk-margin-small" uk-grid>
                            <div class="uk-width-1-1">
                                <label><input id="filter-instock" class="uk-checkbox" type="checkbox"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_ONLY_IN_STOCK'); ?></label>
                            </div>
                            <div class="uk-width-1-1">
                                <label class="uk-text-small uk-text-muted"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PRICE'); ?></label>
                            </div>
                            <div class="uk-flex uk-flex-middle uk-width-1-1" style="gap:6px">
                                <input id="price-from" class="uk-input" type="number" min="0" step="0.01" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_FROM'); ?>" style="max-width:140px">
                                <input id="price-to" class="uk-input" type="number" min="0" step="0.01" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_TO'); ?>" style="max-width:140px">
                            </div>
                        </div>
                        <div id="filters-modal-fields">
                <?php
                // Resolve RadicalMart fields (id -> alias/title) to render configured filters
                $filtersCfg = (array) ($this->params->get('filters_fields') ?: []);
                $rmtFieldsMap = [];
                if (!empty($filtersCfg)) {
                    try {
                        $db = Factory::getContainer()->get('DatabaseDriver');
                        $q = $db->getQuery(true)
                            ->select($db->qn(['id','title','alias','plugin','params','options']))
                            ->from($db->qn('#__radicalmart_fields'))
                            ->where($db->qn('state') . ' = 1')
                            ->where($db->qn('area') . ' = ' . $db->q('products'));
                        $db->setQuery($q);
                        $rows = (array)$db->loadObjectList();
                        foreach ($rows as $r) {
                            $ui = '';
                            $opts = [];
                            try {
                                $pp = json_decode((string)$r->params, true) ?: [];
                                // Для фильтров в RadicalMart используется display_filter_as (list|checkboxes|images)
                                $ui = (string)($pp['display_filter_as'] ?? $pp['display_variability_as'] ?? $pp['display_as'] ?? $pp['display'] ?? '');
                                // Heuristics to extract options for selects if provided in params
                                if (isset($pp['options']) && is_array($pp['options'])) { $opts = $pp['options']; }
                                elseif (isset($pp['values']) && is_array($pp['values'])) { $opts = $pp['values']; }
                                elseif (isset($pp['choices']) && is_array($pp['choices'])) { $opts = $pp['choices']; }
                                elseif (isset($pp['variations']) && is_array($pp['variations'])) { $opts = $pp['variations']; }
                                // If the table has JSON options column, prefer it (same structure as plugin saves)
                                $colOpts = json_decode((string)$r->options, true);
                                if (is_array($colOpts) && !empty($colOpts)) { $opts = $colOpts; }
                            } catch (\Throwable $e) {}
                            $rmtFieldsMap[(int)$r->id] = [
                                'title' => (string)$r->title,
                                'alias' => (string)$r->alias,
                                'ui'    => $ui,
                                'opts'  => $opts,
                            ];
                        }
                    } catch (\Throwable $e) {}
                }
                if (!empty($filtersCfg)):
                ?>
                <div class="uk-grid-small uk-margin-small" uk-grid>
                    <?php foreach ($filtersCfg as $f):
                        $f = (array) $f; // Convert stdClass to array
                        if (empty($f['enabled']) || (int)$f['enabled'] !== 1) continue;
                        $fid = (int)($f['field_id'] ?? 0);
                        if (!$fid || empty($rmtFieldsMap[$fid]['alias'])) continue;
                        $alias = trim((string)$rmtFieldsMap[$fid]['alias']);
                        if ($alias==='') continue;
                        $type = (string)($f['type']??'auto');
                        $rmUi = (string)($rmtFieldsMap[$fid]['ui'] ?? '');
                        // Map RadicalMart UI to our control types if Auto selected (filters: list|checkboxes|images)
                        if ($type === 'auto') {
                            $t = strtolower($rmUi);
                            // Для UX в WebApp по умолчанию отображаем RM list как кнопки
                            if (in_array($t, ['buttons','chips','pills','images','checkboxes','list'], true)) $type = 'buttons';
                            elseif (in_array($t, ['select','dropdown','radio'], true)) $type = 'select';
                            elseif (in_array($t, ['checkbox','switch','toggle'], true)) $type = 'checkbox';
                            elseif (in_array($t, ['range','slider'], true)) $type = 'range';
                            elseif (in_array($t, ['number','numeric'], true)) $type = 'number';
                            else $type = 'text';
                        }
                        $label = (string)($f['label'] ?? $rmtFieldsMap[$fid]['title'] ?? $alias);
                        $ph=(string)($f['placeholder']??'');
                        $phf=(string)($f['placeholder_from']??'');
                        $pht=(string)($f['placeholder_to']??'');
                        $customOptsText=(string)($f['options']??'');
                        $rmOpts = $rmtFieldsMap[$fid]['opts'] ?? [];
                        // Построим список опций один раз, чтобы использовать и для select, и для buttons
                        $builtOptions = [];
                        if ($customOptsText !== '') {
                            $lines=preg_split('#\r?\n#',$customOptsText);
                            foreach ($lines as $ln){ $ln=trim($ln); if ($ln==='') continue; $parts=explode('|',$ln,2); $val=trim($parts[0]); $lab=trim($parts[1]??$parts[0]); $builtOptions[]=['value'=>$val,'label'=>$lab]; }
                        } elseif (!empty($rmOpts)) {
                            foreach ($rmOpts as $k=>$v){
                                if (is_array($v)){
                                    $val = (string)($v['value'] ?? $v['val'] ?? $v['id'] ?? $k);
                                    $lab = (string)($v['label'] ?? $v['text'] ?? $v['title'] ?? $val);
                                } elseif (is_object($v)) {
                                    $val = (string)($v->value ?? $v->val ?? $v->id ?? $k);
                                    $lab = (string)($v->label ?? $v->text ?? $v->title ?? $val);
                                } else {
                                    $val = is_int($k) ? (string)$v : (string)$k; $lab = (string)$v;
                                }
                                if ($val !== '') { $builtOptions[]=['value'=>$val,'label'=>$lab]; }
                            }
                        }
                    ?>
                        <div>
                            <label class="uk-form-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="uk-form-controls">
                                <?php if ($type==='select'): ?>
                                    <select class="uk-select" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="select" data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                        <option value=""></option>
                                        <?php foreach ($builtOptions as $op): ?>
                                            <option value="<?php echo htmlspecialchars($op['value'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($op['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($type==='buttons'): ?>
                                    <div class="rmt-filter-buttons" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="buttons" data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" data-value="">
                                        <button type="button" class="uk-button uk-button-default rmt-filter-btn-all" data-all="1" data-value="" aria-pressed="false"><?php echo Text::_('JALL'); ?></button>
                                        <?php foreach ($builtOptions as $op): ?>
                                            <button type="button" class="uk-button uk-button-default rmt-filter-btn" data-value="<?php echo htmlspecialchars($op['value'], ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="false"><?php echo htmlspecialchars($op['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($type==='checkbox'): ?>
                                    <input type="checkbox" class="uk-checkbox" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="checkbox" data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php elseif ($type==='range'): ?>
                                    <div class="uk-grid-small" uk-grid data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div><input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="range" data-field-range="from" placeholder="<?php echo htmlspecialchars($phf ?: Text::_('COM_RADICALMART_TELEGRAM_FROM'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width:130px"></div>
                                        <div><input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="range" data-field-range="to" placeholder="<?php echo htmlspecialchars($pht ?: Text::_('COM_RADICALMART_TELEGRAM_TO'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width:130px"></div>
                                    </div>
                                <?php elseif ($type==='number'): ?>
                                    <input type="number" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="number" data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php else: ?>
                                    <input type="text" class="uk-input" data-field-alias="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>" data-field-type="text" data-field-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                        </div> <!-- /#filters-modal-fields -->
                        <div class="uk-flex uk-flex-right uk-margin">
                            <button type="button" id="filters-reset" class="uk-button uk-button-default uk-button-small"><?php echo Text::_('COM_RADICALMART_TELEGRAM_FILTERS_RESET'); ?></button>
                            <button type="button" id="filters-apply" class="uk-button uk-button-primary uk-button-small uk-margin-small-left"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?></button>
                        </div>
                    </div>
                </div>
                <!-- Sort Modal -->
                <div id="sort-modal" class="uk-flex-top" uk-modal>
                    <div class="uk-modal-dialog uk-modal-body uk-margin-auto-vertical">
                        <button class="uk-modal-close-default" type="button" uk-close></button>
                        <h4 class="uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT'); ?></h4>
                        <div class="uk-form-stacked">
                            <label><input class="uk-radio" type="radio" name="sort-radio" value="" checked> <?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_DEFAULT'); ?></label><br>
                            <label><input class="uk-radio" type="radio" name="sort-radio" value="new"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_NEW'); ?></label><br>
                            <label><input class="uk-radio" type="radio" name="sort-radio" value="price_asc"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_PRICE_ASC'); ?></label><br>
                            <label><input class="uk-radio" type="radio" name="sort-radio" value="price_desc"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_SORT_PRICE_DESC'); ?></label>
                        </div>
                        <div class="uk-flex uk-flex-right uk-margin">
                            <button type="button" class="uk-button uk-button-default uk-modal-close"><?php echo Text::_('JCANCEL'); ?></button>
                            <button type="button" id="sort-apply" class="uk-button uk-button-primary uk-margin-small-left"><?php echo Text::_('COM_RADICALMART_TELEGRAM_APPLY'); ?></button>
                        </div>
                    </div>
                </div>
                <div class="uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid id="catalog-list"></div>
            </div>

        </div>

    </div>

</div>

<!-- Bottom fixed nav -->
<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li class="uk-active">
                <a href="index.php?option=com_radicalmart_telegram&view=app" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="index.php?option=com_radicalmart_telegram&view=cart" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                    <span id="cart-badge" hidden>0</span>
                </a>
            </li>
            <li>
                <a href="index.php?option=com_radicalmart_telegram&view=orders" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="index.php?option=com_radicalmart_telegram&view=profile" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Cookies banner -->
<div id="cookie-banner" class="uk-alert uk-alert-primary uk-margin-small">
        <div class="uk-flex uk-flex-middle uk-flex-between">
                <div>
                        <div class="uk-text-bold tg-safe-text" style="margin-bottom:4px"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_BANNER_TITLE'); ?></div>
                        <div class="uk-text-small tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_BANNER_TEXT'); ?></div>
                </div>
                <div class="uk-flex uk-flex-middle uk-margin-small-left">
                        <button type="button" class="uk-button uk-button-default uk-button-small uk-margin-small-right" onclick="openCookieModal()"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_SETTINGS'); ?></button>
                        <button type="button" class="uk-button uk-button-primary uk-button-small" onclick="acceptAllCookies()"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_ACCEPT_ALL'); ?></button>
                </div>
        </div>
        <button class="uk-alert-close" uk-close onclick="this.parentElement.style.display='none'"></button>
        </div>

<!-- Cookie settings floating button -->
<button id="cookie-settings-btn" class="uk-button uk-button-default uk-button-small" onclick="openCookieModal()"><span uk-icon="cog"></span> <span class="uk-visible@s"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_SETTINGS'); ?></span></button>

<!-- Cookie settings modal -->
<div id="cookie-modal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <h4 class="uk-margin-remove tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_BANNER_TITLE'); ?></h4>
        <div class="uk-margin-small-top">
            <label class="uk-display-block"><input class="uk-checkbox" type="checkbox" checked disabled> <?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_ESSENTIAL'); ?></label>
            <label class="uk-display-block"><input id="cookie-analytics" class="uk-checkbox" type="checkbox"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_ANALYTICS'); ?></label>
            <div id="cookie-dnt-note" class="uk-text-meta uk-margin-small" hidden><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_DNT_ACTIVE'); ?></div>
            <label class="uk-display-block"><input id="cookie-marketing" class="uk-checkbox" type="checkbox"> <?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_CATEGORY_MARKETING'); ?></label>
        </div>
        <div class="uk-margin-top uk-flex uk-flex-right">
            <button class="uk-button uk-button-default uk-margin-small-right" onclick="UIkit.modal('#cookie-modal').hide()"><?php echo Text::_('JCANCEL'); ?></button>
            <button class="uk-button uk-button-primary" onclick="saveCookieSettings()"><?php echo Text::_('COM_RADICALMART_TELEGRAM_COOKIES_SAVE'); ?></button>
        </div>
    </div>
    <script>try{ UIkit.util.on('#cookie-modal','show',applyCookieUIFromPrefs);}catch(e){}</script>
</div>

<!-- Search Modal -->
<div id="search-modal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
    <h4 class="uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_TITLE'); ?></h4>
    <input id="search-input" class="uk-input uk-margin-small" type="text" placeholder="<?php echo Text::_('COM_RADICALMART_TELEGRAM_SEARCH_INPUT_PLACEHOLDER'); ?>" oninput="onSearchInput(event)">
        <div id="search-results" class="uk-margin-small"></div>
    </div>
</div>

<!-- Added to Cart Modal -->
<div id="added-to-cart-modal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body uk-text-center">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <div uk-icon="icon: check; ratio: 3" class="uk-text-success"></div>
        <h3 class="uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ADDED_TO_CART'); ?></h3>
        <p id="added-product-info" class="uk-text-meta"></p>
        <div class="uk-margin uk-flex uk-flex-center" style="gap: 8px;">
            <button type="button" class="uk-button uk-button-default uk-modal-close"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CONTINUE_SHOPPING'); ?></button>
            <a href="index.php?option=com_radicalmart_telegram&view=cart" class="uk-button uk-button-primary"><?php echo Text::_('COM_RADICALMART_TELEGRAM_GO_TO_CART'); ?></a>
        </div>
    </div>
</div>
</body>
</html>
