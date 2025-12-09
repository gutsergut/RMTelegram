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
                // Decode order products (basic info with id, quantity, price)
                $orderProducts = json_decode($order->products ?? '[]', true) ?: [];

                // Load full product info from database
                $order->products = $this->loadProductsDetails($orderProducts, $order->currency ?? 'RUB');

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

                // Format shipping cost
                if ($order->shipping->get('final')) {
                    $order->shipping->set('final_string', PriceHelper::toString($order->shipping->get('final'), $currency));
                }

                $order->status = $this->getStatus((int) ($order->status ?? 0));
                // Build order title with order number - always use direct format to avoid translation issues
                $orderNumber = $order->number ?: $order->id;
                $order->title = 'Заказ №' . $orderNumber;

                $this->order = $order;
            }
        } catch (\Throwable $e) {
            // Log error
        }
    }

    /**
     * Load full product details from database
     *
     * @param array  $orderProducts  Products from order (with id, quantity, price info)
     * @param string $currency       Currency code
     * @return array Full products data with titles, images, etc.
     */
    protected function loadProductsDetails(array $orderProducts, string $currency = 'RUB'): array
    {
        if (empty($orderProducts)) {
            return [];
        }

        // Get product IDs
        $productIds = array_filter(array_map(function($p) {
            return (int) ($p['id'] ?? 0);
        }, $orderProducts));

        if (empty($productIds)) {
            return $orderProducts;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Load products from database
            $query = $db->getQuery(true)
                ->select(['p.id', 'p.title', 'p.media', 'p.prices'])
                ->from($db->quoteName('#__radicalmart_products', 'p'))
                ->whereIn($db->quoteName('p.id'), $productIds);

            $db->setQuery($query);
            $dbProducts = $db->loadObjectList('id');

            // Merge order product data with database product data
            $result = [];
            foreach ($orderProducts as $orderProduct) {
                $productId = (int) ($orderProduct['id'] ?? 0);
                $dbProduct = $dbProducts[$productId] ?? null;

                // Get product image
                $image = '';
                if ($dbProduct && !empty($dbProduct->media)) {
                    $media = json_decode($dbProduct->media, true);
                    $image = $media['image'] ?? ($media[0]['image'] ?? '');
                }

                // Build full product data
                $result[] = [
                    'id' => $productId,
                    'title' => $dbProduct->title ?? ($orderProduct['title'] ?? ''),
                    'image' => $image,
                    'quantity' => (int) ($orderProduct['quantity'] ?? 1),
                    'final_string' => $orderProduct['final_string'] ?? PriceHelper::toString($orderProduct['final'] ?? 0, $currency),
                    'sum_final_string' => $orderProduct['sum_final_string'] ?? PriceHelper::toString(
                        ($orderProduct['sum_final'] ?? (($orderProduct['final'] ?? 0) * ($orderProduct['quantity'] ?? 1))),
                        $currency
                    ),
                    'extra_display' => $orderProduct['extra_display'] ?? [],
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return $orderProducts;
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
