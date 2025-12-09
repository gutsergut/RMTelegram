<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  View
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Points;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null;
    public $customerId = 0;
    public $points = 0;
    public $pointsEquivalent = '';
    public $items = [];
    public $expiringPoints = [];
    public $hasMore = false;
    public $start = 0;
    public $limit = 10;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart_bonuses', JPATH_SITE);

        $app = Factory::getApplication();
        $this->params = $app->getParams('com_radicalmart_telegram');

        $this->start = $app->input->getInt('start', 0);
        $this->limit = 10;

        $this->tgUser = TelegramUserHelper::getCurrentUser();
        $this->loadPointsData();

        if ($app->getTemplate() !== 'yootheme') {
            $app->setTemplate('yootheme');
        }

        HTMLHelper::_('jquery.framework');

        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();
        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css');
        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css');
        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js', [], ['defer' => false]);
        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js', ['uikit.js'], ['defer' => false]);

        parent::display($tpl);
    }

    protected function loadPointsData(): void
    {
        $userId = $this->tgUser['user_id'] ?? 0;

        if ($userId <= 0) {
            return;
        }

        try {
            $this->customerId = $userId;

            if ($this->customerId <= 0) {
                return;
            }

            if (class_exists(PointsHelper::class)) {
                $this->points = (float) PointsHelper::getCustomerPoints($this->customerId);
                if ($this->points > 0) {
                    $currency = 'RUB';
                    $money = PointsHelper::convertToMoney($this->points, $currency);
                    $this->pointsEquivalent = number_format($money, 0, ',', ' ') . ' rub';
                }
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__radicalmart_bonuses_points'))
                ->where($db->quoteName('customer') . ' = ' . (int) $this->customerId)
                ->order($db->quoteName('created') . ' DESC')
                ->setLimit($this->limit + 1, $this->start);
            $db->setQuery($query);
            $items = $db->loadObjectList();

            if (count($items) > $this->limit) {
                $this->hasMore = true;
                array_pop($items);
            }
            $this->items = $items;

            $now = Factory::getDate()->toSql();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__radicalmart_bonuses_points'))
                ->where($db->quoteName('customer') . ' = ' . (int) $this->customerId)
                ->where($db->quoteName('end') . ' IS NOT NULL')
                ->where($db->quoteName('end') . ' > ' . $db->quote($now))
                ->where($db->quoteName('rest') . ' > 0')
                ->order($db->quoteName('end') . ' ASC');
            $db->setQuery($query);
            $this->expiringPoints = $db->loadObjectList();

        } catch (\Throwable $e) {
            $this->items = [];
            $this->expiringPoints = [];
        }
    }

    public function getContextLabel(object $item): string
    {
        $context = $item->context ?? '';
        $data = $item->data ? json_decode($item->data, true) : [];

        if (strpos($context, 'order') !== false) {
            $orderId = $data['order_id'] ?? '';
            if ($item->points > 0) {
                return Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_ORDER_CREDIT', $orderId);
            } else {
                return Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_ORDER_DEBIT', $orderId);
            }
        }

        if (strpos($context, 'referral') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_REFERRAL');
        }

        if (strpos($context, 'refund') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_REFUND');
        }

        if (strpos($context, 'manual') !== false || strpos($context, 'admin') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_MANUAL');
        }

        if (strpos($context, 'expire') !== false || strpos($context, 'burn') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_EXPIRED');
        }

        return $context ?: Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_OTHER');
    }
}
