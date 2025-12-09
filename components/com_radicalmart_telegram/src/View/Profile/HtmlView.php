<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Profile;

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
    public $points = 0;
    public $pointsEquivalent = '';
    public $customerId = 0;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');

        // Используем централизованный хелпер для идентификации пользователя
        $this->tgUser = TelegramUserHelper::getCurrentUser();

        // Загружаем баланс баллов
        $this->loadPointsBalance();

        $app = Factory::getApplication();

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

    /**
     * Загрузка баланса баллов пользователя
     */
    protected function loadPointsBalance(): void
    {
        $userId = $this->tgUser['user_id'] ?? 0;
        if ($userId <= 0) {
            return;
        }

        try {
            // Получаем customer_id по user_id
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__radicalmart_customers'))
                ->where($db->quoteName('user_id') . ' = ' . (int) $userId);
            $db->setQuery($query);
            $this->customerId = (int) $db->loadResult();

            if ($this->customerId > 0 && class_exists(PointsHelper::class)) {
                // Получаем баланс баллов
                $this->points = (float) PointsHelper::getCustomerPoints($this->customerId);

                // Конвертируем в рубли
                if ($this->points > 0) {
                    $currency = 'RUB';
                    $money = PointsHelper::convertToMoney($this->points, $currency);
                    $this->pointsEquivalent = number_format($money, 0, ',', ' ') . ' ₽';
                }
            }
        } catch (\Throwable $e) {
            // Если компонент bonuses не установлен или ошибка - баллы = 0
            $this->points = 0;
        }
    }
}
