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
        $chatId = $this->tgUser['chat_id'] ?? 0;

        // Get phone from tg_data if available
        $phone = '';
        if (!empty($this->tgUser['tg_data']->phone)) {
            $phone = $this->tgUser['tg_data']->phone;
        } elseif (!empty($this->tgUser['phone'])) {
            $phone = $this->tgUser['phone'];
        }

        $this->userData = [
            'first_name' => '',
            'second_name' => '',
            'last_name' => '',
            'phone' => $phone,
            'email' => '',
            'email_verified' => false,
        ];

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // First, try to get FIO, email and tg_name from telegram_users
            if ($chatId > 0) {
                $query = $db->getQuery(true)
                    ->select(['first_name', 'second_name', 'last_name', 'email', 'email_verified', 'tg_first_name', 'tg_last_name'])
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chatId);
                $tgProfile = $db->setQuery($query, 0, 1)->loadAssoc();

                if ($tgProfile) {
                    // Email из telegram_users (приоритет)
                    if (!empty($tgProfile['email'])) {
                        $this->userData['email'] = $tgProfile['email'];
                        $this->userData['email_verified'] = (bool) $tgProfile['email_verified'];
                    }

                    // FIO из профиля
                    if (!empty($tgProfile['first_name'])) {
                        $this->userData['first_name'] = $tgProfile['first_name'];
                    }
                    if (!empty($tgProfile['second_name'])) {
                        $this->userData['second_name'] = $tgProfile['second_name'];
                    }
                    if (!empty($tgProfile['last_name'])) {
                        $this->userData['last_name'] = $tgProfile['last_name'];
                    }

                    // Fallback: если FIO пустое, используем tg_first_name/tg_last_name из Telegram
                    if (empty($this->userData['first_name']) && !empty($tgProfile['tg_first_name'])) {
                        $this->userData['first_name'] = $tgProfile['tg_first_name'];
                    }
                    if (empty($this->userData['last_name']) && !empty($tgProfile['tg_last_name'])) {
                        $this->userData['last_name'] = $tgProfile['tg_last_name'];
                    }
                }
            }

            // If FIO is empty, try to get from latest order (fallback)
            if ($userId > 0 && empty($this->userData['first_name']) && empty($this->userData['last_name'])) {
                $query = $db->getQuery(true)
                    ->select('contacts')
                    ->from($db->quoteName('#__radicalmart_orders'))
                    ->where($db->quoteName('created_by') . ' = ' . (int) $userId)
                    ->order('id DESC');
                $db->setQuery($query, 0, 1);
                $contactsJson = $db->loadResult();

                if ($contactsJson) {
                    $contacts = json_decode($contactsJson, true) ?: [];
                    if (!empty($contacts['first_name']) && empty($this->userData['first_name'])) {
                        $this->userData['first_name'] = $contacts['first_name'];
                    }
                    if (!empty($contacts['second_name']) && empty($this->userData['second_name'])) {
                        $this->userData['second_name'] = $contacts['second_name'];
                    }
                    if (!empty($contacts['last_name']) && empty($this->userData['last_name'])) {
                        $this->userData['last_name'] = $contacts['last_name'];
                    }
                    if (!empty($contacts['phone']) && empty($this->userData['phone'])) {
                        $this->userData['phone'] = $contacts['phone'];
                    }
                    // Email из заказа только если нет в telegram_users
                    if (!empty($contacts['email']) && empty($this->userData['email'])) {
                        $this->userData['email'] = $contacts['email'];
                    }
                }
            }

            // If no email from telegram_users or order, get from Joomla user
            if (empty($this->userData['email']) && $userId > 0) {
                $user = Factory::getUser($userId);
                if ($user && !$user->guest) {
                    $this->userData['email'] = $user->email;
                }
            }

            // Check if user has Joomla account (for password change functionality)
            $this->userData['has_joomla_account'] = ($userId > 0);
        } catch (\Throwable $e) {
            // Ignore errors
        }
    }
}
