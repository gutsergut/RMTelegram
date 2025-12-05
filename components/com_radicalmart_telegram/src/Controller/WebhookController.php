<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper as RMParamsHelper;
use Joomla\Component\RadicalMartTelegram\Site\Service\TelegramClient;
use Joomla\Component\RadicalMartTelegram\Site\Service\UpdateHandler;
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;

class WebhookController extends BaseController
{
    public function receive(): bool
    {
        $app    = Factory::getApplication();
        $secret = (string) $app->input->get('secret', '', 'string');

        // Read component params
        $params = $this->getParams();

        // ДИАГНОСТИКА: проверка загрузки параметров
        LogHelper::debug('RMParamsHelper exists: ' . (class_exists(RMParamsHelper::class) ? 'YES' : 'NO'));
        LogHelper::debug('Params object type: ' . get_class($params));

        // Попробуем разные ключи
        $expected = (string) $params->get('webhook_secret');
        $altKey = (string) $params->get('com_radicalmart_telegram.webhook_secret');

        LogHelper::debug('webhook_secret value length: ' . strlen($expected));
        if (strlen($altKey) > 0) {
            LogHelper::debug('Alternative key com_radicalmart_telegram.webhook_secret found: ' . $altKey);
        }

        // ДИАГНОСТИКА
        LogHelper::debug('Webhook receive: secret=' . (strlen($secret) > 0 ? 'present (' . strlen($secret) . ' chars)' : 'EMPTY'));
        LogHelper::debug('Expected secret: ' . (strlen($expected) > 0 ? 'present (' . strlen($expected) . ' chars)' : 'EMPTY'));

        if (strlen($secret) > 0 && strlen($expected) > 0) {
            LogHelper::debug('Secret match: ' . ($secret === $expected ? 'YES' : 'NO (first 10 chars: got=' . substr($secret, 0, 10) . ', expected=' . substr($expected, 0, 10) . ')'));
        }

        if (empty($expected) || $secret !== $expected) {
            $app->setHeader('Status', '403 Forbidden', true);
            LogHelper::warning('Forbidden webhook call - secret mismatch or empty');
            echo 'forbidden';
            $app->close();
        }

        LogHelper::debug('Webhook authorized, processing update...');

        // Read raw body (JSON update from Telegram)
        $raw = file_get_contents('php://input') ?: '';

        // Handle update (basic)
        $botToken = (string) $params->get('bot_token', '');
        $botTokenLen = strlen($botToken);
        $maskedToken = $botTokenLen > 10 ? substr($botToken, 0, 5) . '...' . substr($botToken, -5) : 'EMPTY or TOO SHORT';
        LogHelper::debug('bot_token from params: ' . $maskedToken . ' (' . $botTokenLen . ' chars)');

        $client  = new TelegramClient($botToken);
        $handler = new UpdateHandler($client);
        try {
            $handler->handle($raw);
        } catch (\Throwable $e) {
            LogHelper::error('Webhook error: ' . $e->getMessage());
        }

        // Ack
        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo 'ok';
        $app->close();

        return true;
    }

    protected function getParams()
    {
        // Попробуем стандартный способ Joomla 5
        try {
            $componentParams = ComponentHelper::getParams('com_radicalmart_telegram');
            LogHelper::debug('ComponentHelper::getParams webhook_secret length: ' . strlen($componentParams->get('webhook_secret', '')));

            if (strlen($componentParams->get('webhook_secret', '')) > 0) {
                LogHelper::debug('Using ComponentHelper (webhook_secret found)');
                return $componentParams;
            }
        } catch (\Exception $e) {
            LogHelper::warning('ComponentHelper::getParams failed: ' . $e->getMessage());
        }

        // Reuse RadicalMart params helper to get component params style (consistent with project)
        if (class_exists(RMParamsHelper::class)) {
            $params = RMParamsHelper::getComponentParams('com_radicalmart_telegram');
            LogHelper::debug('Params loaded via RMParamsHelper, webhook_secret length: ' . strlen($params->get('webhook_secret', '')));
            return $params;
        }

        $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        LogHelper::debug('Params loaded via Factory, webhook_secret length: ' . strlen($params->get('webhook_secret', '')));
        return $params;
    }
}
