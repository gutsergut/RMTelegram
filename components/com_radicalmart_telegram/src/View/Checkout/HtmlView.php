<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Checkout;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null; // Данные пользователя из TelegramUserHelper
    public $customerId = 0;
    public $points = 0;
    public $pointsEquivalent = '';
    public $pointsEnabled = false;
    public $codesEnabled = false;
    public $appliedPoints = 0; // Текущее количество применённых баллов
    public $appliedCode = '';  // Текущий применённый промокод

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);
        $lang->load('com_radicalmart_bonuses', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');

        // Используем централизованный хелпер для идентификации пользователя
        $this->tgUser = TelegramUserHelper::getCurrentUser();

        // Загружаем данные о баллах
        $this->loadBonusesData();

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
     * Загрузка данных о бонусной системе
     */
    protected function loadBonusesData(): void
    {
        try {
            $app = Factory::getApplication();

            // Проверяем настройки бонусов
            $rmParams = ParamsHelper::getComponentParams();
            $this->pointsEnabled = ((int) $rmParams->get('bonuses_points_enabled', 1) === 1);
            $this->codesEnabled = ((int) $rmParams->get('bonuses_codes_enabled', 1) === 1);

            // Загружаем текущие значения из сессии RadicalMart
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $this->appliedPoints = (int) ($sessionData['plugins']['bonuses']['points'] ?? 0);
            $this->appliedCode = (string) ($sessionData['plugins']['bonuses']['codes'] ?? '');

            if (!$this->pointsEnabled) {
                return;
            }

            $userId = $this->tgUser['user_id'] ?? 0;
            if ($userId <= 0) {
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            // Получаем customer_id по user_id
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
            // Если компонент bonuses не установлен или ошибка
            $this->points = 0;
            $this->pointsEnabled = false;
        }
    }
}
