<?php
/**
 * @package     PlgRadicalMart_PaymentTelegramstars
 * Telegram Stars payment page layout
 *
 * After checkout, this layout:
 * - In WebApp: redirects to order page with auto-refresh for status updates
 * - In browser: shows link to bot
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

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

$root = rtrim(Uri::root(), '/');

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

// Order page URL for redirect
$orderId = isset($order) && !empty($order->id) ? (int) $order->id : 0;
$orderNumber = isset($order) && !empty($order->number) ? (string) $order->number : '';
$orderPageUrl = $root . '/index.php?option=com_radicalmart_telegram&view=order&id=' . $orderId;
?>
<style>
    .stars-payment-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 20px;
        text-align: center;
    }
    .stars-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
    .stars-message {
        font-size: 18px;
        margin-bottom: 20px;
        color: #333;
    }
    .stars-hint {
        font-size: 14px;
        color: #666;
        margin-bottom: 20px;
    }
    .stars-bot-link {
        display: inline-block;
        padding: 12px 24px;
        background: #0088cc;
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
    }
    .stars-bot-link:hover {
        background: #006699;
        color: #fff;
    }
    .stars-closing {
        margin-top: 20px;
        font-size: 14px;
        color: #888;
    }
    @media (prefers-color-scheme: dark) {
        .stars-message { color: #eee; }
        .stars-hint { color: #aaa; }
        .stars-closing { color: #888; }
    }
</style>

<div class="stars-payment-container" id="stars-payment">
    <div class="stars-icon">⭐</div>
    <div class="stars-message"><?php echo htmlspecialchars($page_message ?: 'Счёт на оплату отправлен в Telegram'); ?></div>

    <div id="stars-webapp-notice" style="display: none;">
        <div class="stars-hint">Переходим к заказу...</div>
        <div class="stars-closing">
            <span uk-spinner="ratio: 0.8"></span>
        </div>
    </div>

    <div id="stars-browser-notice">
        <div class="stars-hint">Откройте бота в Telegram для оплаты</div>
        <?php if ($botLink): ?>
            <a href="<?php echo $botLink; ?>" class="stars-bot-link" target="_blank">
                📱 Открыть бота
            </a>
        <?php endif; ?>
    </div>
</div>

<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
(function() {
    const tg = window.Telegram?.WebApp;
    const webappNotice = document.getElementById('stars-webapp-notice');
    const browserNotice = document.getElementById('stars-browser-notice');
    const orderId = <?php echo $orderId; ?>;
    const orderPageUrl = '<?php echo $orderPageUrl; ?>';

    if (tg && tg.initData) {
        // We're inside Telegram WebApp
        webappNotice.style.display = 'block';
        browserNotice.style.display = 'none';

        tg.ready();

        // Get chat_id from initData and add to URL
        const chatId = tg.initDataUnsafe?.user?.id || 0;
        let redirectUrl = orderPageUrl;
        if (chatId > 0) {
            redirectUrl += '&chat=' + chatId;
        }
        // Mark as awaiting payment for auto-refresh
        redirectUrl += '&awaiting_payment=1';

        // Redirect to order page after brief delay
        setTimeout(function() {
            window.location.href = redirectUrl;
        }, 1000);
    } else {
        // Regular browser - show link to bot
        webappNotice.style.display = 'none';
        browserNotice.style.display = 'block';
    }
})();
</script>
