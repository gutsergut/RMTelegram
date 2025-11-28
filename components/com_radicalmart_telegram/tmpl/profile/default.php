<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  profile
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\Component\RadicalMartTelegram\Site\View\Profile\HtmlView $this */

$root = Uri::root();
$app = \Joomla\CMS\Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatId = $this->tgUser['chat_id'] ?? $chat;
$baseQuery = $chatId > 0 ? '&chat=' . $chatId : '';

// Данные пользователя
$userName = $this->tgUser['name'] ?? 'Пользователь';
$userPhone = $this->tgUser['phone'] ?? '';
$userId = $this->tgUser['user_id'] ?? 0;

// Add styles for bottom nav
$doc = \Joomla\CMS\Factory::getDocument();
$doc->addStyleDeclaration('
    #app-bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-top: 1px solid #e5e5e5; padding-bottom: env(safe-area-inset-bottom); }
    #app-bottom-nav .uk-navbar-nav > li > a { min-height: 60px; padding: 0 10px; font-size: 10px; line-height: 1.2; text-transform: none; color: #999; }
    #app-bottom-nav .uk-navbar-nav > li.uk-active > a { color: #1e87f0; }
    #app-bottom-nav .bottom-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
    #app-bottom-nav .uk-icon { margin-bottom: 4px; }
    body { padding-bottom: 80px; }
');

?>
<div id="profile-app" class="uk-container uk-container-small uk-padding-small">
    <h1 class="uk-h3 uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROFILE'); ?></h1>

    <!-- Карточка пользователя -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <div class="uk-flex uk-flex-middle">
            <div class="uk-width-auto uk-margin-right">
                <span uk-icon="icon: user; ratio: 2"></span>
            </div>
            <div class="uk-width-expand">
                <h3 class="uk-card-title uk-margin-remove-bottom"><?php echo htmlspecialchars($userName); ?></h3>
                <?php if ($userPhone): ?>
                <p class="uk-text-meta uk-margin-remove-top"><?php echo htmlspecialchars($userPhone); ?></p>
                <?php elseif ($userId > 0): ?>
                <p class="uk-text-meta uk-margin-remove-top">ID: <?php echo $userId; ?></p>
                <?php else: ?>
                <p class="uk-text-meta uk-margin-remove-top"><?php echo Text::_('COM_RADICALMART_TELEGRAM_GUEST'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Блок баллов -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3 class="uk-card-title uk-margin-small-bottom"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS'); ?></h3>
        <?php if ($this->points > 0): ?>
        <div class="uk-text-lead uk-text-primary">
            <?php echo number_format($this->points, 0, ',', ' '); ?> <?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_UNIT'); ?>
            <span class="uk-text-muted uk-text-small">(= <?php echo $this->pointsEquivalent; ?>)</span>
        </div>
        <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=points<?php echo $baseQuery; ?>" class="uk-link-muted uk-text-small">
            <?php echo Text::_('COM_RADICALMART_TELEGRAM_VIEW_HISTORY'); ?> →
        </a>
        <?php else: ?>
        <div class="uk-text-muted">
            0 <?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_UNIT'); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Меню профиля -->
    <div class="uk-card uk-card-default uk-margin-bottom">
        <ul class="uk-list uk-list-divider uk-margin-remove">
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=orders<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: list" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_ORDERS'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <?php if ($this->points > 0): ?>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=points<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: star" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_POINTS_HISTORY'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=codes<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: tag" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_PROMO_CODES'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
            <li>
                <a href="<?php echo $root; ?>index.php?option=com_radicalmart_telegram&view=referrals<?php echo $baseQuery; ?>" class="uk-display-block uk-padding-small uk-link-reset">
                    <div class="uk-flex uk-flex-middle">
                        <span uk-icon="icon: users" class="uk-margin-small-right"></span>
                        <span class="uk-width-expand"><?php echo Text::_('COM_RADICALMART_TELEGRAM_REFERRALS'); ?></span>
                        <span uk-icon="icon: chevron-right"></span>
                    </div>
                </a>
            </li>
        </ul>
    </div>
</div>

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

<!-- Load UIkit icons AFTER YOOtheme's UIkit is loaded -->
<script src="<?php echo $root; ?>templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
<script>
    if (typeof UIkit !== 'undefined') {
        UIkit.update(document.body);
    }
</script>
