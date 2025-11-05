<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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
        $expected = (string) $params->get('webhook_secret');

        // Logger setup
        Log::addLogger([
            'text_file' => 'com_radicalmart_telegram.php',
            'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}",
        ], Log::ALL, ['com_radicalmart.telegram']);

        if (empty($expected) || $secret !== $expected) {
            $app->setHeader('Status', '403 Forbidden', true);
            Log::add('Forbidden webhook call', Log::WARNING, 'com_radicalmart.telegram');
            echo 'forbidden';
            $app->close();
        }

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
        // Reuse RadicalMart params helper to get component params style (consistent with project)
        if (class_exists(RMParamsHelper::class)) {
            return RMParamsHelper::getComponentParams('com_radicalmart_telegram');
        }

        return Factory::getApplication()->getParams('com_radicalmart_telegram');
    }
}
