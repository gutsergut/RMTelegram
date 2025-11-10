<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\View\Status;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    protected array $items = [];
    protected array $dbCounts = [];
    protected array $pvzCounts = [];
    protected array $postomatCounts = [];

    public function display($tpl = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Получаем список активных провайдеров из методов доставки RadicalMart
        $activeProviders = [];
        try {
            $query = $db->getQuery(true)
                ->select(['sm.plugin', 'sm.plugins'])
                ->from($db->quoteName('#__radicalmart_shipping_methods', 'sm'))
                ->where('sm.state = 1')
                ->where('sm.plugin != ' . $db->quote(''));

            $db->setQuery($query);
            $shippingMethods = $db->loadObjectList() ?: [];

            foreach ($shippingMethods as $method) {
                if ($method->plugin === 'apiship') {
                    $plugins = new Registry($method->plugins);
                    $providers = $plugins->get('apiship_providers', '');

                    if (is_array($providers)) {
                        $providersList = array_filter(array_map('trim', $providers));
                    } else {
                        $providersList = array_filter(array_map('trim', explode(',', (string) $providers)));
                    }

                    $activeProviders = array_merge($activeProviders, $providersList);
                }
            }

            $activeProviders = array_unique($activeProviders);
        } catch (\Throwable $e) {
            $activeProviders = [];
        }

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('provider'), $db->quoteName('last_fetch'), $db->quoteName('last_total')])
                ->from($db->quoteName('#__radicalmart_apiship_meta'));

            // Фильтруем только активные провайдеры
            if (!empty($activeProviders)) {
                $query->where($db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $activeProviders)) . ')');
            }

            $query->order($db->quoteName('provider') . ' ASC');
            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        // Реальные количества точек в базе по провайдерам (общее)
        $counts = [];
        try {
            $q2 = $db->getQuery(true)
                ->select([$db->quoteName('provider'), 'COUNT(*) AS cnt'])
                ->from($db->quoteName('#__radicalmart_apiship_points'));

            if (!empty($activeProviders)) {
                $q2->where($db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $activeProviders)) . ')');
            }

            $q2->group($db->quoteName('provider'));
            $db->setQuery($q2);
            $list = $db->loadAssocList() ?: [];
            foreach ($list as $r) {
                $counts[$r['provider']] = (int) $r['cnt'];
            }
        } catch (\Throwable $e) {
            $counts = [];
        }

        // Количество ПВЗ (pvz_type = '1')
        $pvzCounts = [];
        try {
            $q3 = $db->getQuery(true)
                ->select([$db->quoteName('provider'), 'COUNT(*) AS cnt'])
                ->from($db->quoteName('#__radicalmart_apiship_points'))
                ->where($db->quoteName('pvz_type') . ' = ' . $db->quote('1'));

            if (!empty($activeProviders)) {
                $q3->where($db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $activeProviders)) . ')');
            }

            $q3->group($db->quoteName('provider'));
            $db->setQuery($q3);
            $list = $db->loadAssocList() ?: [];
            foreach ($list as $r) {
                $pvzCounts[$r['provider']] = (int) $r['cnt'];
            }
        } catch (\Throwable $e) {
            $pvzCounts = [];
        }

        // Количество постоматов (pvz_type = '2')
        $postomatCounts = [];
        try {
            $q4 = $db->getQuery(true)
                ->select([$db->quoteName('provider'), 'COUNT(*) AS cnt'])
                ->from($db->quoteName('#__radicalmart_apiship_points'))
                ->where($db->quoteName('pvz_type') . ' = ' . $db->quote('2'));

            if (!empty($activeProviders)) {
                $q4->where($db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $activeProviders)) . ')');
            }

            $q4->group($db->quoteName('provider'));
            $db->setQuery($q4);
            $list = $db->loadAssocList() ?: [];
            foreach ($list as $r) {
                $postomatCounts[$r['provider']] = (int) $r['cnt'];
            }
        } catch (\Throwable $e) {
            $postomatCounts = [];
        }

        $this->items = $rows;
        $this->dbCounts = $counts;
        $this->pvzCounts = $pvzCounts;
        $this->postomatCounts = $postomatCounts;
        parent::display($tpl);
    }
}

?>

