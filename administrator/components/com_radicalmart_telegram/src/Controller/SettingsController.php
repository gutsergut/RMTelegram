<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;

class SettingsController extends BaseController
{
    /**
     * Установить webhook
     */
    public function setWebhook()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        $botToken = $params->get('bot_token', '');
        $webhookSecret = $params->get('webhook_secret', '');

        if (empty($botToken)) {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_BOT_TOKEN_NOT_SET'), 'error');
            $this->setRedirect('index.php?option=com_radicalmart_telegram&view=settings');
            return;
        }

        if (empty($webhookSecret)) {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_SECRET_NOT_SET'), 'error');
            $this->setRedirect('index.php?option=com_radicalmart_telegram&view=settings');
            return;
        }

        $webhookUrl = Uri::root() . 'index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=' . urlencode($webhookSecret);

        try {
            $url = 'https://api.telegram.org/bot' . $botToken . '/setWebhook?url=' . urlencode($webhookUrl);
            $response = @file_get_contents($url);

            if ($response === false) {
                throw new \Exception(Text::_('COM_RADICALMART_TELEGRAM_API_CONNECTION_ERROR'));
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['ok']) || !$data['ok']) {
                throw new \Exception($data['description'] ?? Text::_('COM_RADICALMART_TELEGRAM_API_UNKNOWN_ERROR'));
            }

            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_SET_SUCCESS'), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_WEBHOOK_SET_ERROR', $e->getMessage()), 'error');
        }

        $this->setRedirect('index.php?option=com_radicalmart_telegram&view=settings');
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        $botToken = $params->get('bot_token', '');

        if (empty($botToken)) {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_BOT_TOKEN_NOT_SET'), 'error');
            $this->setRedirect('index.php?option=com_radicalmart_telegram&view=settings');
            return;
        }

        try {
            $url = 'https://api.telegram.org/bot' . $botToken . '/deleteWebhook';
            $response = @file_get_contents($url);

            if ($response === false) {
                throw new \Exception(Text::_('COM_RADICALMART_TELEGRAM_API_CONNECTION_ERROR'));
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['ok']) || !$data['ok']) {
                throw new \Exception($data['description'] ?? Text::_('COM_RADICALMART_TELEGRAM_API_UNKNOWN_ERROR'));
            }

            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_WEBHOOK_DELETE_SUCCESS'), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_WEBHOOK_DELETE_ERROR', $e->getMessage()), 'error');
        }

        $this->setRedirect('index.php?option=com_radicalmart_telegram&view=settings');
    }
}
