<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Site
 * Полноценная страница товара для Telegram WebApp с UIkit, графиками и вариативностью
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\Component\RadicalMart\Site\Helper\MediaHelper;

$app = Factory::getApplication();
$root = rtrim(Uri::root(), '/');
$chat = $app->input->getInt('chat', 0);
$chatParam = $chat ? '&chat=' . $chat : '';

$product = $this->product;

if (empty($product) || empty($product->id)) {
    echo '<div style="padding:20px;text-align:center;">Товар не найден</div>';
    return;
}

// Get gallery, variability from view
$gallery = $this->gallery ?? [];
$variability = $this->variability ?? null;
$variabilityForm = $this->variabilityForm ?? null;
$hasVariants = ($variability && $variabilityForm);

// Main image fallback
$mainImage = '';
if (!empty($product->image)) {
    $mainImage = $product->image;
} elseif (!empty($product->media)) {
    $media = is_object($product->media) ? $product->media : (is_string($product->media) ? json_decode($product->media) : $product->media);
    $mainImage = is_object($media) ? ($media->image ?? '') : ($media['image'] ?? '');
}
if ($mainImage && strpos($mainImage, 'http') !== 0) {
    $mainImage = $root . '/' . ltrim($mainImage, '/');
}

// Вкусовые характеристики
$tasteProfile = [];
$hasVkus = !empty($product->fieldsets['vkusovye-kharakteristiki']->fields);
if ($hasVkus) {
    $fields = $product->fieldsets['vkusovye-kharakteristiki']->fields;
    $tasteProfile = [
        'gorech' => (float)($fields['gorech']->value ?? 0),
        'gorech2' => (float)($fields['gorech-2']->value ?? 0),
        'kislotnost' => (float)($fields['kislotnost']->value ?? 0),
        'pryanost' => (float)($fields['pryanost']->value ?? 0),
        'shokoladnost' => (float)($fields['shokoladnost']->value ?? 0),
        'obemnost' => (float)($fields['obemnost']->value ?? 0),
        'ottenki' => (string)($fields['ottenki-vkusa']->value ?? ''),
        'ottenki_title' => (string)($fields['ottenki-vkusa']->title ?? 'Оттенки вкуса'),
    ];
}

// Cacao Scores
$cacaoScores = 0;
$cacaoScoresDesc = '';
$hasCacaoScores = !empty($product->fieldsets['kakao-kharakteristiki']->fields['sila-sostoyanij']->value);
if ($hasCacaoScores) {
    $cacaoScores = (int)$product->fieldsets['kakao-kharakteristiki']->fields['sila-sostoyanij']->value;
    if ($cacaoScores < 180) {
        $cacaoScoresDesc = 'Ощутимое. Рекомендуется новичкам и тем, кто знакомится с правильным какао для ежедневного употребления.';
    } elseif ($cacaoScores < 200) {
        $cacaoScoresDesc = 'Сильное. Рекомендуется тем, кто уже пробовал какао и опытным любителям.';
    } else {
        $cacaoScoresDesc = 'Очень сильное. Рекомендуется опытным любителям правильного какао. Лучший вариант для какао-церемоний и глубоких практик.';
    }
}

// Классификация качества
$qualityTypes = [
    'selected' => ['title' => 'Отборный', 'text' => 'Специально отобранные какао-бобы поступают от нескольких фермеров. Технология ферментации и сушки подобрана для максимальных вкусовых и ароматических показателей.'],
    'original' => ['title' => 'Оригинальный', 'text' => 'Бобы высокого качества, культивируемые в других странах. Своими качествами, вкусовыми особенностями или терруаром они привлекают интерес и любопытство.'],
    'microlot' => ['title' => 'Микролот', 'text' => 'Какао уникального качества, выпущенное ограниченным тиражом, собранное в дикой природе на плантациях, затерянных среди джунглей Латинской Америки.'],
    'premium' => ['title' => 'Премиальное', 'text' => 'Какао премиального качества с особым терруаром. Особое внимание уделяется степени зрелости плодов какао и соблюдению протокола ферментации.'],
    'specialty' => ['title' => 'Высочайшее', 'text' => 'Какао высочайшего качества с отдельных участков специально отобранных плантаций. Уникальные характеристики определяются редчайшими генетическими видами.'],
];
$qualityRaw = $product->fieldsets['kakao-kharakteristiki']->fields['klassifikatsiya-kachestva']->rawvalue ?? '';
$qualityValue = $product->fieldsets['kakao-kharakteristiki']->fields['klassifikatsiya-kachestva']->value ?? '';
$quality = $qualityTypes[$qualityRaw] ?? null;

// Цена
$price = $product->price ?? [];
$priceString = $price['final_string'] ?? '';
$priceBase = $price['base_string'] ?? '';
$hasDiscount = !empty($price['discount_enable']);
$inStock = !empty($product->in_stock);

// API URL
$apiBase = $root . '/index.php?option=com_radicalmart_telegram&task=api';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($product->title ?? 'Товар'); ?></title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme_cacao/css/apexcharts.css">
    <style>
        :root {
            --tg-theme-bg-color: #ffffff;
            --tg-theme-text-color: #000000;
            --tg-theme-hint-color: #999999;
            --tg-theme-link-color: #2678b6;
            --tg-theme-button-color: #3390ec;
            --tg-theme-button-text-color: #ffffff;
            --tg-theme-secondary-bg-color: #f5f5f5;
        }
        html, body {
            background: var(--tg-theme-bg-color) !important;
            color: var(--tg-theme-text-color) !important;
            margin: 0;
            padding: 0;
        }
        body { padding-bottom: 80px; }

        /* Gallery Slideshow */
        .product-gallery { margin-bottom: 16px; }
        .product-gallery .uk-slideshow-items { min-height: 280px; background: #f8f8f8; border-radius: 12px; }
        .product-gallery .uk-slideshow-items img { object-fit: contain !important; }
        .product-gallery .uk-thumbnav { margin-top: 10px; }
        .product-gallery .uk-thumbnav > li > a { width: 50px; height: 50px; }
        .product-gallery .uk-thumbnav > li > a img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }

        /* Product Info */
        .product-content { padding: 0 16px; }
        .product-title { font-size: 22px; font-weight: 600; margin-bottom: 8px; line-height: 1.3; }
        .product-categories { margin-bottom: 12px; }
        .product-categories a { color: var(--tg-theme-link-color); font-size: 13px; margin-right: 8px; text-decoration: none; }

        /* Price Block */
        .price-block { background: var(--tg-theme-secondary-bg-color); border-radius: 12px; padding: 16px; margin: 16px 0; }
        .price-current { font-size: 28px; font-weight: 700; color: #4CAF50; }
        .price-old { font-size: 16px; color: var(--tg-theme-hint-color); text-decoration: line-through; margin-left: 8px; }
        .stock-status { font-size: 13px; margin-top: 8px; }
        .stock-status.in-stock { color: #4CAF50; }
        .stock-status.out-of-stock { color: #f44336; }

        /* Variants - UIkit button style */
        .variants-block { margin: 16px 0 !important; }
        #variants-container { display: flex !important; flex-wrap: wrap !important; gap: 8px !important; }

        /* Add to Cart button states - keep UIkit primary base style */
        #add-to-cart.adding { background-color: #999 !important; border-color: #999 !important; }
        #add-to-cart.added { background-color: #32d296 !important; border-color: #32d296 !important; }
        #add-to-cart:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Section titles */
        .section-title { font-size: 18px; font-weight: 600; text-align: center; margin: 24px 0 16px 0; }

        /* Charts */
        .chart-container { margin: 16px 0; }
        #taste-chart, #cacao-scores-chart { max-width: 100%; }

        /* Quality block */
        .quality-block { background: linear-gradient(135deg, #6D4C41 0%, #4E342E 100%); color: #fff; border-radius: 12px; padding: 16px; margin: 16px 0; }
        .quality-title { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .quality-text { font-size: 13px; line-height: 1.5; opacity: 0.9; }

        /* Description tabs */
        .tabs-container { margin: 16px 0; }
        .uk-subnav-pill > * > a { border-radius: 20px; font-size: 13px; }

        /* Introtext */
        .introtext { font-size: 14px; line-height: 1.6; margin: 16px 0; }

        /* Fields */
        .field-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 14px; }
        .field-label { color: var(--tg-theme-hint-color); }
        .field-value { font-weight: 500; text-align: right; max-width: 60%; }

        /* Warning */
        .warning-block { background: #FFF3E0; border-left: 4px solid #FF9800; padding: 12px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; font-size: 13px; }

        /* Cart badge */
        #cart-badge { position: absolute; top: 2px; right: 6px; background: #f0506e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; }

        /* Bottom Navigation - same as app page */
        #app-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10005; }
        #app-bottom-nav .uk-navbar-nav > li > a { padding: 4px 8px; line-height: 1.05; min-height: 50px; position: relative; }
        #app-bottom-nav .tg-safe-text { display: inline-flex; align-items: center; }
        #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 10px; }
        #app-bottom-nav .bottom-tab .caption { display: block; margin-top: 1px; font-size: 10px; }
        #app-bottom-nav .uk-icon > svg { width: 18px; height: 18px; }
    </style>
    <script>
        // Force light theme immediately before any Telegram scripts run
        (function() {
            var root = document.documentElement;
            root.style.setProperty('--tg-theme-bg-color', '#ffffff');
            root.style.setProperty('--tg-theme-text-color', '#000000');
            root.style.setProperty('--tg-theme-hint-color', '#999999');
            root.style.setProperty('--tg-theme-link-color', '#2678b6');
            root.style.setProperty('--tg-theme-button-color', '#3390ec');
            root.style.setProperty('--tg-theme-button-text-color', '#ffffff');
            root.style.setProperty('--tg-theme-secondary-bg-color', '#f5f5f5');
        })();
    </script>
</head>
<body style="background-color: #ffffff !important; color: #000000 !important;">
<div class="uk-container uk-container-small uk-padding-small">
    <!-- Gallery -->
    <div class="product-gallery" uk-slideshow="animation: fade; ratio: 4:3">
        <div class="uk-position-relative">
            <ul class="uk-slideshow-items">
                <?php if (!empty($gallery)): ?>
                    <?php foreach ($gallery as $i => $media): ?>
                        <li>
                            <?php
                            $imgSrc = '';
                            if (!empty($media['item']->src)) {
                                $imgSrc = $media['item']->src;
                            } elseif (!empty($media['item']->image)) {
                                $imgSrc = $media['item']->image;
                            }
                            if ($imgSrc && strpos($imgSrc, 'http') !== 0) {
                                $imgSrc = $root . '/' . ltrim($imgSrc, '/');
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($product->title); ?>" uk-cover>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product->title); ?>" uk-cover>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if (count($gallery) > 1): ?>
                <a href="#" class="uk-position-center-left uk-position-small uk-icon-button" uk-icon="icon: chevron-left" uk-slideshow-item="previous"></a>
                <a href="#" class="uk-position-center-right uk-position-small uk-icon-button" uk-icon="icon: chevron-right" uk-slideshow-item="next"></a>
            <?php endif; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
            <ul class="uk-thumbnav uk-flex-center uk-margin-small-top">
                <?php foreach ($gallery as $i => $media): ?>
                    <?php
                    $thumbSrc = '';
                    if (!empty($media['item']->src)) {
                        $thumbSrc = $media['item']->src;
                    } elseif (!empty($media['item']->image)) {
                        $thumbSrc = $media['item']->image;
                    }
                    if ($thumbSrc && strpos($thumbSrc, 'http') !== 0) {
                        $thumbSrc = $root . '/' . ltrim($thumbSrc, '/');
                    }
                    ?>
                    <li uk-slideshow-item="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'uk-active' : ''; ?>">
                        <a href="#"><img src="<?php echo htmlspecialchars($thumbSrc); ?>" alt="" width="50" height="50"></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="product-content">
        <!-- Categories -->
        <?php if (!empty($product->categories)): ?>
            <div class="product-categories">
                <?php foreach ($product->categories as $category): ?>
                    <a href="#">#<?php echo htmlspecialchars($category->title); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Title -->
        <h1 class="product-title"><?php echo htmlspecialchars($product->title); ?></h1>

        <!-- Introtext -->
        <?php if (!empty($product->introtext)): ?>
            <div class="introtext"><?php echo $product->introtext; ?></div>
        <?php endif; ?>

        <!-- Price Block -->
        <div class="price-block">
            <div>
                <?php if ($hasDiscount && $priceBase): ?>
                    <span class="price-old"><?php echo $priceBase; ?></span>
                <?php endif; ?>
                <span class="price-current"><?php echo $priceString ?: 'Цена по запросу'; ?></span>
            </div>
            <div class="stock-status <?php echo $inStock ? 'in-stock' : 'out-of-stock'; ?>">
                <?php echo $inStock ? '✓ В наличии' : '✕ Нет в наличии'; ?>
            </div>
        </div>

        <!-- Variants (Weight) -->
        <?php if ($hasVariants): ?>
            <div class="variants-block">
                <?php
                // Получаем заголовок поля вариативности (например "Вес")
                $varFieldTitle = 'Выберите вариант';
                if (!empty($variability->fields)) {
                    $firstField = reset($variability->fields);
                    if (!empty($firstField->title)) {
                        $varFieldTitle = 'Выберите ' . mb_strtolower($firstField->title) . ':';
                    }
                }
                ?>
                <div class="uk-text-small uk-text-muted uk-margin-small-bottom"><?php echo htmlspecialchars($varFieldTitle); ?></div>
                <div id="variants-container">
                    <?php foreach ($variability->products as $p => $variant): ?>
                        <button type="button"
                                class="uk-button <?php echo $variant->id == $product->id ? 'uk-button-primary' : 'uk-button-default'; ?> uk-button-small"
                                data-id="<?php echo $variant->id; ?>"
                                data-link="<?php echo htmlspecialchars($root . '/index.php?option=com_radicalmart_telegram&view=product&id=' . $variant->id . $chatParam); ?>"
                                onclick="selectVariant(this)">
                            <?php
                            // Получаем читаемое значение из options поля
                            $displayLabel = $variant->title;
                            if (!empty($variant->fieldsVariability) && !empty($variability->fields)) {
                                foreach ($variant->fieldsVariability as $fieldAlias => $rawValue) {
                                    // Ищем поле в variability->fields
                                    if (isset($variability->fields[$fieldAlias]) && !empty($variability->fields[$fieldAlias]->options)) {
                                        $fieldOptions = $variability->fields[$fieldAlias]->options;
                                        foreach ($fieldOptions as $option) {
                                            if ($option['value'] == $rawValue) {
                                                $displayLabel = $option['text'];
                                                break 2; // выходим из обоих циклов
                                            }
                                        }
                                    }
                                }
                            }
                            echo htmlspecialchars($displayLabel);
                            ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add to Cart Button -->
        <button type="button" class="uk-button uk-button-primary uk-width-1-1 uk-margin-top" id="add-to-cart" <?php echo !$inStock ? 'disabled' : ''; ?>>
            <?php echo $inStock ? 'В корзину' : 'Нет в наличии'; ?>
        </button>

        <!-- Вкусовой профиль -->
        <?php if ($hasVkus && $tasteProfile['gorech'] > 0): ?>
            <div class="section-title">Вкусовой профиль</div>
            <div class="chart-container">
                <div id="taste-chart"></div>
            </div>
            <?php if (!empty($tasteProfile['ottenki'])): ?>
                <div class="uk-text-center uk-text-small">
                    <strong><?php echo htmlspecialchars($tasteProfile['ottenki_title']); ?>:</strong>
                    <?php echo htmlspecialchars($tasteProfile['ottenki']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Cacao Scores -->
        <?php if ($hasCacaoScores && $cacaoScores > 0): ?>
            <div class="section-title">Сила состояний</div>
            <div class="uk-grid uk-child-width-1-2@s" uk-grid>
                <div>
                    <div id="cacao-scores-chart"></div>
                </div>
                <div class="uk-flex uk-flex-middle">
                    <div class="uk-text-small"><strong><?php echo $cacaoScoresDesc; ?></strong></div>
                </div>
            </div>
            <div class="uk-text-small uk-text-muted uk-margin-top">
                CS — Cacao Scores — условный показатель, совокупная оценка какао-тестеров сообщества Cacao-Lovers и Сacao.Land.
            </div>
        <?php endif; ?>

        <!-- Quality -->
        <?php if ($quality): ?>
            <div class="quality-block">
                <div class="quality-title"><?php echo htmlspecialchars($qualityValue); ?> (<?php echo $quality['title']; ?>)</div>
                <div class="quality-text"><?php echo htmlspecialchars($quality['text']); ?></div>
            </div>
        <?php endif; ?>

        <!-- Fieldsets (Tabs) -->
        <?php if (!empty($product->fieldsets)): ?>
            <?php
            $filteredFieldsets = [];
            foreach ($product->fieldsets as $alias => $fieldset) {
                if ($alias === 'root' || $alias === 'vkusovye-kharakteristiki') continue;
                $hasValues = false;
                if (!empty($fieldset->fields)) {
                    foreach ($fieldset->fields as $field) {
                        if (!empty($field->value)) {
                            $hasValues = true;
                            break;
                        }
                    }
                }
                if ($hasValues) {
                    $filteredFieldsets[$alias] = $fieldset;
                }
            }
            ?>
            <?php if (!empty($filteredFieldsets)): ?>
                <div class="tabs-container">
                    <ul class="uk-subnav uk-subnav-pill uk-flex-center" uk-switcher>
                        <?php foreach ($filteredFieldsets as $alias => $fieldset): ?>
                            <li><a href="#"><?php echo htmlspecialchars($fieldset->title); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <ul class="uk-switcher uk-margin">
                        <?php foreach ($filteredFieldsets as $alias => $fieldset): ?>
                            <li>
                                <?php foreach ($fieldset->fields as $field): ?>
                                    <?php if (empty($field->value)) continue; ?>
                                    <div class="field-row">
                                        <span class="field-label"><?php echo htmlspecialchars($field->title); ?></span>
                                        <span class="field-value"><?php echo $field->value; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Warning -->
        <div class="warning-block">
            <strong>Важно:</strong> Этот Церемониальный какао — уникальный продукт. Он перетирается без обжарки, с сохранением какаовеллы и зародыша. Содержит природные ингибиторы МАО. С осторожностью употреблять людям, принимающим антидепрессанты.
        </div>
    </div>
</div><!-- /.uk-container -->

    <!-- Bottom Navigation - same as app page -->
    <div id="app-bottom-nav" class="uk-navbar-container" uk-navbar>
        <div class="uk-navbar-center uk-width-1-1 uk-flex uk-flex-center">
            <ul class="uk-navbar-nav">
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $chatParam; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: thumbnails"></span><span class="caption tg-safe-text">Каталог</span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=cart<?php echo $chatParam; ?>" class="tg-safe-text" style="position:relative;">
                        <span class="bottom-tab"><span uk-icon="icon: cart"></span><span class="caption tg-safe-text">Корзина</span></span>
                        <span id="cart-badge" style="display:none;">0</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=orders<?php echo $chatParam; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: list"></span><span class="caption tg-safe-text">Заказы</span></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=profile<?php echo $chatParam; ?>" class="tg-safe-text">
                        <span class="bottom-tab"><span uk-icon="icon: user"></span><span class="caption tg-safe-text">Профиль</span></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Scripts (UIkit already loaded in head) -->
    <script src="<?php echo $root; ?>/templates/yootheme_cacao/js/apexcharts.js"></script>
    <script>
        const PRODUCT_ID = <?php echo (int)$product->id; ?>;
        const CHAT_PARAM = '<?php echo $chatParam; ?>';
        const API_BASE = '<?php echo $apiBase; ?>';

        // Telegram WebApp Init
        document.addEventListener('DOMContentLoaded', function() {
            // Force light theme - override Telegram's dark theme
            function forceLightTheme() {
                document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
                document.documentElement.style.setProperty('--tg-theme-text-color', '#000000');
                document.documentElement.style.setProperty('--tg-theme-hint-color', '#999999');
                document.documentElement.style.setProperty('--tg-theme-link-color', '#2678b6');
                document.documentElement.style.setProperty('--tg-theme-button-color', '#3390ec');
                document.documentElement.style.setProperty('--tg-theme-button-text-color', '#ffffff');
                document.documentElement.style.setProperty('--tg-theme-secondary-bg-color', '#f5f5f5');
                document.body.style.backgroundColor = '#ffffff';
                document.body.style.color = '#000000';
            }
            forceLightTheme();

            // Initialize UIkit icons
            if (typeof UIkit !== 'undefined') {
                UIkit.update(document.body);
            }

            // Telegram WebApp
            if (window.Telegram && Telegram.WebApp) {
                Telegram.WebApp.ready();
                Telegram.WebApp.expand();

                // Force light theme again after Telegram init
                forceLightTheme();

                // BackButton
                try {
                    Telegram.WebApp.BackButton.show();
                    Telegram.WebApp.BackButton.onClick(function() {
                        window.location.href = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&view=app<?php echo $chatParam; ?>';
                    });
                } catch(e) {}
            }

            // Initialize charts
            initCharts();

            // Load cart count
            refreshCart();
        });

        // Select variant
        function selectVariant(btn) {
            const link = btn.dataset.link;
            if (link) {
                window.location.href = link;
            }
        }

        // Nonce generator
        function makeNonce() {
            return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
        }

        // Add to cart
        document.getElementById('add-to-cart')?.addEventListener('click', function() {
            const btn = this;
            if (btn.disabled) return;

            btn.disabled = true;
            btn.classList.add('adding');
            btn.textContent = 'Добавляем...';

            // Build URL with params (same as main app)
            const url = new URL(location.origin + '/index.php');
            url.searchParams.set('option', 'com_radicalmart_telegram');
            url.searchParams.set('task', 'api.add');
            url.searchParams.set('id', PRODUCT_ID);
            url.searchParams.set('qty', 1);
            url.searchParams.set('nonce', makeNonce());
            <?php if ($chat): ?>
            url.searchParams.set('chat', '<?php echo $chat; ?>');
            <?php endif; ?>
            try {
                if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                    url.searchParams.set('tg_init', Telegram.WebApp.initData);
                }
            } catch(e) {}

            fetch(url.toString(), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                // API returns { success: bool, data: {...}, message: string }
                if (json.success !== false) {
                    btn.classList.remove('adding');
                    btn.classList.add('added');
                    btn.textContent = '✓ Добавлено в корзину';
                    if (window.Telegram?.WebApp?.HapticFeedback) {
                        Telegram.WebApp.HapticFeedback.notificationOccurred('success');
                    }
                    refreshCart();
                    setTimeout(() => {
                        btn.classList.remove('added');
                        btn.textContent = 'В корзину';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    btn.classList.remove('adding');
                    btn.textContent = json.message || 'Ошибка';
                    setTimeout(() => {
                        btn.textContent = 'В корзину';
                        btn.disabled = false;
                    }, 2000);
                }
            })
            .catch(err => {
                console.error('Cart error:', err);
                btn.classList.remove('adding');
                btn.textContent = 'Ошибка';
                setTimeout(() => {
                    btn.textContent = 'В корзину';
                    btn.disabled = false;
                }, 2000);
            });
        });

        // Refresh cart badge
        async function refreshCart() {
            try {
                const url = new URL(location.origin + '/index.php');
                url.searchParams.set('option', 'com_radicalmart_telegram');
                url.searchParams.set('task', 'api.cart');
                <?php if ($chat): ?>
                url.searchParams.set('chat', '<?php echo $chat; ?>');
                <?php endif; ?>
                try {
                    if (window.Telegram && Telegram.WebApp && Telegram.WebApp.initData) {
                        url.searchParams.set('tg_init', encodeURIComponent(Telegram.WebApp.initData));
                    }
                } catch(e) {}

                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                const json = await res.json();
                const cart = json.data?.cart;
                const badge = document.getElementById('cart-badge');

                if (!cart || !cart.products || Object.keys(cart.products).length === 0) {
                    if (badge) badge.style.display = 'none';
                    return;
                }

                const count = (cart.total && cart.total.quantity) ? parseInt(cart.total.quantity, 10) : Object.keys(cart.products).length;
                if (badge) {
                    if (count > 0) {
                        badge.style.display = 'inline-block';
                        badge.textContent = String(count);
                    } else {
                        badge.style.display = 'none';
                    }
                }
            } catch(e) { console.error('refreshCart error:', e); }
        }

        // Charts
        function initCharts() {
            <?php if ($hasVkus && $tasteProfile['gorech'] > 0): ?>
            // Taste Profile Chart
            if (document.getElementById('taste-chart') && typeof ApexCharts !== 'undefined') {
                var tasteOptions = {
                    series: [
                        <?php echo ($tasteProfile['gorech'] / 6 * 100); ?>,
                        <?php echo ($tasteProfile['gorech2'] / 6 * 100); ?>,
                        <?php echo ($tasteProfile['kislotnost'] / 6 * 100); ?>,
                        <?php echo ($tasteProfile['pryanost'] / 6 * 100); ?>,
                        <?php echo ($tasteProfile['shokoladnost'] / 6 * 100); ?>,
                        <?php echo ($tasteProfile['obemnost'] / 6 * 100); ?>
                    ],
                    chart: { height: 350, type: 'radialBar', background: 'transparent' },
                    plotOptions: {
                        radialBar: {
                            offsetY: 0, startAngle: 0, endAngle: 270,
                            hollow: { margin: 5, size: '10%', background: 'transparent' },
                            track: { background: 'transparent' },
                            dataLabels: { name: { show: false }, value: { show: false } },
                            barLabels: {
                                enabled: true, useSeriesColors: true, offsetX: -6, fontSize: '14px',
                                formatter: function(seriesName, opts) {
                                    return seriesName + ': ' + Math.round(opts.w.globals.series[opts.seriesIndex] / 100 * 6);
                                }
                            }
                        }
                    },
                    colors: ['#4E342E', '#F9A825', '#BF360C', '#6D4C41', '#A1887F', '#B3A89C'],
                    labels: ['Горечь', 'Кислотность', 'Пряность', 'Шоколадность', 'Объёмность', 'Мягкость'],
                    responsive: [{ breakpoint: 480, options: { legend: { show: false } } }]
                };
                new ApexCharts(document.getElementById('taste-chart'), tasteOptions).render();
            }
            <?php endif; ?>

            <?php if ($hasCacaoScores && $cacaoScores > 0): ?>
            // Cacao Scores Chart
            if (document.getElementById('cacao-scores-chart') && typeof ApexCharts !== 'undefined') {
                var scoresOptions = {
                    series: [<?php echo ($cacaoScores / 250 * 100); ?>],
                    chart: { height: 200, type: 'radialBar', toolbar: { show: false } },
                    plotOptions: {
                        radialBar: {
                            startAngle: -135, endAngle: 225,
                            hollow: {
                                margin: 0, size: '70%', background: '#fff',
                                dropShadow: { enabled: true, top: 3, left: 0, blur: 4, opacity: 0.5 }
                            },
                            track: {
                                background: '#fff', strokeWidth: '67%', margin: 0,
                                dropShadow: { enabled: true, top: -3, left: 0, blur: 4, opacity: 0.7 }
                            },
                            dataLabels: {
                                show: true,
                                name: { offsetY: -10, show: true, color: '#6D4C41', fontSize: '16px' },
                                value: {
                                    formatter: function(val) { return Math.round(parseInt(val) / 100 * 250); },
                                    color: '#6D4C41', fontWeight: 700, offsetY: 16, fontSize: '32px', show: true
                                }
                            }
                        }
                    },
                    colors: ['#ffa700'],
                    fill: {
                        type: 'gradient',
                        gradient: { type: 'horizontal', shade: 'dark', shadeIntensity: 0.4, gradientToColors: ['#6D4C41'], inverseColors: true, opacityFrom: 1, opacityTo: 1, stops: [0, 100] }
                    },
                    stroke: { lineCap: 'round' },
                    labels: ['Cacao Scores']
                };
                new ApexCharts(document.getElementById('cacao-scores-chart'), scoresOptions).render();
            }
            <?php endif; ?>
        }

        // UIkit Icons Fix - Force icons to render in Telegram WebApp
        function RMT_FORCE_UKIT_ICONS() {
            if (typeof UIkit === 'undefined') return;
            document.querySelectorAll('[uk-icon], [data-uk-icon]').forEach(function(el) {
                if (!el.querySelector('svg')) {
                    try {
                        UIkit.icon(el);
                    } catch (e) {}
                }
            });
        }
        function RMT_OBSERVE_ICONS() {
            if (typeof MutationObserver === 'undefined') return;
            new MutationObserver(function(mutations) {
                mutations.forEach(function(m) {
                    if (m.addedNodes.length) {
                        RMT_FORCE_UKIT_ICONS();
                    }
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
        document.addEventListener('DOMContentLoaded', function() {
            RMT_FORCE_UKIT_ICONS();
            RMT_OBSERVE_ICONS();
            setTimeout(RMT_FORCE_UKIT_ICONS, 100);
            setTimeout(RMT_FORCE_UKIT_ICONS, 500);
            setTimeout(RMT_FORCE_UKIT_ICONS, 1000);
        });
        if (document.readyState !== 'loading') {
            RMT_FORCE_UKIT_ICONS();
        }
    </script>
</body>
</html>
