<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\View\Settings;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    protected $webhookInfo = null;
    protected $webhookUrl = '';
    protected $botInfo = null;

    public function display($tpl = null)
    {
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        $botToken = $params->get('bot_token', '');
        $webhookSecret = $params->get('webhook_secret', '');

        // Формируем URL webhook
        $this->webhookUrl = Uri::root() . 'index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=' . urlencode($webhookSecret);

        // Получаем информацию о webhook
        if (!empty($botToken)) {
            $this->webhookInfo = $this->getWebhookInfo($botToken);
            $this->botInfo = $this->getBotInfo($botToken);
        }

        parent::display($tpl);
    }

    /**
     * Получить информацию о webhook
     */
    protected function getWebhookInfo($botToken)
    {
        try {
            $url = 'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo';
            $response = @file_get_contents($url);

            if ($response === false) {
                return ['error' => 'Не удалось подключиться к Telegram API'];
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['ok']) || !$data['ok']) {
                return ['error' => 'Ошибка API: ' . ($data['description'] ?? 'Unknown error')];
            }

            return $data['result'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Получить информацию о боте
     */
    protected function getBotInfo($botToken)
    {
        try {
            $url = 'https://api.telegram.org/bot' . $botToken . '/getMe';
            $response = @file_get_contents($url);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['ok']) || !$data['ok']) {
                return null;
            }

            return $data['result'];
        } catch (\Exception $e) {
            return null;
        }
    }
}
