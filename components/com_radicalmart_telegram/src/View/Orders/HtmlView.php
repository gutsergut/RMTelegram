<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Orders;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    protected $params;
    protected $items = [];
    protected $statuses = [];
    protected $pagination;
    protected $currentStatus;
    protected $userId = 0;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart', JPATH_SITE);

        $app = Factory::getApplication();
        $this->params = $app->getParams('com_radicalmart_telegram');
        $this->currentStatus = $app->input->getInt('status', 0);

        // Get user_id from Telegram chat_id or Joomla session
        $this->userId = $this->resolveUserId();

        // Load orders for current user
        $this->loadOrders();
        $this->loadStatuses();

        if ($app->getTemplate() !== 'yootheme')
        {
            $app->setTemplate('yootheme');
        }

        HTMLHelper::_('jquery.framework');

        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();

        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css?1745431273');
        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css?4.5.9');

        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js?4.5.9', [], ['defer' => false]);
        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js?4.5.9', ['uikit.js'], ['defer' => false]);

        parent::display($tpl);
    }

    /**
     * Resolve user_id from Telegram initData or Joomla session
     */
    protected function resolveUserId(): int
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Try to get chat_id from tg_init parameter (Telegram WebApp)
        $tgInit = $input->get('tg_init', '', 'raw');
        if ($tgInit) {
            $chatId = $this->parseChatIdFromInit($tgInit);
            if ($chatId > 0) {
                $userId = $this->getUserIdByChatId($chatId);
                if ($userId > 0) {
                    return $userId;
                }
            }
        }

        // Try to get from chat parameter
        $chat = $input->getInt('chat', 0);
        if ($chat > 0) {
            $userId = $this->getUserIdByChatId($chat);
            if ($userId > 0) {
                return $userId;
            }
        }

        // Fallback to Joomla user
        $user = Factory::getUser();
        if (!$user->guest) {
            return (int) $user->id;
        }

        return 0;
    }

    /**
     * Parse chat_id from Telegram initData string
     */
    protected function parseChatIdFromInit(string $initData): int
    {
        try {
            parse_str($initData, $parsed);
            if (!empty($parsed['user'])) {
                $userData = json_decode($parsed['user'], true);
                if (!empty($userData['id'])) {
                    return (int) $userData['id'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore parse errors
        }
        return 0;
    }

    /**
     * Get Joomla user_id by Telegram chat_id
     */
    protected function getUserIdByChatId(int $chatId): int
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . (int) $chatId);
            $db->setQuery($query, 0, 1);
            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function loadOrders(): void
    {
        if ($this->userId <= 0) {
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Use created_by field (same as API controller)
            $query = $db->getQuery(true)
                ->select('o.*')
                ->from($db->quoteName('#__radicalmart_orders', 'o'))
                ->where($db->quoteName('o.created_by') . ' = ' . (int) $this->userId)
                ->where($db->quoteName('o.state') . ' = 1')
                ->order($db->quoteName('o.created') . ' DESC');

            if ($this->currentStatus > 0) {
                $query->where($db->quoteName('o.status') . ' = ' . (int) $this->currentStatus);
            }

            $db->setQuery($query, 0, 20);
            $orders = $db->loadObjectList();

            foreach ($orders as $order) {
                // Parse JSON fields
                $order->products = json_decode($order->products ?? '[]', true) ?: [];
                $order->shipping = new Registry($order->shipping ?? '{}');
                $order->payment = new Registry($order->payment ?? '{}');
                $order->total = json_decode($order->total ?? '{}', true) ?: [];

                // Load status
                $order->status = $this->getStatus((int) ($order->status ?? 0));

                // Build link
                $order->link = Uri::root() . 'index.php?option=com_radicalmart&view=order&id=' . (int) $order->id;

                // Build title
                $order->title = Text::sprintf('COM_RADICALMART_ORDER_NUMBER', $order->number ?: $order->id);

                $this->items[] = $order;
            }
        } catch (\Throwable $e) {
            // Log error
        }
    }

    protected function loadStatuses(): void
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'params'])
                ->from($db->quoteName('#__radicalmart_statuses'))
                ->where($db->quoteName('state') . ' = 1')
                ->order('ordering ASC');

            $db->setQuery($query);
            $rows = $db->loadObjectList();

            foreach ($rows as $row) {
                $row->params = new Registry($row->params ?? '{}');
                $this->statuses[$row->id] = $row;
            }
        } catch (\Throwable $e) {
            // Log error
        }
    }

    protected function getStatus(int $id): ?object
    {
        if (empty($this->statuses)) {
            $this->loadStatuses();
        }
        return $this->statuses[$id] ?? null;
    }
}

