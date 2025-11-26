<?php
/*
 * @package     plg_system_radicalmart_telegram
 */

namespace Joomla\Plugin\System\Radicalmart_telegram\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\AdministratorMenuItem;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;

class RadicalMartTelegram extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected bool $removeAdministratorMenu = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterRoute' => 'onAfterRoute',
            'onRadicalMartAfterChangeOrderStatus' => 'onAfterChangeOrderStatus',
            'onRadicalMartPreprocessSubmenu' => 'onRadicalMartPreprocessSubmenu',
            'onPreprocessMenuItems' => 'onPreprocessMenuItems',
            'onAfterRender' => 'onAfterRender',
        ];
    }

    /**
     * Redirect WebApp users to our component if they land on other pages
     * This handles payment success/error pages and any other accidental navigation
     */
    public function onAfterRoute(Event $event): void
    {
        $app = Factory::getApplication();

        // Only process site frontend
        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->input;
        $option = $input->get('option', '');

        // Already on our component - no redirect needed
        if ($option === 'com_radicalmart_telegram') {
            return;
        }

        // Check if user came from WebApp (has tg_webapp cookie or tg_init parameter)
        $isWebApp = false;
        $orderNumber = null;
        $paymentResult = null;

        // Check cookie (set when user enters WebApp)
        if ($input->cookie->get('tg_webapp', '') === '1') {
            $isWebApp = true;
        }

        // Check tg_init parameter (Telegram WebApp init data)
        if ($input->get('tg_init', '')) {
            $isWebApp = true;
        }

        // Check referer for Telegram WebApp
        $referer = $input->server->get('HTTP_REFERER', '');
        if (strpos($referer, 'tgWebAppData') !== false || strpos($referer, 'com_radicalmart_telegram') !== false) {
            $isWebApp = true;
        }

        if (!$isWebApp) {
            return;
        }

        // User is from WebApp - determine where to redirect
        $redirectUrl = 'index.php?option=com_radicalmart_telegram&view=app';
        $message = null;
        $messageType = 'info';

        // Special handling for RadicalMart payment pages
        if ($option === 'com_radicalmart') {
            $task = $input->get('task', '');
            $orderNumber = $input->get('order_number', '');

            if (strpos($task, 'payment.') === 0) {
                $paymentTask = str_replace('payment.', '', $task);

                switch ($paymentTask) {
                    case 'success':
                        $paymentResult = 'success';
                        $message = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_SUCCESS');
                        $messageType = 'success';
                        break;
                    case 'error':
                        $paymentResult = 'error';
                        $message = Text::_('COM_RADICALMART_TELEGRAM_PAYMENT_ERROR');
                        $messageType = 'danger';
                        break;
                    case 'return':
                        $paymentResult = 'return';
                        break;
                }

                // Redirect to our payment result view
                $redirectUrl = 'index.php?option=com_radicalmart_telegram&view=paymentresult';
                if ($orderNumber) {
                    $redirectUrl .= '&order_number=' . urlencode($orderNumber);
                }
                if ($paymentResult) {
                    $redirectUrl .= '&result=' . $paymentResult;
                }
            }
        }

        // Log the redirect
        Log::add(
            sprintf('[WebApp Redirect] From %s to %s (option=%s, task=%s)',
                Uri::current(), $redirectUrl, $option, $input->get('task', '')),
            Log::INFO,
            'com_radicalmart.telegram'
        );

        // Set message if any
        if ($message) {
            $app->enqueueMessage($message, $messageType);
        }

        // Redirect to our component
        $app->redirect(Route::_($redirectUrl, false));
    }

    public function onAfterInitialise(Event $event): void
    {
        // Auto-fix plugin ordering to ensure we run AFTER EngageBox (rstbox/engagebox)
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'element', 'ordering']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' IN (' . $db->quote('radicalmart_telegram') . ', ' . $db->quote('rstbox') . ', ' . $db->quote('engagebox') . ')');

            $db->setQuery($query);
            $plugins = $db->loadObjectList('element');

            $targetOrder = 0;
            if (isset($plugins['rstbox'])) {
                $targetOrder = max($targetOrder, (int)$plugins['rstbox']->ordering);
            }
            if (isset($plugins['engagebox'])) {
                $targetOrder = max($targetOrder, (int)$plugins['engagebox']->ordering);
            }

            if (isset($plugins['radicalmart_telegram'])) {
                $myOrder = (int)$plugins['radicalmart_telegram']->ordering;
                if ($myOrder <= $targetOrder) {
                    $newOrder = $targetOrder + 1;
                    $update = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('ordering') . ' = ' . (int)$newOrder)
                        ->where($db->quoteName('extension_id') . ' = ' . (int)$plugins['radicalmart_telegram']->extension_id);
                    $db->setQuery($update)->execute();
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB errors
        }
    }

    public function onRadicalMartPreprocessSubmenu(array &$results, AdministratorMenuItem $parent, Registry $params): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator'))
        {
            return;
        }

        $this->addTelegramSubMenu($results);
    }

    protected function addTelegramSubMenu(array &$results): void
    {
        foreach ($results as $item)
        {
            if ($item instanceof AdministratorMenuItem && $item->link === 'index.php?option=com_radicalmart_telegram')
            {
                return;
            }
        }

        $language = Factory::getApplication()->getLanguage();
        $language->load('com_radicalmart_telegram.sys', JPATH_ADMINISTRATOR);

        $root = new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM',
            'type'      => 'container',
            'link'      => 'index.php?option=com_radicalmart_telegram',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => '',
            'dashboard' => '',
            'scope'     => 'default',
        ]);

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_SETTINGS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=settings',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_STATUS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=status',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_LINKS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=links',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_PAYMENTS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=payments',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_CONFIGURATION',
            'type'      => 'component',
            'link'      => 'index.php?option=com_config&view=component&component=com_radicalmart_telegram',
            'element'   => 'com_config',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $results[] = $root;
    }

    public function onPreprocessMenuItems(Event $event): void
    {
        $context  = $event->getArgument(0);
        $children = $event->getArgument(1);

        $this->removeTelegramAdministratorComponentsMenuItem($context, $children);

        $event->setArgument(1, $children);
    }

    protected function removeTelegramAdministratorComponentsMenuItem(?string $context = null, array $children = []): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator') || $context !== 'com_menus.administrator.module' || $this->removeAdministratorMenu)
        {
            return;
        }

        $component = ComponentHelper::getComponent('com_radicalmart_telegram');
        if (!$component || empty($component->id))
        {
            return;
        }

        foreach ($children as $child)
        {
            if ($child instanceof AdministratorMenuItem
                && $child->type === 'component'
                && (int) $child->component_id === (int) $component->id)
            {
                $parent = $child->getParent();
                if ($parent)
                {
                    $parent->removeChild($child);
                }

                $this->removeAdministratorMenu = true;
            }
        }
    }

    public function onAfterRender(): void
    {
        $app = Factory::getApplication();

        // Only process site frontend
        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->input;
        $option = $input->get('option', '');

        // Process ALL views of com_radicalmart_telegram component
        if ($option !== 'com_radicalmart_telegram') {
            return;
        }

        // Get component params
        $params = ComponentHelper::getParams('com_radicalmart_telegram');

        // Check if filtering is enabled
        if (!(int) $params->get('filter_scripts_enabled', 1)) {
            return;
        }

        // Get current body
        $body = $app->getBody();
        if (empty($body)) {
            return;
        }

        // Fallback: Inject CSS and JS to hide/remove EngageBox if regex fails or ordering is wrong
        // This works even if our plugin runs BEFORE EngageBox
        $css = '<style>.eb-inst, .eb-overlay, .eb-dialog, .eb-container { display: none !important; }</style>';
        $js = '<script>document.addEventListener("DOMContentLoaded", function() { document.querySelectorAll(".eb-inst, .eb-overlay, .eb-dialog").forEach(e => e.remove()); });</script>';
        $body = str_replace('</head>', $css . $js . '</head>', $body);

        // FIRST: Aggressive RstBox/EngageBox cleanup using regex
        // Simplified approach: just remove the opening tag and let browser handle broken HTML

        $removedCount = 0;
        $debugInfo = [];

        // Strategy 1: Remove EngageBox blocks - handle multiline attributes
        // Key: use \s to match whitespace including newlines, not [^>]
        $pattern1 = '/<div(?:\s+[^>]*?)?\s+class="[^"]*eb-inst[^"]*"[^>]*>/is';
        $body = preg_replace_callback($pattern1, function($m) use (&$removedCount, &$debugInfo) {
            $removedCount++;
            $debugInfo[] = substr($m[0], 0, 80);
            return '<!-- EngageBox removed -->';
        }, $body);

        // Strategy 2: Remove by data-id first (more specific, handles attributes before class)
        $pattern1b = '/<div\s+data-id="\d+"(?:\s+[^>]*?)?\s+class="[^"]*eb-inst[^"]*"[^>]*>/is';
        $before = $body;
        $body = preg_replace_callback($pattern1b, function($m) use (&$removedCount, &$debugInfo) {
            $removedCount++;
            $debugInfo[] = "data-id: " . substr($m[0], 0, 60);
            return '<!-- EngageBox removed -->';
        }, $body);
        if ($before === $body) {
            // Fallback: try with any whitespace between div and data-id
            $pattern1c = '/<div[\s\r\n]+data-id="1"[\s\S]*?>/is';
            $body = preg_replace_callback($pattern1c, function($m) use (&$removedCount, &$debugInfo) {
                $removedCount++;
                $debugInfo[] = "fallback: " . substr($m[0], 0, 60);
                return '<!-- EngageBox removed -->';
            }, $body);
        }

        // Strategy 3: REMOVED (Too risky, caused page blanking)
        // $pattern_full = ...

        // Strategy 4: Remove eb-close buttons
        $pattern2 = '/<button(?:\s+[^>]*?)?\s+class="[^"]*eb-close[^"]*"[^>]*>.*?<\/button>/is';
        $body = preg_replace($pattern2, '', $body);

        // Strategy 5: Remove eb-dialog, eb-container, eb-content divs
        // Use non-greedy match and ensure we don't eat too much
        $pattern3 = '/<div(?:\s+[^>]*?)?\s+class="[^"]*eb-(?:dialog|container|content)[^"]*"[^>]*>.*?<\/div>/is';
        $body = preg_replace($pattern3, '', $body);

        // Strategy 6: Remove specific "Subscribe to Telegram" block content
        // Safer version: match only if it's a small block (limit chars) to avoid eating page
        // Matches <div class="uk-text-center">...Telegram...Подписаться...</div>
        $pattern_sub = '/<div[^>]*class="[^"]*uk-text-center[^"]*"[^>]*>(?:(?!<\/div>).){0,1000}href="https:\/\/t\.me\/cacaoland"(?:(?!<\/div>).){0,1000}<\/div>/is';
        $body = preg_replace_callback($pattern_sub, function($m) use (&$removedCount, &$debugInfo) {
            $removedCount++;
            $debugInfo[] = "Subscribe block: " . substr($m[0], 0, 50);
            return '<!-- Subscribe block removed -->';
        }, $body);

        // Strategy 7: Remove orphaned closing divs (up to 5) to clean up structure
        // Only remove if they are followed by our debug comment or body end
        $body = preg_replace('/(<\/div>\s*){1,5}(?=<!-- RadicalMart Telegram|<\/body>)/', '', $body);

        // Remove rstbox scripts and styles
        $body = preg_replace('/<script[\s\S]*?src="[^"]*\/com_rstbox\/[^"]*"[\s\S]*?>[\s\S]*?<\/script>/', '', $body);
        $body = preg_replace('/<link[\s\S]*?href="[^"]*\/com_rstbox\/[^"]*"[\s\S]*?>/', '', $body);

        // Add debug comment with details
        $debugMsg = "RadicalMart Telegram: Removed $removedCount EngageBox elements";
        if (!empty($debugInfo)) {
            $debugMsg .= " | " . implode(' | ', $debugInfo);
        }
        $body = str_replace('</body>', "<!-- $debugMsg --></body>", $body);

        // Get configuration
        $scriptPaths = $params->get('filter_scripts_paths', "/media/com_rstbox/\n/components/com_j_sms_registration/");
        $htmlSelectors = $params->get('filter_html_selectors', "div[class*=eb-init]\ndiv[class*=eb-dialog]\ndiv[class*=eb-inst]\ndiv#jsms_vk_shtorka");
        $inlinePatterns = $params->get('filter_inline_patterns', "var COM_J_SMS_REGISTRATION");

        // Process script/style paths
        if (!empty($scriptPaths)) {
            $paths = array_filter(array_map('trim', explode("\n", $scriptPaths)));
            foreach ($paths as $path) {
                $escapedPath = preg_quote($path, '/');
                // Remove <link> tags
                $body = preg_replace('/<link[^>]*href="[^"]*' . $escapedPath . '[^"]*"[^>]*>/i', '', $body);
                // Remove <script> tags
                $body = preg_replace('/<script[^>]*src="[^"]*' . $escapedPath . '[^"]*"[^>]*><\/script>/i', '', $body);
            }
        }

        // Process inline script patterns
        if (!empty($inlinePatterns)) {
            $patterns = array_filter(array_map('trim', explode("\n", $inlinePatterns)));
            foreach ($patterns as $pattern) {
                $escapedPattern = preg_quote($pattern, '/');
                // Match individual <script> blocks that contain the pattern
                // Use negative lookahead to avoid matching across multiple script tags
                $body = preg_replace_callback(
                    '/<script(?![^>]*\ssrc=)[^>]*>(.*?)<\/script>/is',
                    function($matches) use ($escapedPattern) {
                        // Check if this specific script block contains the pattern
                        if (preg_match('/' . $escapedPattern . '/i', $matches[1])) {
                            return ''; // Remove this script block
                        }
                        return $matches[0]; // Keep this script block
                    },
                    $body
                );
            }
        }

        // Additional cleanup: remove SMS registration inline scripts (use callback to avoid greedy matching)
        $body = preg_replace_callback(
            '/<script(?![^>]*\ssrc=)[^>]*>(.*?)<\/script>/is',
            function($matches) {
                // Remove only if contains sitogonmask or j_sms_registration
                if (preg_match('/jQuery\.sitogonmask|j_sms_registration/i', $matches[1])) {
                    return '';
                }
                return $matches[0];
            },
            $body
        );

        // Process HTML selectors
        if (!empty($htmlSelectors)) {
            $selectors = array_filter(array_map('trim', explode("\n", $htmlSelectors)));
            foreach ($selectors as $selector) {
                // Parse selector: tag[attr=value] or tag[attr*=value] or tag#id
                if (preg_match('/^(\w+)\[([^=\*]+)([\*]?)=([^\]]+)\]$/', $selector, $matches)) {
                    $tag = $matches[1];
                    $attr = $matches[2];
                    $isPartial = $matches[3] === '*';
                    $value = trim($matches[4]);

                    if ($isPartial) {
                        // Partial match: attr*=value
                        $escapedValue = preg_quote($value, '/');
                        $pattern = '/<' . $tag . '[^>]*\s' . $attr . '="[^"]*\b' . $escapedValue . '\b[^"]*"[^>]*>.*?<\/' . $tag . '>/is';
                    } else {
                        // Exact match: attr=value
                        $escapedValue = preg_quote($value, '/');
                        $pattern = '/<' . $tag . '[^>]*\s' . $attr . '="' . $escapedValue . '"[^>]*>.*?<\/' . $tag . '>/is';
                    }
                    $body = preg_replace($pattern, '', $body);
                } elseif (preg_match('/^(\w+)#(\w+)$/', $selector, $matches)) {
                    // ID selector: tag#id
                    $tag = $matches[1];
                    $id = $matches[2];
                    $pattern = '/<' . $tag . '[^>]*\sid="' . $id . '"[^>]*>.*?<\/' . $tag . '>/is';
                    $body = preg_replace($pattern, '', $body);
                }
            }
        }

        // Additional cleanup: remove all remaining EngageBox/SMS elements by patterns
        // Remove any <div> or <button> with class containing 'eb-' (EngageBox elements and close buttons)
        $body = preg_replace('/<(div|button|a)[^>]*class="[^"]*\beb-[^"]*"[^>]*>.*?<\/\1>/is', '', $body);

        // Remove standalone EngageBox close buttons (they can be outside the box div)
        $body = preg_replace('/<[^>]*class="[^"]*eb-close[^"]*"[^>]*>/i', '', $body);

        // Remove any element with data-attributes from EngageBox
        $body = preg_replace('/<[^>]*data-ebox[^>]*>.*?<\/[^>]+>/is', '', $body);

        // Remove EngageBox overlay/backdrop with rstbox class (non-greedy, single line)
        $body = preg_replace('/<div[^>]*class="[^"]*rstbox[^"]*"[^>]*>.*?<\/div>/s', '', $body);

        // Remove SMS registration elements
        $body = preg_replace('/<div[^>]*id="jsms_[^"]*"[^>]*>.*?<\/div>/is', '', $body);        // Remove RadicalMicro blocks if enabled
        if ((int) $params->get('filter_radicalmicro', 1)) {
            // Remove RadicalMicro comment blocks
            $body = preg_replace('/<!-- RadicalMicro: start -->.*?<!-- RadicalMicro: end -->/is', '', $body);

            // Remove any remaining OpenGraph meta tags
            $body = preg_replace('/<meta[^>]*property="og:[^"]*"[^>]*>/i', '', $body);

            // Remove any remaining Schema.org JSON-LD scripts
            $body = preg_replace('/<script[^>]*type="application\/ld\+json"[^>]*>.*?<\/script>/is', '', $body);
        }

        // Set cleaned body back
        $app->setBody($body);
    }    public function onAfterChangeOrderStatus(?string $context = null, ?object $order = null,
                                             int $oldStatus = 0, int $newStatus = 0, bool $isNew = false)
    {
        try {
            if (!$order || empty($order->id) || empty($order->created_by)) {
                return;
            }

            // Get chat mapping
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('chat_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', (int) $order->created_by);
            $chatId = (int) $db->setQuery($query, 0, 1)->loadResult();
            if ($chatId <= 0) {
                return; // user not linked to bot
            }

            // Get token from component params
            $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
            $token  = (string) $params->get('bot_token', '');
            if ($token === '') {
                return;
            }

            // Compose message
            $statusText = '';
            if (!empty($order->status) && !empty($order->status->title)) {
                $statusText = (string) $order->status->title;
            }
            $number = (string) ($order->number ?? ('#' . (int) $order->id));
            $lines = [];
            $lines[] = Text::sprintf('PLG_SYSTEM_RADICALMART_TELEGRAM_ORDER_STATUS_CHANGED', $number);
            if ($statusText !== '') {
                $lines[] = Text::sprintf('PLG_SYSTEM_RADICALMART_TELEGRAM_NEW_STATUS', $statusText);
            }
            $text = implode("\n", $lines);

            // Send via Telegram API
            $http = new Http();
            $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
            $url = 'https://api.telegram.org/bot' . $token . '/sendMessage?' . http_build_query([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ]);
            $response = $http->get($url);
            // Optional: parse $response->body if needed
        } catch (\Throwable $e) {
            // swallow for now; add logging later
        }
    }
}
