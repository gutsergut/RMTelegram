<?php
/**
 * Checkout view template
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

$root = rtrim(Uri::root(), '/');














































</html></body></nav>    </div>        </ul>            <li><a href="index.php?option=com_radicalmart_telegram&view=profile"><span uk-icon="icon: user; ratio: 0.9"></span><span class="caption">Профиль</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=orders"><span uk-icon="icon: list; ratio: 0.9"></span><span class="caption">Заказы</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=cart"><span uk-icon="icon: cart; ratio: 0.9"></span><span class="caption">Корзина</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=app"><span uk-icon="icon: home; ratio: 0.9"></span><span class="caption">Каталог</span></a></li>        <ul class="uk-navbar-nav">    <div class="uk-navbar uk-flex uk-flex-center"><nav id="app-bottom-nav" class="uk-navbar-container" style="background: var(--tg-theme-bg-color, #fff); border-top: 1px solid #ddd;"><!-- Bottom Navigation --></div>    </div>        </div>            <p>Выберите способ доставки и оплаты</p>        <div class="uk-alert uk-alert-warning">    <div id="checkout-content">        <h2 class="uk-heading-small">Оформление заказа</h2><div class="uk-container uk-container-small uk-padding-small"><body class="contentpane"></head>    </style>        body { padding-bottom: 60px; }        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }        body.contentpane { padding: 0 !important; margin: 0 !important; }        html, body { background-color: var(--tg-theme-bg-color, #ffffff); color: var(--tg-theme-text-color, #222); }    <style>    <script src="https://telegram.org/js/telegram-web-app.js"></script>    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">    <title><?php echo htmlspecialchars($storeTitle . ' - Оформление заказа', ENT_QUOTES, 'UTF-8'); ?></title>    <meta name="viewport" content="width=device-width, initial-scale=1">    <meta charset="utf-8"><head><html lang="ru"><!DOCTYPE html>?>$storeTitle = isset($this->params) ? (string) $this->params->get('store_title', 'магазин Cacao.Land') : 'магазин Cacao.Land';
