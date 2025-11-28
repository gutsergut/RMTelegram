<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Referrals view for Telegram WebApp
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Referrals;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\ReferralHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null;
    public $userId = 0;

    public $inChain = false;
    public $referralCodes = [];
    public $canCreateCode = false;
    public $canCustomCode = false;
    public $codesLimit = 0;
    public $codesLimitReached = false;
    public $templateDiscount = '';
    public $myReferrals = [];
    public $parentChain = [];
    public $totalEarnedPoints = 0;
    public $referralsCount = 0;

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

        $this->loadReferralData();

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

    protected function loadReferralData(): void
    {
        $this->userId = $this->tgUser['user_id'] ?? 0;
        if ($this->userId <= 0) {
            return;
        }

        try {
            $this->inChain = ReferralHelper::inChain($this->userId);

            if (!$this->inChain) {
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            $this->loadReferralCodes($db);
            $this->loadMyReferrals($db);
            $this->loadParentChain();
            $this->loadEarnedPoints($db);
            $this->checkCanCreateCode();

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    protected function loadReferralCodes($db): void
    {
        $rmParams = ParamsHelper::getComponentParams();
        $linkEnabled = ((int) $rmParams->get('bonuses_codes_cookies_enabled', 1) === 1);
        $linkPrefix = $rmParams->get('bonuses_codes_cookies_selector', 'rbc');
        $linkBase = Uri::root() . '?' . $linkPrefix . '=';

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__radicalmart_bonuses_codes'))
            ->where($db->quoteName('referral') . ' = 1')
            ->where($db->quoteName('created_by') . ' = ' . (int) $this->userId)
            ->order('id DESC');

        $items = $db->setQuery($query)->loadObjectList();

        foreach ($items as $item) {
            $item->currency = PriceHelper::getCurrency($item->currency);
            $item->link = $linkEnabled ? $linkBase . $item->code : false;

            $item->discount = PriceHelper::cleanAdjustmentValue($item->discount);
            $item->discount_string = (strpos($item->discount, '%') !== false)
                ? $item->discount
                : PriceHelper::toString($item->discount, $item->currency['code']);

            if (!is_array($item->customers)) {
                $customers = !empty($item->customers) ? explode(',', $item->customers) : [];
                $item->customers = array_filter($customers, function($v) { return !empty($v); });
            }

            $item->expires = (!empty($item->expires) && $item->expires !== '0000-00-00 00:00:00')
                ? $item->expires : false;

            $item->enabled = true;
            if ($item->customers_limit > 0 && count($item->customers) >= $item->customers_limit) {
                $item->enabled = false;
            }
            if ($item->expires) {
                $current = new \Joomla\CMS\Date\Date();
                $expires = new \Joomla\CMS\Date\Date($item->expires);
                if ($current->toUnix() >= $expires->toUnix()) {
                    $item->enabled = false;
                }
            }

            $item->used_count = count($item->customers);
        }

        $this->referralCodes = $items;
    }

    protected function loadMyReferrals($db): void
    {
        $query = $db->getQuery(true)
            ->select(['r.id as referral_id', 'r.created', 'u.id as user_id', 'u.name', 'u.email', 'u.registerDate'])
            ->from($db->quoteName('#__radicalmart_bonuses_referrals', 'r'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = r.id')
            ->where($db->quoteName('r.parent_id') . ' = ' . (int) $this->userId)
            ->order($db->quoteName('r.created') . ' DESC');

        $countQuery = clone $query;
        $countQuery->clear('select')->clear('order')->select('COUNT(*)');
        $this->referralsCount = (int) $db->setQuery($countQuery)->loadResult();

        $query->setLimit($this->limit + 1, $this->start);
        $items = $db->setQuery($query)->loadObjectList();

        if (count($items) > $this->limit) {
            $this->hasMore = true;
            array_pop($items);
        }

        foreach ($items as $item) {
            $item->level = 1;
            $item->masked_email = $this->maskEmail($item->email);
            $item->sub_referrals = $this->loadSubReferrals($db, (int) $item->referral_id, 2);
        }

        $this->myReferrals = $items;
    }

    protected function loadSubReferrals($db, int $parentId, int $level, int $maxLevel = 5): array
    {
        if ($level > $maxLevel) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select(['r.id as referral_id', 'r.created', 'u.id as user_id', 'u.name', 'u.email', 'u.registerDate'])
            ->from($db->quoteName('#__radicalmart_bonuses_referrals', 'r'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = r.id')
            ->where($db->quoteName('r.parent_id') . ' = ' . $parentId)
            ->order($db->quoteName('r.created') . ' DESC')
            ->setLimit(50);

        $items = $db->setQuery($query)->loadObjectList();

        foreach ($items as $item) {
            $item->level = $level;
            $item->masked_email = $this->maskEmail($item->email);
            $item->sub_referrals = $this->loadSubReferrals($db, (int) $item->referral_id, $level + 1, $maxLevel);
        }

        return $items;
    }

    protected function loadParentChain(): void
    {
        $chain = [];
        $currentId = $this->userId;
        $maxLevels = 10;
        $i = 0;

        while ($i < $maxLevels) {
            $parent = ReferralHelper::getParent($currentId);
            if (!$parent || empty($parent->parent_id)) {
                break;
            }

            $user = Factory::getUser($parent->parent_id);
            if ($user && $user->id > 0) {
                $chain[] = (object) [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'masked_email' => $this->maskEmail($user->email),
                    'level' => $i + 1,
                    'created' => $parent->created
                ];
            }

            $currentId = (int) $parent->parent_id;
            $i++;
        }

        $this->parentChain = $chain;
    }

    protected function loadEarnedPoints($db): void
    {
        $query = $db->getQuery(true)
            ->select('brl.data')
            ->from($db->quoteName('#__radicalmart_bonuses_referrals_logs', 'brl'))
            ->where($db->quoteName('brl.parent_id') . ' = ' . (int) $this->userId)
            ->where($db->quoteName('brl.action') . ' IN (' . implode(',', [
                $db->quote('order'),
                $db->quote('points'),
                $db->quote('bonus'),
                $db->quote('accrual')
            ]) . ')');

        $logs = $db->setQuery($query)->loadObjectList();

        $total = 0;
        foreach ($logs as $log) {
            if (!empty($log->data)) {
                $data = new Registry($log->data);
                $points = $data->get('points', 0);
                if (empty($points)) {
                    $points = $data->get('bonuses', 0);
                }
                if (empty($points)) {
                    $points = $data->get('value', 0);
                }
                if (empty($points)) {
                    $points = $data->get('amount', 0);
                }
                $total += (float) $points;
            }
        }

        $this->totalEarnedPoints = $total;
    }

    protected function checkCanCreateCode(): void
    {
        try {
            $rmParams = ParamsHelper::getComponentParams();

            // Проверяем включены ли реферальные коды
            if ((int) $rmParams->get('bonuses_referral_codes_enabled', 1) === 0) {
                $this->canCreateCode = false;
                return;
            }

            // Может ли пользователь задать свой код
            $this->canCustomCode = ((int) $rmParams->get('bonuses_referral_codes_custom_code', 1) === 1);

            // Лимит кодов
            $this->codesLimit = (int) $rmParams->get('bonuses_referral_codes_limit', 1);
            $currentCount = count($this->referralCodes);

            if ($this->codesLimit > 0 && $currentCount >= $this->codesLimit) {
                $this->canCreateCode = false;
                $this->codesLimitReached = true;
                return;
            }

            // Получаем информацию о скидке из шаблона кода
            $this->loadTemplateDiscount($rmParams);

            $this->canCreateCode = true;
        } catch (\Exception $e) {
            $this->canCreateCode = false;
        }
    }

    protected function loadTemplateDiscount($rmParams): void
    {
        try {
            // Получаем шаблон кода для текущей валюты (RUB)
            $templateId = (int) $rmParams->get('bonuses_referral_codes_template_RUB', 0);
            if ($templateId === 0) {
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(['discount', 'discount_type'])
                ->from($db->quoteName('#__radicalmart_bonuses_codes'))
                ->where($db->quoteName('id') . ' = ' . $templateId);

            $template = $db->setQuery($query)->loadObject();

            if ($template && !empty($template->discount)) {
                $discount = PriceHelper::cleanAdjustmentValue($template->discount);
                if (strpos($discount, '%') !== false) {
                    $this->templateDiscount = $discount;
                } else {
                    $this->templateDiscount = PriceHelper::toString($discount, 'RUB');
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }

    protected function maskEmail(?string $email): string
    {
        if (empty($email)) {
            return '***';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $localLen = strlen($local);
        if ($localLen <= 2) {
            $maskedLocal = str_repeat('*', $localLen);
        } else {
            $maskedLocal = $local[0] . str_repeat('*', $localLen - 2) . $local[$localLen - 1];
        }

        $domainParts = explode('.', $domain);
        $domainName = $domainParts[0];
        $domainExt = isset($domainParts[1]) ? '.' . $domainParts[1] : '';

        $domainLen = strlen($domainName);
        if ($domainLen <= 2) {
            $maskedDomain = str_repeat('*', $domainLen);
        } else {
            $maskedDomain = $domainName[0] . str_repeat('*', $domainLen - 2) . $domainName[$domainLen - 1];
        }

        return $maskedLocal . '@' . $maskedDomain . $domainExt;
    }

    public function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '***';
        }

        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len < 4) {
            return str_repeat('*', $len);
        }

        return substr($digits, 0, 3) . str_repeat('*', $len - 5) . substr($digits, -2);
    }
}
