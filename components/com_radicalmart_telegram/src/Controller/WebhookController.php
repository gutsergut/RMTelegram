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
use Joomla\CMS\Log\Log;

class WebhookController extends BaseController
{
    public function receive(): bool
    {
        $app    = Factory::getApplication();
        $secret = (string) $app->input->get('secret', '', 'string');

        // Read component params
        $params = $this->getParams();

        // ДИАГНОСТИКА: проверка загрузки параметров
        Log::addLogger([
            'text_file' => 'com_radicalmart_telegram.php',
            'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}",
        ], Log::ALL, ['com_radicalmart.telegram']);

        Log::add('RMParamsHelper exists: ' . (class_exists(RMParamsHelper::class) ? 'YES' : 'NO'), Log::DEBUG, 'com_radicalmart.telegram');
        Log::add('Params object type: ' . get_class($params), Log::DEBUG, 'com_radicalmart.telegram');

        // Попробуем разные ключи
        $expected = (string) $params->get('webhook_secret');
        $altKey = (string) $params->get('com_radicalmart_telegram.webhook_secret');

        Log::add('webhook_secret value length: ' . strlen($expected), Log::DEBUG, 'com_radicalmart.telegram');
        if (strlen($altKey) > 0) {
            Log::add('Alternative key com_radicalmart_telegram.webhook_secret found: ' . $altKey, Log::DEBUG, 'com_radicalmart.telegram');
        }

        // ДИАГНОСТИКА
        Log::add('Webhook receive: secret=' . (strlen($secret) > 0 ? 'present (' . strlen($secret) . ' chars)' : 'EMPTY'), Log::DEBUG, 'com_radicalmart.telegram');
        Log::add('Expected secret: ' . (strlen($expected) > 0 ? 'present (' . strlen($expected) . ' chars)' : 'EMPTY'), Log::DEBUG, 'com_radicalmart.telegram');

        if (strlen($secret) > 0 && strlen($expected) > 0) {
            Log::add('Secret match: ' . ($secret === $expected ? 'YES' : 'NO (first 10 chars: got=' . substr($secret, 0, 10) . ', expected=' . substr($expected, 0, 10) . ')'), Log::DEBUG, 'com_radicalmart.telegram');
        }

        if (empty($expected) || $secret !== $expected) {
            $app->setHeader('Status', '403 Forbidden', true);
            Log::add('Forbidden webhook call - secret mismatch or empty', Log::WARNING, 'com_radicalmart.telegram');
            echo 'forbidden';
            $app->close();
        }

        Log::add('Webhook authorized, processing update...', Log::DEBUG, 'com_radicalmart.telegram');

        // Read raw body (JSON update from Telegram)
        $raw = file_get_contents('php://input') ?: '';

        // Handle update (basic)
        $client  = new TelegramClient((string) $params->get('bot_token'));
        $handler = new UpdateHandler($client);
        try {
            $handler->handle($raw);
        } catch (\Throwable $e) {
            Log::add('Webhook error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
        }

        // Ack
        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo 'ok';
        $app->close();

        return true;
    }

    protected function getParams()
    {
        Log::addLogger([
            'text_file' => 'com_radicalmart_telegram.php',
        ], Log::ALL, ['com_radicalmart.telegram']);

        // Попробуем стандартный способ Joomla 5
        try {
            $componentParams = ComponentHelper::getParams('com_radicalmart_telegram');
            Log::add('ComponentHelper::getParams webhook_secret length: ' . strlen($componentParams->get('webhook_secret', '')), Log::DEBUG, 'com_radicalmart.telegram');

            if (strlen($componentParams->get('webhook_secret', '')) > 0) {
                Log::add('Using ComponentHelper (webhook_secret found)', Log::DEBUG, 'com_radicalmart.telegram');
                return $componentParams;
            }
        } catch (\Exception $e) {
            Log::add('ComponentHelper::getParams failed: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
        }

        // Reuse RadicalMart params helper to get component params style (consistent with project)
        if (class_exists(RMParamsHelper::class)) {
            $params = RMParamsHelper::getComponentParams('com_radicalmart_telegram');
            Log::add('Params loaded via RMParamsHelper, webhook_secret length: ' . strlen($params->get('webhook_secret', '')), Log::DEBUG, 'com_radicalmart.telegram');
            return $params;
        }

        $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        Log::add('Params loaded via Factory, webhook_secret length: ' . strlen($params->get('webhook_secret', '')), Log::DEBUG, 'com_radicalmart.telegram');
        return $params;
    }
}
