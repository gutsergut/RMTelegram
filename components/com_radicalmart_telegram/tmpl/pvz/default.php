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
        html, body { background-color: var(--tg-theme-bg-color, #ffffff); color: var(--tg-theme-text-color, #222); }
        body.contentpane { padding: 0 !important; margin: 0 !important; }
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
        body { padding-bottom: 60px; }
        #pvz-map { width: 100%; height: 400px; }
    </style>
</head>
<body class="contentpane">

<div class="uk-container uk-container-small uk-padding-small">
























</html></body></nav>    </div>        </ul>            <li><a href="index.php?option=com_radicalmart_telegram&view=profile"><span uk-icon="icon: user; ratio: 0.9"></span><span class="caption">Профиль</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=orders"><span uk-icon="icon: list; ratio: 0.9"></span><span class="caption">Заказы</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=cart"><span uk-icon="icon: cart; ratio: 0.9"></span><span class="caption">Корзина</span></a></li>            <li><a href="index.php?option=com_radicalmart_telegram&view=app"><span uk-icon="icon: home; ratio: 0.9"></span><span class="caption">Каталог</span></a></li>        <ul class="uk-navbar-nav">    <div class="uk-navbar uk-flex uk-flex-center"><nav id="app-bottom-nav" class="uk-navbar-container" style="background: var(--tg-theme-bg-color, #fff); border-top: 1px solid #ddd;"><!-- Bottom Navigation --></div>    </div>        <p>Выберите удобный пункт выдачи</p>    <div id="pvz-list" class="uk-margin-top">        <div id="pvz-map"></div>        <h2 class="uk-heading-small">Пункты выдачи</h2>
