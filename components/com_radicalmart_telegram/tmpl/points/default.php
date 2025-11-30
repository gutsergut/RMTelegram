<?php
/**
 * Points history template (standalone WebApp)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

$app = Factory::getApplication();
$input = $app->getInput();
$root = Uri::root();

// Get chat parameter
$chat = $input->getInt('chat', 0);
$chatParam = $chat ? '&chat=' . $chat : '';
$baseQuery = $chatParam;

// Get points data from View
$balance = $this->points ?? 0;
$history = $this->items ?? [];
$hasMore = $this->hasMore ?? false;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
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

        /* Points specific styles */
        .points-balance {
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 0 0 20px 20px;
        }
        .balance-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .balance-value {
            font-size: 48px;
            font-weight: 700;
        }
        .balance-suffix {
            font-size: 18px;
            opacity: 0.9;
        }
        .points-history {
            padding: 15px;
        }
        .history-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #222;
        }
        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .history-item {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .points-positive {
            color: #4caf50;
            font-weight: 600;
            font-size: 18px;
        }
        .points-negative {
            color: #f44336;
            font-weight: 600;
            font-size: 18px;
        }
        .context-label {
            color: #999;
            font-size: 13px;
            margin-top: 4px;
        }
        .date-time {
            text-align: right;
            color: #999;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .load-more-btn {
            width: 100%;
            padding: 15px;
            background: #3390ec;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        .load-more-btn:disabled {
            opacity: 0.6;
        }
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
    <!-- Points Balance Header -->
    <div class="points-balance">
        <div class="balance-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_BALANCE'); ?></div>
        <div class="balance-value"><?php echo number_format($balance, 0, '', ' '); ?></div>
        <div class="balance-suffix"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_SUFFIX'); ?></div>
    </div>

    <!-- Points History -->
    <div class="points-history">
        <div class="history-title"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_HISTORY'); ?></div>

        <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <div><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_NO_HISTORY'); ?></div>
            </div>
        <?php else: ?>
            <ul class="history-list" id="points-list">
                <?php foreach ($history as $item):
                    $points = (float)($item->points ?? 0);
                    $contextLabel = method_exists($this, 'getContextLabel') ? $this->getContextLabel($item) : ($item->context ?? '');
                    $created = $item->created ?? '';
                    $date = $created ? Factory::getDate($created)->format('d.m.Y') : '';
                    $time = $created ? Factory::getDate($created)->format('H:i') : '';
                ?>
                    <li class="history-item">
                        <div class="uk-flex uk-flex-between uk-flex-middle">
                            <div class="uk-width-expand">
                                <div class="<?php echo $points > 0 ? 'points-positive' : 'points-negative'; ?>">
                                    <?php echo $points > 0 ? '+' : ''; ?><?php echo number_format($points, 0, '', ' '); ?>
                                </div>
                                <div class="context-label"><?php echo htmlspecialchars($contextLabel); ?></div>
                            </div>
                            <div class="date-time">
                                <div><?php echo $date; ?></div>
                                <div><?php echo $time; ?></div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($hasMore): ?>
                <div id="load-more-container">
                    <button type="button" class="load-more-btn" id="load-more-btn" data-start="10">
                        <?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=app<?php echo $chatParam; ?>" class="nav-item">
            <span uk-icon="icon: home; ratio: 1.2"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_NAV_CATALOG'); ?>
        </a>
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=cart<?php echo $chatParam; ?>" class="nav-item">
            <span uk-icon="icon: cart; ratio: 1.2"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_NAV_CART'); ?>
        </a>
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=orders<?php echo $chatParam; ?>" class="nav-item">
            <span uk-icon="icon: list; ratio: 1.2"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_NAV_ORDERS'); ?>
        </a>
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>" class="nav-item active">
            <span uk-icon="icon: user; ratio: 1.2"></span>
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_NAV_PROFILE'); ?>
        </a>
    </nav>

    <!-- Scripts -->
    <script src="<?php echo $root; ?>templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script>
        // Initialize Telegram WebApp
        if (window.Telegram && Telegram.WebApp) {
            Telegram.WebApp.ready();
            Telegram.WebApp.expand();
        }

        // Apply light theme
        function initTheme() {
            document.body.classList.add('light');
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#000000');
            document.documentElement.style.setProperty('--tg-theme-hint-color', '#999999');
            document.documentElement.style.setProperty('--tg-theme-secondary-bg-color', '#f5f5f5');
        }

        // Initialize BackButton
        function initBackButton() {
            try {
                if (window.Telegram && Telegram.WebApp && Telegram.WebApp.BackButton) {
                    Telegram.WebApp.BackButton.show();
                    Telegram.WebApp.BackButton.onClick(function() {
                        window.location.href = '<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>';
                    });
                }
            } catch(e) {
                console.log('BackButton error:', e);
            }
        }

        // Load more functionality
        function initLoadMore() {
            var loadMoreBtn = document.getElementById('load-more-btn');
            if (!loadMoreBtn) return;

            loadMoreBtn.addEventListener('click', function() {
                var start = parseInt(this.dataset.start);
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_LOADING'); ?>';

                var url = '<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=points&format=json<?php echo $baseQuery; ?>&start=' + start;

                fetch(url)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.items && data.items.length > 0) {
                            var list = document.getElementById('points-list');
                            data.items.forEach(function(item) {
                                var li = document.createElement('li');
                                li.className = 'history-item';
                                li.innerHTML = '<div class="uk-flex uk-flex-between uk-flex-middle">' +
                                    '<div class="uk-width-expand">' +
                                    '<div class="' + (item.points > 0 ? 'points-positive' : 'points-negative') + '">' +
                                    (item.points > 0 ? '+' : '') + item.points_formatted +
                                    '</div>' +
                                    '<div class="context-label">' + item.context_label + '</div>' +
                                    '</div>' +
                                    '<div class="date-time">' +
                                    '<div>' + item.date + '</div>' +
                                    '<div>' + item.time + '</div>' +
                                    '</div>' +
                                    '</div>';
                                list.appendChild(li);
                            });

                            if (data.hasMore) {
                                btn.dataset.start = start + 10;
                                btn.disabled = false;
                                btn.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?>';
                            } else {
                                document.getElementById('load-more-container').style.display = 'none';
                            }
                        } else {
                            document.getElementById('load-more-container').style.display = 'none';
                        }
                    })
                    .catch(function(err) {
                        console.error('Error loading more points:', err);
                        btn.disabled = false;
                        btn.innerHTML = '<?php echo Text::_('COM_RADICALMART_TELEGRAM_LOAD_MORE'); ?>';
                    });
            });
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Force light theme again
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
            document.body.style.backgroundColor = '#ffffff';
            document.body.style.color = '#222222';

            initTheme();
            initBackButton();
            initLoadMore();
        });
    </script>

    <!-- Bottom fixed nav -->
    <div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
        <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
            <ul class="uk-navbar-nav">
                <li>
                    <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=cart<?php echo $baseQuery; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                    </a>
                </li>
                <li class="uk-active">
                    <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>" class="tg-safe-text">
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
