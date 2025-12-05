<?php
/*
 * @package     com_radicalmart_telegram (site)
 * Order Detail View
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $order = null;
    public $tgUser = null;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart', JPATH_SITE);

        $app = Factory::getApplication();
        $this->params = $app->getParams('com_radicalmart_telegram');

        // Get user
        $this->tgUser = TelegramUserHelper::getCurrentUser();

        // Load order
        $orderId = $app->input->getInt('id', 0);
        $this->loadOrder($orderId);

        if ($app->getTemplate() !== 'yootheme') {
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

    protected function loadOrder(int $orderId): void
    {
        $userId = $this->tgUser['user_id'] ?? 0;
        if ($orderId <= 0 || $userId <= 0) {
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('o.*')
                ->from($db->quoteName('#__radicalmart_orders', 'o'))
                ->where($db->quoteName('o.id') . ' = ' . (int) $orderId)
                ->where($db->quoteName('o.created_by') . ' = ' . (int) $userId)
                ->where($db->quoteName('o.state') . ' = 1');

            $db->setQuery($query);
            $order = $db->loadObject();

            if ($order) {
                $order->products = json_decode($order->products ?? '[]', true) ?: [];
                $order->shipping = new Registry($order->shipping ?? '{}');
                $order->payment = new Registry($order->payment ?? '{}');
                $order->total = json_decode($order->total ?? '{}', true) ?: [];
                $order->contacts = new Registry($order->contacts ?? '{}');

                // Format price strings like RadicalMart does
                $currency = $order->currency ?? 'RUB';
                if (isset($order->total['final'])) {
                    $order->total['final_string'] = PriceHelper::toString($order->total['final'], $currency);
                }
                if (isset($order->total['base'])) {
                    $order->total['base_string'] = PriceHelper::toString($order->total['base'], $currency);
                }

                $order->status = $this->getStatus((int) ($order->status ?? 0));
                $order->title = Text::sprintf('COM_RADICALMART_TELEGRAM_ORDER_NUMBER', $order->number ?: $order->id);
                $this->order = $order;
            }
        } catch (\Throwable $e) {
            // Log error
        }
    }

    protected function getStatus(int $id): ?object
    {
        if ($id <= 0) return null;

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'params'])
                ->from($db->quoteName('#__radicalmart_statuses'))
                ->where($db->quoteName('id') . ' = ' . (int) $id);

            $db->setQuery($query);
            $row = $db->loadObject();

            if ($row) {
                $row->rawtitle = $row->title;
                $row->title = Text::_($row->title);
                $row->params = new Registry($row->params ?? '{}');
                return $row;
            }
        } catch (\Throwable $e) {}

        return null;
    }
}
