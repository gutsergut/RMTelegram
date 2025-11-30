<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\Http;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Log\Log;

class TelegramClient
{
    protected string $token;

    public function __construct(?string $token = null)
    {
        $this->token = (string) ($token ?? Factory::getApplication()->getParams('com_radicalmart_telegram')->get('bot_token', ''));
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function api(string $method, array $params = []): Registry
    {
        // Log masked token for debugging
        $tokenLen = strlen($this->token);
        $maskedToken = $tokenLen > 10 ? substr($this->token, 0, 5) . '...' . substr($this->token, -5) . " ({$tokenLen} chars)" : "EMPTY or TOO SHORT ({$tokenLen} chars)";
        Log::add('TelegramClient::api calling ' . $method . ' with ' . count($params) . ' params, token=' . $maskedToken, Log::DEBUG, 'com_radicalmart.telegram');

        if (empty($this->token)) {
            Log::add('API ' . $method . ' FAILED: bot_token is empty!', Log::ERROR, 'com_radicalmart.telegram');
            return new Registry(['ok' => false, 'description' => 'Bot token is not configured']);
        }

        $http = new Http();
        $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;

        try {
            $response = $http->post($url, $params, ['Content-Type' => 'application/x-www-form-urlencoded']);
            $body = (string) $response->body;
            $data = new Registry($body);

            $ok = $data->get('ok', false);
            Log::add('API ' . $method . ' response: ok=' . ($ok ? 'true' : 'false') . ', code=' . $response->code, Log::DEBUG, 'com_radicalmart.telegram');

            if (!$ok) {
                $error = $data->get('description', 'Unknown error');
                Log::add('API ' . $method . ' error: ' . $error, Log::WARNING, 'com_radicalmart.telegram');
            }

            return $data;
        } catch (\Throwable $e) {
            Log::add('API ' . $method . ' exception: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            return new Registry(['ok' => false, 'description' => $e->getMessage()]);
        }
    }

    public function sendMessage(int $chatId, string $text, array $options = []): bool
    {
        if (isset($options['reply_markup']) && is_array($options['reply_markup'])) {
            $options['reply_markup'] = json_encode($options['reply_markup'], JSON_UNESCAPED_UNICODE);
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $options);

        $data = $this->api('sendMessage', $params);
        return (bool) $data->get('ok', false);
    }

    public function answerCallbackQuery(string $callbackId, string $text = '', bool $showAlert = false): bool
    {
        $data = $this->api('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ]);
        return (bool) $data->get('ok', false);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): bool
    {
        if (isset($options['reply_markup']) && is_array($options['reply_markup'])) {
            $options['reply_markup'] = json_encode($options['reply_markup'], JSON_UNESCAPED_UNICODE);
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $options);

        $data = $this->api('editMessageText', $params);
        return (bool) $data->get('ok', false);
    }

    public function sendInvoice(int $chatId, string $title, string $description, string $payload, string $providerToken, string $currency, int $amountMinor, array $options = []): bool
    {
        $prices = json_encode([[ 'label' => $title, 'amount' => $amountMinor ]], JSON_UNESCAPED_UNICODE);
        $params = array_merge([
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => $prices,
        ], $options);
        $data = $this->api('sendInvoice', $params);
        return (bool) $data->get('ok', false);
    }

    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, string $errorMessage = ''): bool
    {
        $params = [ 'pre_checkout_query_id' => $preCheckoutQueryId, 'ok' => $ok ? 'true' : 'false' ];
        if (!$ok && $errorMessage !== '') { $params['error_message'] = $errorMessage; }
        $data = $this->api('answerPreCheckoutQuery', $params);
        return (bool) $data->get('ok', false);
    }
}
