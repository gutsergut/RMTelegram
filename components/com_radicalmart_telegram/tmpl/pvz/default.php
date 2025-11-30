<?php
/**
 * PVZ view template
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

$root = rtrim(Uri::root(), '/');
$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeTitle . ' - Пункты выдачи', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <?php $ymKey = isset($this->params) ? (string) $this->params->get('yandex_maps_api_key', '') : ''; ?>
    <?php if ($ymKey): ?>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?php echo htmlspecialchars($ymKey, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
    <style>
        html, body { background: #ffffff !important; color: #222 !important; margin: 0; padding: 0; }
        body { padding-bottom: 52px; }
        body.contentpane { padding: 0 !important; margin: 0 !important; }

        /* Bottom nav - same as app page */
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
        #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
        #app-bottom-nav .tg-safe-text { display: inline-flex; align-items: center; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
        #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
        #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }

        #pvz-map { width: 100%; height: 400px; }
    </style>
    <script>
        // Force light theme
        (function(){
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
        })();
    </script>
</head>
<body class="contentpane">

<div class="uk-container uk-container-small uk-padding-small">
    <h2 class="uk-heading-small">Пункты выдачи</h2>
    <div id="pvz-map"></div>
    <div id="pvz-list" class="uk-margin-top">
        <p>Выберите удобный пункт выдачи</p>
    </div>
</div>

<!-- Bottom Navigation -->
<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<script>
    // Initialize Telegram WebApp
    document.addEventListener('DOMContentLoaded', function() {
        // Force light theme
        document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
        document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
        document.body.style.backgroundColor = '#ffffff';
        document.body.style.color = '#222222';

        try {
            if (window.Telegram && Telegram.WebApp) {
                Telegram.WebApp.ready();
                Telegram.WebApp.expand();

                // BackButton - navigate to checkout
                try {
                    Telegram.WebApp.BackButton.show();
                    Telegram.WebApp.BackButton.onClick(function() {
                        window.location.href = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=checkout';
                    });
                } catch(e) { console.log('BackButton error:', e); }
            }
        } catch(e) {}
    });
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
