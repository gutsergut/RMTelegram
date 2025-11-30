<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

$items = $this->items ?? [];
$userId = $this->userId ?? 0;
$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatParam = $chat ? '&chat=' . $chat : '';
?>
<!DOCTYPE html>
<html lang="ru" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO_CODES'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
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

        /* Codes specific styles */
        .codes-container { padding: 15px; max-width: 600px; margin: 0 auto; }
        .page-header { display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; }
        .back-btn { margin-right: 15px; color: #2481cc; text-decoration: none; font-size: 24px; }
        .page-title { margin: 0; font-size: 20px; font-weight: 600; }
        .code-card { background: #f5f5f5; border-radius: 12px; padding: 15px; margin-bottom: 12px; }
        .code-card.expired { opacity: 0.5; }
        .code-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .code-value { font-size: 18px; font-weight: 700; font-family: monospace; color: #2481cc; }
        .code-value.expired { color: #999; text-decoration: line-through; }
        .code-discount { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .code-discount.expired { background: #999; }
        .code-meta { font-size: 13px; color: #999; margin-bottom: 8px; }
        .code-meta-item { margin-bottom: 4px; }
        .code-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-top: 5px; }
        .code-status.active { background: #d4edda; color: #155724; }
        .code-status.expired { background: #f8d7da; color: #721c24; }
        .code-status.used { background: #fff3cd; color: #856404; }
        .code-actions { display: flex; gap: 10px; margin-top: 10px; }
        .copy-btn { flex: 1; padding: 8px 15px; border: none; border-radius: 8px; background: #2481cc; color: #fff; font-size: 14px; cursor: pointer; }
        .copy-btn:disabled { background: #999; cursor: not-allowed; }
        .copy-btn.copied { background: #28a745; }
        .restrictions-block { margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px; font-size: 12px; }
        .restrictions-title { font-weight: 600; margin-bottom: 5px; color: #999; }
        .restrictions-list { margin: 0; padding-left: 15px; }
        .restrictions-list li { margin-bottom: 2px; }
        .restriction-type { font-weight: 500; }
        .restriction-type.apply { color: #28a745; }
        .restriction-type.ignore { color: #dc3545; }
        .empty-state { text-align: center; padding: 40px 20px; color: #999; }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        .login-hint { text-align: center; padding: 40px 20px; color: #999; }
        .load-more-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #f5f5f5; color: #2481cc; font-size: 14px; cursor: pointer; margin-top: 10px; }
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
<div class="codes-container">
    <div class="page-header">
        <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>" class="back-btn">←</a>
        <h1 class="page-title"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO_CODES'); ?></h1>
    </div>

    <?php if ($userId <= 0): ?>
        <div class="login-hint">
            <div class="empty-state-icon">🔒</div>
            <p><?php echo Text::_('COM_RADICALMART_TELEGRAM_BONUSES_LOGIN_HINT'); ?></p>
        </div>
    <?php elseif (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎟️</div>
            <p><?php echo Text::_('COM_RADICALMART_TELEGRAM_CODES_EMPTY'); ?></p>
        </div>
    <?php else: ?>
        <div id="codes-list">
            <?php foreach ($items as $item): ?>
                <div class="code-card <?php echo $item->expired ? 'expired' : ''; ?>">
                    <div class="code-header">
                        <div class="code-value <?php echo $item->expired ? 'expired' : ''; ?>">
                            <?php echo htmlspecialchars($item->code); ?>
                        </div>
                        <div class="code-discount <?php echo $item->expired ? 'expired' : ''; ?>">
                            -<?php echo $item->discount_string; ?>
                        </div>
                    </div>

                    <div class="code-meta">
                        <?php if (!empty($item->end) && $item->end !== '0000-00-00 00:00:00'): ?>
                            <div class="code-meta-item">
                                📅 <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_VALID_UNTIL'); ?>:
                                <?php echo HTMLHelper::date($item->end, Text::_('DATE_FORMAT_LC4')); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($item->orders_limit > 0): ?>
                            <div class="code-meta-item">
                                🔢 <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_USES'); ?>:
                                <?php echo (int) $item->usageCount; ?> / <?php echo (int) $item->orders_limit; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($item->expired): ?>
                        <span class="code-status expired"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_EXPIRED'); ?></span>
                    <?php elseif ($item->usageExceeded): ?>
                        <span class="code-status used"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_USAGE_EXCEEDED'); ?></span>
                    <?php else: ?>
                        <span class="code-status active"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_ACTIVE'); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($item->restrictions['has_restrictions'])): ?>
                        <div class="restrictions-block">
                            <div class="restrictions-title"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_RESTRICTIONS'); ?>:</div>

                            <?php if (!empty($item->restrictions['products'])): ?>
                                <div class="restriction-type <?php echo $item->restrictions['products_rule']; ?>">
                                    <?php if ($item->restrictions['products_rule'] === 'apply'): ?>
                                        ✅ <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_APPLIES_TO_PRODUCTS'); ?>:
                                    <?php else: ?>
                                        ❌ <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_EXCLUDES_PRODUCTS'); ?>:
                                    <?php endif; ?>
                                </div>
                                <ul class="restrictions-list">
                                    <?php foreach ($item->restrictions['products'] as $productName): ?>
                                        <li><?php echo htmlspecialchars($productName); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($item->restrictions['categories'])): ?>
                                <div class="restriction-type <?php echo $item->restrictions['categories_rule']; ?>">
                                    <?php if ($item->restrictions['categories_rule'] === 'apply'): ?>
                                        ✅ <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_APPLIES_TO_CATEGORIES'); ?>:
                                    <?php else: ?>
                                        ❌ <?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE_EXCLUDES_CATEGORIES'); ?>:
                                    <?php endif; ?>
                                </div>
                                <ul class="restrictions-list">
                                    <?php foreach ($item->restrictions['categories'] as $categoryName): ?>
                                        <li><?php echo htmlspecialchars($categoryName); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="code-actions">
                        <button type="button" class="copy-btn" data-code="<?php echo htmlspecialchars($item->code); ?>" <?php echo !$item->enabled ? 'disabled' : ''; ?>>
                            📋 <?php echo Text::_('COM_RADICALMART_TELEGRAM_COPY_CODE'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($this->hasMore): ?>
            <button type="button" class="load-more-btn" id="load-more-btn" data-start="<?php echo $this->start + $this->limit; ?>">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?>
            </button>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Force light theme again
        document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
        document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
        document.body.style.backgroundColor = '#ffffff';
        document.body.style.color = '#222222';

        if (window.Telegram && window.Telegram.WebApp) {
            Telegram.WebApp.ready();
            Telegram.WebApp.expand();
            Telegram.WebApp.BackButton.show();
            Telegram.WebApp.BackButton.onClick(function() {
                window.location.href = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>';
            });
        }
    });

    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var code = this.dataset.code;
            if (!code) return;
            navigator.clipboard.writeText(code).then(function() {
                btn.classList.add('copied');
                var originalText = btn.innerHTML;
                btn.innerHTML = '✅ <?php echo Text::_('COM_RADICALMART_TELEGRAM_COPIED'); ?>';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalText;
                }, 2000);
                if (window.Telegram && window.Telegram.WebApp) {
                    Telegram.WebApp.HapticFeedback.notificationOccurred('success');
                }
            }).catch(function(err) {
                alert('<?php echo Text::_('COM_RADICALMART_TELEGRAM_CODE'); ?>: ' + code);
            });
        });
    });
</script>

<!-- Bottom fixed nav -->
<div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
    <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
        <ul class="uk-navbar-nav">
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $chatParam; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart<?php echo $chatParam; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $chatParam; ?>" class="tg-safe-text">
                    <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                </a>
            </li>
            <li class="uk-active">
                <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>" class="tg-safe-text">
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
