<?php
/**
 * @package     PlgRadicalMart_PaymentTelegramcards
 * Telegram Cards payment page layout
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
        <div class="cards-hint">Ожидаем оплату...</div>
        <div class="cards-closing">
            <span uk-spinner="ratio: 0.8"></span>
            <div id="poll-status" style="margin-top: 10px; font-size: 12px; color: #999;"></div>
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

<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
(function() {
    const tg = window.Telegram?.WebApp;
    const webappNotice = document.getElementById('cards-webapp-notice');
    const browserNotice = document.getElementById('cards-browser-notice');
    const pollStatus = document.getElementById('poll-status');
    const orderId = <?php echo $orderId; ?>;
    const orderPageUrl = '<?php echo $orderPageUrl; ?>';
    const initialStatusId = <?php echo isset($order) && !empty($order->status) ? (int) $order->status : 0; ?>;

    if (tg && tg.initData) {
        // We're inside Telegram WebApp
        webappNotice.style.display = 'block';
        browserNotice.style.display = 'none';

        tg.ready();

        // Get chat_id from initData
        const chatId = tg.initDataUnsafe?.user?.id || 0;

        // Build order page URL with chat
        let redirectUrl = orderPageUrl;
        if (chatId > 0) {
            redirectUrl += '&chat=' + chatId;
        }

        // Poll order status every 3 seconds
        // If status changes (payment completed), redirect to order page
        let pollCount = 0;
        const maxPolls = 120; // 6 minutes max
        const pollInterval = 3000; // 3 seconds

        function checkStatus() {
            pollCount++;
            if (pollCount > maxPolls) {
                if (pollStatus) pollStatus.textContent = 'Время ожидания истекло';
                return;
            }

            const statusUrl = '<?php echo $root; ?>/index.php?option=com_radicalmart_telegram&task=api.orderStatus&format=json&id=' + orderId + '&chat=' + chatId;

            fetch(statusUrl)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const currentStatusId = parseInt(data.data.status_id) || 0;

                        // If status changed from initial - payment completed!
                        if (currentStatusId > 0 && currentStatusId !== initialStatusId) {
                            if (pollStatus) pollStatus.textContent = 'Оплата получена! Переходим...';
                            setTimeout(() => {
                                window.location.href = redirectUrl;
                            }, 500);
                            return;
                        }
                    }
                    // Continue polling
                    setTimeout(checkStatus, pollInterval);
                })
                .catch(err => {
                    console.log('Poll error:', err);
                    // Continue polling even on error
                    setTimeout(checkStatus, pollInterval);
                });
        }

        // Start polling after small delay (let invoice be sent first)
        setTimeout(checkStatus, 2000);

    } else {
        // Regular browser - show link to bot
        webappNotice.style.display = 'none';
        browserNotice.style.display = 'block';
    }
})();
</script>
