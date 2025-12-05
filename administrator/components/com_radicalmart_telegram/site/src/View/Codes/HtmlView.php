<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Promo codes view for Telegram WebApp
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Codes;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null;
    public $customerId = 0;
    public $userId = 0;
    public $items = [];
    public $codesEnabled = false;
    public $start = 0;
    public $limit = 10;
    public $hasMore = false;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart_bonuses', JPATH_SITE);
        $lang->load('com_radicalmart', JPATH_SITE);

        $app = Factory::getApplication();
        $this->params = $app->getParams('com_radicalmart_telegram');

        $this->start = $app->input->getInt('start', 0);
        $this->limit = 10;

        $this->tgUser = TelegramUserHelper::getCurrentUser();

        $this->loadCodesData();

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

    protected function loadCodesData(): void
    {
        try {
            $rmParams = ParamsHelper::getComponentParams();
            $this->codesEnabled = ((int) $rmParams->get('bonuses_codes_enabled', 1) === 1);

            if (!$this->codesEnabled) {
                return;
            }

            $this->userId = $this->tgUser['user_id'] ?? 0;
            if ($this->userId <= 0) {
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__radicalmart_customers'))
                ->where($db->quoteName('user_id') . ' = ' . (int) $this->userId);
            $db->setQuery($query);
            $this->customerId = (int) $db->loadResult();

            if ($this->customerId <= 0) {
                return;
            }

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__radicalmart_bonuses_codes'))
                ->where('FIND_IN_SET(' . (int) $this->userId . ', ' . $db->quoteName('customers') . ')')
                ->order($db->quoteName('created') . ' DESC')
                ->setLimit($this->limit + 1, $this->start);
            $db->setQuery($query);
            $items = $db->loadObjectList();

            if (count($items) > $this->limit) {
                $this->hasMore = true;
                array_pop($items);
            }

            $this->items = $this->prepareItems($items);

        } catch (\Throwable $e) {
            $this->items = [];
        }
    }

    protected function prepareItems(array $items): array
    {
        $linkEnabled = true;
        $linkPrefix = 'rbc';
        
        try {
            $rmParams = ParamsHelper::getComponentParams();
            $linkEnabled = ((int) $rmParams->get('bonuses_codes_cookies_enabled', 1) === 1);
            $linkPrefix = $rmParams->get('bonuses_codes_cookies_selector', 'rbc');
        } catch (\Throwable $e) {}

        $link = \Joomla\CMS\Uri\Uri::root() . '?' . $linkPrefix . '=';

        foreach ($items as $item) {
            $item->plugins = new Registry($item->plugins);
            $item->currency = PriceHelper::getCurrency($item->currency ?? 'RUB');
            $item->link = $linkEnabled ? $link . $item->code : false;

            $item->discount_clean = PriceHelper::cleanAdjustmentValue($item->discount);
            if (strpos($item->discount_clean, '%') !== false) {
                $item->discount_string = $item->discount_clean;
            } else {
                $item->discount_string = PriceHelper::toString($item->discount_clean, $item->currency['code'] ?? 'RUB');
            }

            $item->expired = false;
            if (!empty($item->end) && $item->end !== '0000-00-00 00:00:00') {
                $endDate = Factory::getDate($item->end);
                if ($endDate->toUnix() < Factory::getDate()->toUnix()) {
                    $item->expired = true;
                }
            }

            $item->usageExceeded = false;
            if ($item->orders_limit > 0) {
                $usageCount = $this->getCodeUsageCount($item->id);
                if ($usageCount >= $item->orders_limit) {
                    $item->usageExceeded = true;
                }
                $item->usageCount = $usageCount;
            } else {
                $item->usageCount = 0;
            }

            $item->enabled = !$item->expired && !$item->usageExceeded;
            $item->restrictions = $this->getCodeRestrictions($item);
        }

        return $items;
    }

    protected function getCodeUsageCount(int $codeId): int
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__radicalmart_orders'))
                ->where($db->quoteName('plugins') . ' LIKE ' . $db->quote('%"codes":"' . $codeId . '"%'));
            $db->setQuery($query);
            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function getCodeRestrictions(object $item): array
    {
        $restrictions = [
            'products' => [],
            'categories' => [],
            'products_rule' => '',
            'categories_rule' => '',
            'has_restrictions' => false
        ];

        $plugins = $item->plugins;

        $productsRule = $plugins->get('products.products_rule', '');
        $productsIds = $plugins->get('products.products_ids', []);

        if (!empty($productsRule) && !empty($productsIds)) {
            $restrictions['products_rule'] = $productsRule;
            $restrictions['has_restrictions'] = true;

            $productIds = array_filter(array_map(function($p) {
                return is_object($p) ? ($p->product_id ?? 0) : (is_array($p) ? ($p['product_id'] ?? 0) : 0);
            }, (array) $productsIds));

            if (!empty($productIds)) {
                $restrictions['products'] = $this->getProductNames($productIds);
            }
        }

        $categoriesRule = $plugins->get('categories.categories_rule', '');
        $categoriesIds = $plugins->get('categories.categories_ids', []);

        if (!empty($categoriesRule) && !empty($categoriesIds)) {
            $restrictions['categories_rule'] = $categoriesRule;
            $restrictions['has_restrictions'] = true;

            $categoryIds = array_filter(array_map(function($c) {
                return is_object($c) ? ($c->category_id ?? 0) : (is_array($c) ? ($c['category_id'] ?? 0) : 0);
            }, (array) $categoriesIds));

            if (!empty($categoryIds)) {
                $restrictions['categories'] = $this->getCategoryNames($categoryIds);
            }
        }

        return $restrictions;
    }

    protected function getProductNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__radicalmart_products'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
            $db->setQuery($query);
            return $db->loadAssocList('id', 'title') ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getCategoryNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__radicalmart_categories'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
            $db->setQuery($query);
            return $db->loadAssocList('id', 'title') ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
