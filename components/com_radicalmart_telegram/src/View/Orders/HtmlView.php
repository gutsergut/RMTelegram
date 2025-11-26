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

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart', JPATH_SITE);

        $app = Factory::getApplication();
        $this->params = $app->getParams('com_radicalmart_telegram');
        $this->currentStatus = $app->input->getInt('status', 0);

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

    protected function loadOrders(): void
    {
        $user = Factory::getUser();
        if ($user->guest) {
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('o.*')
                ->from($db->quoteName('#__radicalmart_orders', 'o'))
                ->where($db->quoteName('o.user_id') . ' = ' . (int) $user->id)
                ->order($db->quoteName('o.created') . ' DESC');

            if ($this->currentStatus > 0) {
                $query->where($db->quoteName('o.status_id') . ' = ' . (int) $this->currentStatus);
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
                $order->status = $this->getStatus((int) $order->status_id);

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

