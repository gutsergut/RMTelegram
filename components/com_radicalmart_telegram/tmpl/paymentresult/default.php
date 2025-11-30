<?php
/**
 * Payment Result View - shown after returning from external payment
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

$root = rtrim(Uri::root(), '/');
$app = Factory::getApplication();
$chat = $app->input->getInt('chat', 0);
$chatParam = $chat ? '&chat=' . $chat : '';

$result = $this->result ?? 'return';
$orderNumber = $this->orderNumber ?? '';

$isSuccess = ($result === 'success');
$isError = ($result === 'error');

// Determine title and message based on result
if ($isSuccess) {
    $title = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_SUCCESS_TITLE');
    $message = $orderNumber
        ? Text::sprintf('COM_RADICALMART_TELEGRAM_PAYMENT_SUCCESS_MESSAGE', htmlspecialchars($orderNumber))
        : Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_SUCCESS_TITLE');
    $iconClass = 'check';
    $iconColor = '#32d296';
} elseif ($isError) {
    $title = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_ERROR_TITLE');
    $message = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_ERROR_MESSAGE');
    $iconClass = 'close';
    $iconColor = '#f0506e';
} else {
    $title = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_RETURN_TITLE');
    $message = '';
    $iconClass = 'info';
    $iconColor = '#1e87f0';
}

$appUrl = $root . '/index.php?option=com_radicalmart_telegram&view=app' . $chatParam;
$ordersUrl = $root . '/index.php?option=com_radicalmart_telegram&view=orders' . $chatParam;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $root; ?>/templates/yootheme/css/theme.css">
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js"></script>
    <script src="<?php echo $root; ?>/templates/yootheme/vendor/assets/uikit/dist/js/uikit-icons.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        html, body {
            background: #ffffff !important;
            color: #222 !important;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .payment-result-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            text-align: center;
        }
        .payment-result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .payment-result-icon svg {
            width: 40px;
            height: 40px;
        }
        .payment-result-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .payment-result-message {
            font-size: 16px;
            color: #999;
            margin-bottom: 30px;
        }
        .payment-result-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            max-width: 300px;
        }
        .payment-result-buttons .uk-button {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="payment-result-container">
        <!-- Icon -->
        <div class="payment-result-icon" style="background: <?php echo $iconColor; ?>20;">
            <span uk-icon="icon: <?php echo $iconClass; ?>; ratio: 2" style="color: <?php echo $iconColor; ?>;"></span>
        </div>

        <!-- Title -->
        <div class="payment-result-title">
            <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="payment-result-message">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="payment-result-buttons">
            <a href="<?php echo $ordersUrl; ?>" class="uk-button uk-button-primary">
                <span uk-icon="icon: file-text"></span>
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_GO_ORDERS'); ?>
            </a>

            <a href="<?php echo $appUrl; ?>" class="uk-button uk-button-default">
                <span uk-icon="icon: home"></span>
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_GO_CATALOG'); ?>
            </a>

            <?php if ($isError): ?>
            <button onclick="window.history.back();" class="uk-button uk-button-danger uk-button-small">
                <span uk-icon="icon: refresh"></span>
                <?php echo Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_TRY_AGAIN'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize Telegram WebApp
        document.addEventListener('DOMContentLoaded', function() {
            // Force light theme
            document.documentElement.style.setProperty('--tg-theme-bg-color', '#ffffff');
            document.documentElement.style.setProperty('--tg-theme-text-color', '#222222');
            document.body.style.backgroundColor = '#ffffff';
            document.body.style.color = '#222222';

            try {
                if (window.Telegram && Telegram.WebApp) {
                    Telegram.WebApp.ready();
                    Telegram.WebApp.expand();

                    // BackButton - navigate to orders
                    try {
                        Telegram.WebApp.BackButton.show();
                        Telegram.WebApp.BackButton.onClick(function() {
                            window.location.href = '<?php echo $ordersUrl; ?>';
                        });
                    } catch(e) { console.log('BackButton error:', e); }
                }
            } catch(e) {
                console.log('Telegram WebApp init error:', e);
            }

            try {
                document.cookie = 'tg_webapp=1; path=/; max-age=7200; SameSite=Lax';
            } catch(e) {}
        });
    </script>

    <script>
    // --- UIkit Icons Fix ---
    function RMT_EXTRACT_ICON_NAME(attr){
        if (!attr) return '';
        let s = String(attr);
        const m = s.match(/icon\s*:\s*([^;]+)/);
        if (m && m[1]) return m[1].trim();
        return s.trim();
    }
    function RMT_FORCE_UKIT_ICONS(){
        try{
            if (!window.UIkit || !UIkit.icon) return false;
            const nodes = document.querySelectorAll('[uk-icon]');
            let forced = 0;
            nodes.forEach(el => {
                if (el.querySelector('svg')) return;
                const name = RMT_EXTRACT_ICON_NAME(el.getAttribute('uk-icon'));
                try { UIkit.icon(el, { icon: name }); forced++; } catch(_){}
            });
            if (forced>0) console.log('[RMT][icons] UIkit.icon forced for', forced, 'elements');
            try { UIkit.update(); } catch(_){}
            return forced>0;
        }catch(e){ return false; }
    }

    let RMT_ICON_OBSERVER = null;
    function RMT_OBSERVE_ICONS(){
        try{
            if (RMT_ICON_OBSERVER) return;
            RMT_ICON_OBSERVER = new MutationObserver((mutations) => {
                let needsCheck = false;
                for (const m of mutations){
                    if (m.type === 'childList'){
                        if (m.target && (m.target.hasAttribute?.('uk-icon') || m.target.querySelector?.('[uk-icon]'))) {
                            needsCheck = true; break;
                        }
                        for (const n of m.addedNodes){
                            if (n.nodeType === 1 && ((n.hasAttribute && n.hasAttribute('uk-icon')) || n.querySelector?.('[uk-icon]'))) { needsCheck = true; break; }
                        }
                    }
                    if (needsCheck) break;
                }
                if (needsCheck) { try { RMT_FORCE_UKIT_ICONS(); } catch(e){} }
            });
            RMT_ICON_OBSERVER.observe(document.documentElement || document.body, { childList: true, subtree: true });
        }catch(e){}
    }

    document.addEventListener('DOMContentLoaded', function(){
        RMT_FORCE_UKIT_ICONS();
        RMT_OBSERVE_ICONS();
    });
    </script>

</body>
</html>
