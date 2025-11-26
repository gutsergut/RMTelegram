<?php
/**
 * Orders View - based on RadicalMart orders template
 * @package     com_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Orders\HtmlView $this */

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$tgInit = $app->input->get('tg_init', '', 'raw');
$chat = $app->input->getInt('chat', 0);

// Build base URL with tg params
$baseParams = [];
if ($tgInit) {
    $baseParams['tg_init'] = $tgInit;
}
if ($chat) {
    $baseParams['chat'] = $chat;
}
$baseQuery = $baseParams ? '&' . http_build_query($baseParams) : '';
?>
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <style>
        html, body {
            background: #fff;
            color: #222;
            margin: 0;
            padding: 0;
        }
        body { padding-bottom: 70px; }

        /* Bottom nav */
        #app-bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-top: 1px solid #e5e5e5; padding-bottom: env(safe-area-inset-bottom); }
        #app-bottom-nav .uk-navbar-nav > li > a { min-height: 56px; padding: 8px 12px; font-size: 11px; line-height: 1.2; text-transform: none; color: #999; }
        #app-bottom-nav .uk-navbar-nav > li.uk-active > a { color: #1e87f0; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
        #app-bottom-nav .uk-icon { margin-bottom: 2px; }
        #app-bottom-nav .caption { font-size: 10px; }

        /* Order cards */
        .order-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 12px; overflow: hidden; }
        .order-card-header { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
        .order-card-body { padding: 12px 16px; }
        .order-number { font-weight: 600; font-size: 16px; color: #333; }
        .order-date { font-size: 13px; color: #999; margin-left: 8px; }
        .order-info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
        .order-info-row:last-child { border-bottom: none; }
        .order-info-label { color: #666; }
        .order-info-value { color: #333; font-weight: 500; text-align: right; }
        .order-status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .order-link { text-decoration: none; color: inherit; display: block; }
        .order-link:hover { background: #fafafa; }

        /* Status filter */
        .status-filter { margin-bottom: 16px; }
        .status-filter select { font-size: 14px; }

        /* Empty state */
        .empty-orders { text-align: center; padding: 40px 20px; }
        .empty-orders-icon { color: #ddd; margin-bottom: 16px; }
        .empty-orders-text { color: #999; font-size: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="uk-container uk-container-small uk-padding-small">
        <!-- Header -->
        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
            <h1 class="uk-h3 uk-margin-remove"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></h1>
            <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="uk-icon-link" uk-icon="icon: close"></a>
        </div>

        <?php if (!empty($this->statuses)): ?>
        <!-- Status filter -->
        <div class="status-filter">
            <select id="status-filter" class="uk-select" onchange="filterByStatus(this.value)">
                <option value=""><?php echo Text::_('COM_RADICALMART_TELEGRAM_ALL_STATUSES'); ?></option>
                <?php foreach ($this->statuses as $status): ?>
                <option value="<?php echo (int) $status->id; ?>" <?php echo ($this->currentStatus == $status->id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($status->title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (empty($this->items)): ?>
        <!-- Empty state -->
        <div class="empty-orders">
            <div class="empty-orders-icon">
                <span uk-icon="icon: file-text; ratio: 3"></span>
            </div>
            <div class="empty-orders-text">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_NO_ORDERS'); ?>
            </div>
            <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>" class="uk-button uk-button-primary">
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_GO_TO_CATALOG'); ?>
            </a>
        </div>
        <?php else: ?>
        <!-- Orders list -->
        <div id="orders-list">
            <?php foreach ($this->items as $item): ?>
            <div class="order-card">
                <a href="<?php echo htmlspecialchars($item->link); ?>" class="order-link" target="_blank">
                    <div class="order-card-header uk-flex uk-flex-between uk-flex-middle">
                        <div>
                            <span class="order-number"><?php echo htmlspecialchars($item->title); ?></span>
                            <span class="order-date">
                                <?php echo Text::sprintf('COM_RADICALMART_DATE_FROM', HTMLHelper::date($item->created, Text::_('DATE_FORMAT_LC2'))); ?>
                            </span>
                        </div>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                    <div class="order-card-body">
                        <div class="order-info-row">
                            <span class="order-info-label"><?php echo Text::_('COM_RADICALMART_PRODUCTS'); ?></span>
                            <span class="order-info-value"><?php echo count($item->products); ?></span>
                        </div>

                        <?php if ($item->shipping && $item->shipping->get('title')): ?>
                        <div class="order-info-row">
                            <span class="order-info-label"><?php echo Text::_('COM_RADICALMART_SHIPPING'); ?></span>
                            <span class="order-info-value"><?php echo htmlspecialchars($item->shipping->get('title')); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($item->payment && $item->payment->get('title')): ?>
                        <div class="order-info-row">
                            <span class="order-info-label"><?php echo Text::_('COM_RADICALMART_PAYMENT'); ?></span>
                            <span class="order-info-value"><?php echo htmlspecialchars($item->payment->get('title')); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="order-info-row">
                            <span class="order-info-label"><?php echo Text::_('COM_RADICALMART_TOTAL'); ?></span>
                            <span class="order-info-value"><?php echo $item->total['final_string'] ?? '-'; ?></span>
                        </div>

                        <div class="order-info-row">
                            <span class="order-info-label"><?php echo Text::_('COM_RADICALMART_ORDER_STATUS'); ?></span>
                            <span class="order-info-value">
                                <?php if ($item->status): ?>
                                <span class="order-status-badge <?php echo htmlspecialchars($item->status->params->get('class_site', '')); ?>">
                                    <?php echo htmlspecialchars($item->status->title); ?>
                                </span>
                                <?php else: ?>
                                <span class="order-status-badge uk-label-danger">
                                    <?php echo Text::_('COM_RADICALMART_ERROR_STATUS_NOT_FOUND'); ?>
                                </span>
                                <?php endif; ?>
                            </span>
                        </div>
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
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>">
                        <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart<?php echo $baseQuery; ?>">
                        <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span>
                    </a>
                </li>
                <li class="uk-active">
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>">
                        <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>"
                        <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script>
        // Initialize Telegram WebApp
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (window.Telegram && window.Telegram.WebApp) {
                    Telegram.WebApp.ready();
                    Telegram.WebApp.expand();
                    
                    // Store initData for use in navigation
                    window.TG_INIT_DATA = Telegram.WebApp.initData || '';
                }
            } catch(e) {}

            // Refresh WebApp cookie
            try {
                document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax';
            } catch(e) {}
        });

        // Status filter - preserves tg_init param
        function filterByStatus(statusId) {
            const url = new URL(window.location.href);
            if (statusId) {
                url.searchParams.set('status', statusId);
            } else {
                url.searchParams.delete('status');
            }
            // Add tg_init if available from Telegram WebApp
            if (window.TG_INIT_DATA && !url.searchParams.has('tg_init')) {
                url.searchParams.set('tg_init', window.TG_INIT_DATA);
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

