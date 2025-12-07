<?php
/*
 * @package     com_radicalmart_telegram (site)
 * Settings View - редактирование данных профиля
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Settings;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null;
    public $userData = [];

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        $this->tgUser = TelegramUserHelper::getCurrentUser();
        $this->loadUserData();

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

    protected function loadUserData(): void
    {
        $userId = $this->tgUser['user_id'] ?? 0;

        // Get phone from tg_data if available
        $phone = '';
        if (!empty($this->tgUser['tg_data']->phone)) {
            $phone = $this->tgUser['tg_data']->phone;
        } elseif (!empty($this->tgUser['phone'])) {
            $phone = $this->tgUser['phone'];
        }

        // Get name from Telegram data
        $tgFirstName = '';
        if (!empty($this->tgUser['tg_data']->first_name)) {
            $tgFirstName = $this->tgUser['tg_data']->first_name;
        } elseif (!empty($this->tgUser['name'])) {
            $tgFirstName = $this->tgUser['name'];
        }

        $this->userData = [
            'first_name' => $tgFirstName,
            'second_name' => '',
            'last_name' => '',
            'phone' => $phone,
            'email' => '',
        ];

        if ($userId <= 0) {
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Get contacts from the latest order
            $query = $db->getQuery(true)
                ->select('contacts')
                ->from($db->quoteName('#__radicalmart_orders'))
                ->where($db->quoteName('created_by') . ' = ' . (int) $userId)
                ->order('id DESC');
            $db->setQuery($query, 0, 1);
            $contactsJson = $db->loadResult();

            if ($contactsJson) {
                $contacts = json_decode($contactsJson, true) ?: [];
                // Only override if order has data
                if (!empty($contacts['first_name'])) {
                    $this->userData['first_name'] = $contacts['first_name'];
                }
                if (!empty($contacts['second_name'])) {
                    $this->userData['second_name'] = $contacts['second_name'];
                }
                if (!empty($contacts['last_name'])) {
                    $this->userData['last_name'] = $contacts['last_name'];
                }
                if (!empty($contacts['phone'])) {
                    $this->userData['phone'] = $contacts['phone'];
                }
                if (!empty($contacts['email'])) {
                    $this->userData['email'] = $contacts['email'];
                }
            }

            // If no email from order, get from Joomla user
            if (empty($this->userData['email'])) {
                $user = Factory::getUser($userId);
                if ($user && !$user->guest) {
                    $this->userData['email'] = $user->email;
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }
    }
}
