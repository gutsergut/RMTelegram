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
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <!-- CDN fallback for icons -->
    <script>if(!window.UIkit||!UIkit.icon)document.write('<script src="https://cdn.jsdelivr.net/npm/uikit@3.21.6/dist/js/uikit-icons.min.js"><\/script>');</script>
    <style>
        html, body { background: #f8f8f8; color: #222; margin: 0; padding: 0; }
        body { padding-bottom: 70px; }
        body.contentpane { padding: 0 !important; margin: 0 !important; }

        /* Bottom nav */
        #app-bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-top: 1px solid #e5e5e5; padding-bottom: env(safe-area-inset-bottom); }
        #app-bottom-nav .uk-navbar-nav > li > a { min-height: 56px; padding: 8px 12px; font-size: 11px; line-height: 1.2; text-transform: none; color: #999; }
        #app-bottom-nav .uk-navbar-nav > li.uk-active > a { color: #1e87f0; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
        #app-bottom-nav .uk-icon { margin-bottom: 2px; }
        #app-bottom-nav .caption { font-size: 10px; }
    </style>
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
                                    <?php echo $order->title; ?>
                                </h3>
                                <p class="uk-text-meta uk-margin-remove-top">
                                    <?php echo HTMLHelper::date($order->created, Text::_('DATE_FORMAT_LC4')); ?>
                                </p>
                            </div>
                            <div class="uk-width-auto">
                                <?php if ($order->status):
                                    $statusClass = $order->status->params->get('class_site', 'uk-label-warning');
                                    // Convert Bootstrap classes to UIkit
                                    $statusClass = str_replace(['bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-secondary', 'bg-primary'],
                                                               ['uk-label-success', 'uk-label-danger', 'uk-label-warning', 'uk-label-warning', '', ''], $statusClass);
                                ?>
                                <span class="uk-label <?php echo $statusClass; ?>">
                                    <?php echo $order->status->title; ?>
                                </span>
                                <?php else: ?>
                                <span class="uk-label"><?php echo Text::_('COM_RADICALMART_TELEGRAM_STATUS_UNKNOWN'); ?></span>
                                <?php endif; ?>
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
                                <dd class="uk-width-1-2 uk-text-right"><?php echo $order->shipping->get('title'); ?></dd>
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
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>">
                        <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script>
        // Initialize Telegram WebApp
        document.addEventListener('DOMContentLoaded', function() {
            try { document.body.classList.remove('contentpane'); } catch(e){}

            // Set WebApp cookie
            try { document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax'; } catch(e) {}

            // Initialize Telegram
            try {
                if (window.Telegram && window.Telegram.WebApp) {
                    Telegram.WebApp.ready();
                    Telegram.WebApp.expand();

                    const chatId = Telegram.WebApp.initDataUnsafe?.user?.id;
                    window.TG_CHAT_ID = chatId || 0;

                    // Auto-reload with chat parameter if missing
                    if (chatId) {
                        const url = new URL(window.location.href);
                        if (!url.searchParams.has('chat')) {
                            url.searchParams.set('chat', chatId);
                            window.location.replace(url.toString());
                            return;
                        }
                        // Update all links
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

            // Force UIkit icons (with delay to ensure icons script is loaded)
            try {
                if (window.UIkit) {
                    UIkit.update();
                    setTimeout(function() { UIkit.update(); }, 100);
                }
            } catch(e) {}
        });

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
</body>
</html>
