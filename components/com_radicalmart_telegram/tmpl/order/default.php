<?php
/**
 * Order Detail View - Telegram WebApp
 * @package com_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Order\HtmlView $this */

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);

$tgUser = $this->tgUser;
$chatId = $tgUser['chat_id'] ?? $chat;
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';
$order = $this->order;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $order ? $order->title : Text::_('COM_RADICALMART_TELEGRAM_ORDER'); ?></title>
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
        .product-img { width: 60px; height: 60px; object-fit: contain; background: #f5f5f5; border-radius: 4px; }
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
        <!-- Back link -->
        <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="uk-link-muted uk-margin-small-bottom uk-display-block">
            <span uk-icon="arrow-left"></span> <?php echo Text::_('COM_RADICALMART_TELEGRAM_BACK_TO_ORDERS'); ?>
        </a>

        <?php if (!$order): ?>
        <div class="uk-card uk-card-default uk-card-body uk-text-center">
            <span uk-icon="icon: warning; ratio: 3" class="uk-text-warning"></span>
            <p class="uk-text-lead uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDER_NOT_FOUND'); ?></p>
        </div>
        <?php else: ?>

        <!-- Order Header -->
        <div class="uk-card uk-card-default uk-card-small uk-margin-bottom">
            <div class="uk-card-header">
                <div class="uk-grid-small uk-flex-middle" uk-grid>
                    <div class="uk-width-expand">
                        <h1 class="uk-card-title uk-margin-remove-bottom"><?php echo $order->title; ?></h1>
                        <p class="uk-text-meta uk-margin-remove-top">
                            <?php echo HTMLHelper::date($order->created, Text::_('DATE_FORMAT_LC4')); ?>
                        </p>
                    </div>
                    <div class="uk-width-auto">
                        <?php if ($order->status): 
                            $statusClass = $order->status->params->get('class_site', 'uk-label-warning');
                            $statusClass = str_replace(['bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-secondary', 'bg-primary'], 
                                                       ['uk-label-success', 'uk-label-danger', 'uk-label-warning', 'uk-label-warning', '', ''], $statusClass);
                        ?>
                        <span class="uk-label <?php echo $statusClass; ?>"><?php echo $order->status->title; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products -->
        <div class="uk-card uk-card-default uk-card-small uk-margin-bottom">
            <div class="uk-card-header">
                <h3 class="uk-card-title uk-margin-remove"><?php echo Text::_('COM_RADICALMART_PRODUCTS'); ?></h3>
            </div>
            <div class="uk-card-body uk-padding-remove">
                <ul class="uk-list uk-list-divider uk-margin-remove">
                    <?php foreach ($order->products as $product): ?>
                    <li class="uk-padding-small">
                        <div class="uk-grid-small uk-flex-middle" uk-grid>
                            <div class="uk-width-auto">
                                <?php if (!empty($product['image'])): ?>
                                <img src="<?php echo $root . '/' . ltrim($product['image'], '/'); ?>" alt="" class="product-img">
                                <?php else: ?>
                                <div class="product-img uk-flex uk-flex-center uk-flex-middle">
                                    <span uk-icon="icon: image; ratio: 1.5" class="uk-text-muted"></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="uk-width-expand">
                                <div class="uk-text-bold"><?php echo $product['title'] ?? ''; ?></div>
                                <?php if (!empty($product['extra_display'])): ?>
                                <div class="uk-text-meta uk-text-small">
                                    <?php foreach ($product['extra_display'] as $extra): ?>
                                        <?php if (!empty($extra['html'])): ?>
                                            <span><?php echo strip_tags($extra['html']); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="uk-text-small uk-text-muted">
                                    <?php echo ($product['quantity'] ?? 1); ?> × <?php echo $product['final_string'] ?? ''; ?>
                                </div>
                            </div>
                            <div class="uk-width-auto uk-text-bold">
                                <?php echo $product['sum_final_string'] ?? ''; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Shipping -->
        <?php if ($order->shipping && $order->shipping->get('title')): ?>
        <div class="uk-card uk-card-default uk-card-small uk-margin-bottom">
            <div class="uk-card-header">
                <h3 class="uk-card-title uk-margin-remove"><?php echo Text::_('COM_RADICALMART_SHIPPING'); ?></h3>
            </div>
            <div class="uk-card-body">
                <div class="uk-text-bold"><?php echo $order->shipping->get('title'); ?></div>
                <?php if ($address = $order->shipping->get('address')): ?>
                <div class="uk-text-muted uk-margin-small-top"><?php echo is_array($address) ? implode(', ', array_filter($address)) : $address; ?></div>
                <?php endif; ?>
                <?php if ($pvz = $order->shipping->get('pvz_name')): ?>
                <div class="uk-text-muted"><?php echo $pvz; ?></div>
                <?php endif; ?>
                <?php if ($cost = $order->shipping->get('final_string')): ?>
                <div class="uk-margin-small-top"><?php echo Text::_('COM_RADICALMART_SHIPPING_COST'); ?>: <strong><?php echo $cost; ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment -->
        <?php if ($order->payment && $order->payment->get('title')): ?>
        <div class="uk-card uk-card-default uk-card-small uk-margin-bottom">
            <div class="uk-card-header">
                <h3 class="uk-card-title uk-margin-remove"><?php echo Text::_('COM_RADICALMART_PAYMENT'); ?></h3>
            </div>
            <div class="uk-card-body">
                <div class="uk-text-bold"><?php echo $order->payment->get('title'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contacts -->
        <?php if ($order->contacts): ?>
        <div class="uk-card uk-card-default uk-card-small uk-margin-bottom">
            <div class="uk-card-header">
                <h3 class="uk-card-title uk-margin-remove"><?php echo Text::_('COM_RADICALMART_CONTACTS'); ?></h3>
            </div>
            <div class="uk-card-body">
                <?php if ($name = $order->contacts->get('name')): ?>
                <div><?php echo $name; ?></div>
                <?php endif; ?>
                <?php if ($phone = $order->contacts->get('phone')): ?>
                <div><a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a></div>
                <?php endif; ?>
                <?php if ($email = $order->contacts->get('email')): ?>
                <div><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Total -->
        <div class="uk-card uk-card-primary uk-card-small">
            <div class="uk-card-body">
                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-expand uk-text-large"><?php echo Text::_('COM_RADICALMART_TOTAL'); ?></div>
                    <div class="uk-width-auto uk-text-large uk-text-bold"><?php echo $order->total['final_string'] ?? ''; ?></div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Bottom fixed nav -->
    <div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
        <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
            <ul class="uk-navbar-nav">
                <li><a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $baseQuery; ?>"><span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CATALOG'); ?></span></span></a></li>
                <li><a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart<?php echo $baseQuery; ?>"><span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_CART'); ?></span></span></a></li>
                <li class="uk-active"><a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>"><span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span></span></a></li>
                <li><a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $baseQuery; ?>"><span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></span></span></a></li>
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try { document.body.classList.remove('contentpane'); } catch(e){}
            try { document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax'; } catch(e) {}
            try {
                if (window.Telegram && window.Telegram.WebApp) {
                    Telegram.WebApp.ready();
                    Telegram.WebApp.expand();
                    const chatId = Telegram.WebApp.initDataUnsafe?.user?.id;
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
            } catch(e) {}
            try { if (window.UIkit) UIkit.update(); } catch(e) {}
        });
    </script>
</body>
</html>
