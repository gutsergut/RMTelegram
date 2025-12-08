<?php
/**
 * @package     PlgRadicalMart_PaymentTelegramcards
 * Telegram Cards payment page layout
 *
 * This layout closes the Telegram WebApp to show the payment invoice,
 * or provides a link to the bot for non-WebApp context.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;

extract($displayData);

/**
 * Layout variables
 * -----------------
 *
 * @var  string $page_title   The page title.
 * @var  string $page_message The page message.
 * @var  object $order        The order data.
 * @var  array  $payment      The payment plugin data.
 */

// Get bot username for link
$params = ComponentHelper::getParams('com_radicalmart_telegram');
$botToken = (string) $params->get('bot_token', '');
$botUsername = '';

// Extract username from bot info (cached or fetch)
if (!empty($botToken)) {
    try {
        $cacheKey = 'rmt_bot_username';
        $cached = Factory::getApplication()->getSession()->get($cacheKey, '');
        if (!empty($cached)) {
            $botUsername = $cached;
        } else {
            $http = new \Joomla\CMS\Http\Http();
            $response = $http->get('https://api.telegram.org/bot' . $botToken . '/getMe');
            $data = json_decode($response->body, true);
            if (!empty($data['ok']) && !empty($data['result']['username'])) {
                $botUsername = $data['result']['username'];
                Factory::getApplication()->getSession()->set($cacheKey, $botUsername);
            }
        }
    } catch (\Throwable $e) {
        // Ignore
    }
}

$botLink = $botUsername ? 'https://t.me/' . $botUsername : '';
?>
<style>
    .cards-payment-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 20px;
        text-align: center;
    }
    .cards-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
    .cards-message {
        font-size: 18px;
        margin-bottom: 20px;
        color: #333;
    }
    .cards-hint {
        font-size: 14px;
        color: #666;
        margin-bottom: 20px;
    }
    .cards-bot-link {
        display: inline-block;
        padding: 12px 24px;
        background: #0088cc;
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
    }
    .cards-bot-link:hover {
        background: #006699;
        color: #fff;
    }
    .cards-closing {
        margin-top: 20px;
        font-size: 14px;
        color: #888;
    }
    @media (prefers-color-scheme: dark) {
        .cards-message { color: #eee; }
        .cards-hint { color: #aaa; }
        .cards-closing { color: #888; }
    }
</style>

<div class="cards-payment-container" id="cards-payment">
    <div class="cards-icon">💳</div>
    <div class="cards-message"><?php echo htmlspecialchars($page_message ?: 'Счёт на оплату отправлен в Telegram'); ?></div>

    <div id="cards-webapp-notice" style="display: none;">
        <div class="cards-hint">Закрываем приложение для оплаты...</div>
        <div class="cards-closing">
            <span uk-spinner="ratio: 0.8"></span>
        </div>
    </div>

    <div id="cards-browser-notice">
        <div class="cards-hint">Откройте бота в Telegram для оплаты</div>
        <?php if ($botLink): ?>
            <a href="<?php echo $botLink; ?>" class="cards-bot-link" target="_blank">
                📱 Открыть бота
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const tg = window.Telegram?.WebApp;
    const webappNotice = document.getElementById('cards-webapp-notice');
    const browserNotice = document.getElementById('cards-browser-notice');

    if (tg && tg.initData) {
        // We're inside Telegram WebApp - close it to show invoice
        webappNotice.style.display = 'block';
        browserNotice.style.display = 'none';

        // Close WebApp after a brief delay to show message
        setTimeout(function() {
            tg.close();
        }, 1500);
    } else {
        // Regular browser - show link to bot
        webappNotice.style.display = 'none';
        browserNotice.style.display = 'block';
    }
})();
</script>
