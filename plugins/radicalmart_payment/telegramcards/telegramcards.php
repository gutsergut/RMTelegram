<?php
/**
 * @package    PlgRadicalMart_PaymentTelegramcards
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Http\Http;
use Joomla\Registry\Registry;

Log::addLogger([
    'text_file' => 'plg_radicalmart_payment_telegramcards.php',
], Log::ALL, ['plg_radicalmart_payment_telegramcards']);

class PlgRadicalMart_PaymentTelegramcards extends CMSPlugin
{
    protected $autoloadLanguage = true;
    protected $db;

    const PREFIX = 'telegramcards_';

    private array $methodParamsCache = [];

    protected function getPaymentMethodParams($pk = null): Registry
    {
        $pk = (int) $pk;
        if ($pk <= 0) return new Registry();
        if (!isset($this->methodParamsCache[$pk])) {
            $q = $this->db->getQuery(true)
                ->select('params')
                ->from($this->db->quoteName('#__radicalmart_payment_methods'))
                ->where('id = ' . $pk);
            $this->methodParamsCache[$pk] = ($res = $this->db->setQuery($q, 0, 1)->loadResult()) ? new Registry($res) : new Registry();
        }
        return $this->methodParamsCache[$pk];
    }

    public function onRadicalMartGetPaymentMethods(string $context, object $method, array $formData, array $products, array $currency)
    {
        // Show method; detailed checks will be done at pay time
        $method->disabled = false;
        $method->order = (object) [
            'id' => $method->id,
            'title' => $method->title,
            'code' => $method->code,
            'description' => $method->description,
            'price' => [],
        ];
    }

    public function onRadicalMartCheckOrderPay(string $context, object $order): bool
    {
        if (empty($order->payment) || empty($order->payment->plugin) || $order->payment->plugin !== $this->_name) {
            return false;
        }
        $token = $this->resolveProviderToken();
        if ($token === '') return false;
        // Chat mapping required
        $chatId = $this->findChatId((int) ($order->created_by ?? 0));
        if ($chatId <= 0) return false;
        return true;
    }

    public function onRadicalMartPay(object $order, array $links, Registry $params): array
    {
        $result = ['pay_instant' => false, 'link' => \Joomla\CMS\Uri\Uri::root(), 'page_title' => Text::_('PLG_RADICALMART_PAYMENT_TELEGRAMCARDS_TITLE'), 'page_message' => Text::_('PLG_RADICALMART_PAYMENT_TELEGRAMCARDS_MSG_SENT')];
        if (empty($order->payment) || empty($order->payment->plugin) || $order->payment->plugin !== $this->_name) {
            return $result;
        }

        $chatId = $this->findChatId((int) ($order->created_by ?? 0));
        $token  = $this->resolveProviderToken();
        if ($chatId <= 0 || $token === '') {
            Log::add('TelegramCards: missing chat/token', Log::WARNING, 'plg_radicalmart_payment_telegramcards');
            return $result;
        }

        // Compute amount/currency
        $currency = !empty($order->currency['code']) ? (string) $order->currency['code'] : 'RUB';
        $amountMinor = 0;
        if (!empty($order->total['final'])) {
            $amountMinor = (int) round(((float) $order->total['final']) * 100);
        } elseif (!empty($order->total['final_string'])) {
            $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
            $num = str_replace(' ', '', $num);
            $num = str_replace(',', '.', $num);
            $amountMinor = (int) round(((float) $num) * 100);
        }
        if ($amountMinor <= 0) {
            Log::add('TelegramCards: amountMinor=0', Log::WARNING, 'plg_radicalmart_payment_telegramcards');
            return $result;
        }

        $title   = 'Заказ ' . ($order->number ?? ('#' . (int) $order->id));
        $desc    = 'Оплата заказа в магазине';
        $payload = 'order:' . (string) ($order->number ?? (string) $order->id);

        // Send invoice
        try {
            $this->sendInvoice($chatId, $title, $desc, $payload, $token, $currency, $amountMinor);
        } catch (\Throwable $e) {
            Log::add('TelegramCards sendInvoice error: ' . $e->getMessage(), Log::ERROR, 'plg_radicalmart_payment_telegramcards');
        }

        return $result;
    }

    protected function resolveProviderToken(): string
    {
        // Prefer plugin params; fallback to component params
        $env = (string) $this->params->get('env', 'test');
        $provider = (string) $this->params->get('provider', 'yookassa');

        $token = '';
        if ($provider === 'yookassa') {
            $token = (string) ($env === 'prod' ? $this->params->get('yk_token_prod', '') : $this->params->get('yk_token_test', ''));
        } else {
            $token = (string) ($env === 'prod' ? $this->params->get('rk_token_prod', '') : $this->params->get('rk_token_test', ''));
        }
        if ($token !== '') return $token;

        // fallback to component config
        $cmp = Factory::getApplication()->getParams('com_radicalmart_telegram');
        if ($provider === 'yookassa') {
            return (string) ($env === 'prod' ? $cmp->get('yookassa_provider_token_prod', '') : $cmp->get('yookassa_provider_token_test', ''));
        }
        return (string) ($env === 'prod' ? $cmp->get('robokassa_provider_token_prod', '') : $cmp->get('robokassa_provider_token_test', ''));
    }

    protected function findChatId(int $userId): int
    {
        if ($userId <= 0) return 0;
        try {
            $db = $this->db;
            $q = $db->getQuery(true)
                ->select('chat_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId);
            return (int) $db->setQuery($q, 0, 1)->loadResult();
        } catch (\Throwable $e) { return 0; }
    }

    protected function sendInvoice(int $chatId, string $title, string $description, string $payload, string $providerToken, string $currency, int $amountMinor): void
    {
        $token = (string) Factory::getApplication()->getParams('com_radicalmart_telegram')->get('bot_token', '');
        if ($token === '') throw new \RuntimeException('Bot token is empty');
        $http = new Http();
        $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
        $url = 'https://api.telegram.org/bot' . $token . '/sendInvoice';
        $prices = json_encode([[ 'label' => $title, 'amount' => $amountMinor ]], JSON_UNESCAPED_UNICODE);
        $params = [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => $prices,
        ];
        $http->post($url, $params, ['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    /**
     * Optional refund handler (admin mark/real provider integration later)
     */
    public function onRadicalMartPaymentRefund(object $order, float $amount, Registry $params)
    {
        $ok = false; $message = '';
        try {
            $provider = (string) $this->params->get('provider', 'yookassa');
            if ($provider === 'yookassa') {
                $chargeId = $this->getProviderChargeId((int) $order->id);
                if ($chargeId === '') { throw new \RuntimeException('Provider charge id not found'); }
                [$ok, $message] = $this->doYooKassaRefund($chargeId, $amount);
            } else {
                $invoiceId = $this->getRobokassaInvoiceId($order);
                if ($invoiceId === '') { throw new \RuntimeException('InvoiceID not found'); }
                [$ok, $message] = $this->doRobokassaRefund($invoiceId, $amount);
            }
        } catch (\Throwable $e) { $message = $e->getMessage(); }
        try {
            $adm = new \Joomla\Component\RadicalMart\Administrator\Model\OrderModel();
            $adm->addLog((int) $order->id, 'telegramcards_refund', [
                'plugin' => $this->_name,
                'amount' => $amount,
                'ok'     => $ok,
                'message'=> $message,
            ]);
        } catch (\Throwable $e) {}
        return ['ok' => $ok, 'message' => $message];
    }

    protected function getProviderChargeId(int $orderId): string
    {
        try {
            $db = $this->db;
            $q = $db->getQuery(true)
                ->select('logs')
                ->from($db->quoteName('#__radicalmart_orders'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $orderId);
            $logsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
            if ($logsJson === '') return '';
            $logs = (new Registry($logsJson))->toArray();
            foreach ($logs as $log) {
                if (!empty($log['action']) && $log['action'] === 'telegram_payment') {
                    if (!empty($log['provider_payment_charge_id'])) {
                        return (string) $log['provider_payment_charge_id'];
                    }
                }
            }
        } catch (\Throwable $e) { return ''; }
        return '';
    }

    protected function getRobokassaInvoiceId(object $order): string
    {
        $src = (string) $this->params->get('rk_invoice_source', 'provider_charge_id');
        if ($src === 'order_number') {
            return (string) ($order->number ?? '');
        }
        return $this->getProviderChargeId((int) $order->id);
    }

    protected function doYooKassaRefund(string $paymentId, float $amount): array
    {
        $shopId = (string) $this->params->get('yk_shop_id', '');
        $secret = (string) $this->params->get('yk_secret_key', '');
        $url    = (string) $this->params->get('yk_refund_url', 'https://api.yookassa.ru/v3/refunds');
        if ($shopId === '' || $secret === '' || $url === '') { return [false, 'Missing YooKassa credentials']; }
        $body = [
            'payment_id' => $paymentId,
            'amount' => [ 'value' => number_format(max(0.0,$amount), 2, '.', ''), 'currency' => 'RUB' ],
        ];
        $http = new Http();
        $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
        $headers = [
            'Content-Type' => 'application/json',
            'Idempotence-Key' => bin2hex(random_bytes(16)),
            'Authorization' => 'Basic ' . base64_encode($shopId . ':' . $secret),
        ];
        try {
            $res = $http->post($url, json_encode($body, JSON_UNESCAPED_UNICODE), $headers);
            $code = (int) ($res->code ?? 0);
            if ($code >= 200 && $code < 300) {
                return [true, 'YooKassa refund accepted'];
            }
            $msg = 'YooKassa refund failed (HTTP ' . $code . ')';
            if (!empty($res->body)) {
                $json = json_decode((string) $res->body, true);
                if (is_array($json) && !empty($json['description'])) { $msg .= ': ' . (string) $json['description']; }
            }
            return [false, $msg];
        } catch (\Throwable $e) { return [false, $e->getMessage()]; }
    }

    protected function doRobokassaRefund(string $invoiceId, float $amount): array
    {
        $url   = (string) $this->params->get('rk_refund_url', '');
        $login = (string) $this->params->get('rk_login', '');
        $pass2 = (string) $this->params->get('rk_password2', '');
        if ($url === '' || $login === '' || $pass2 === '') { return [false, 'Missing Robokassa credentials/refund URL']; }
        $amountStr = number_format(max(0.0,$amount), 2, '.', '');
        // SignatureValue = MD5(MerchantLogin:InvoiceID:Amount:Password2) upper
        $sig = strtoupper(md5($login . ':' . $invoiceId . ':' . $amountStr . ':' . $pass2));
        $payload = [
            'MerchantLogin'  => $login,
            'InvoiceID'      => $invoiceId,
            'Amount'         => $amountStr,
            'SignatureValue' => $sig,
        ];
        $culture = trim((string) $this->params->get('rk_culture', ''));
        if ($culture !== '') { $payload['Culture'] = $culture; }
        $method = strtoupper((string) $this->params->get('rk_http_method', 'GET'));
        $http = new Http();
        $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
        try {
            if ($method === 'GET') {
                $qs = http_build_query($payload);
                $res = $http->get($url . (strpos($url,'?')!==false?'&':'?') . $qs);
            } else {
                $res = $http->post($url, $payload, ['Content-Type' => 'application/x-www-form-urlencoded']);
            }
            $code = (int) ($res->code ?? 0);
            if ($code >= 200 && $code < 300) {
                return [true, 'Robokassa refund accepted'];
            }
            $msg = 'Robokassa refund failed (HTTP ' . $code . ')';
            if (!empty($res->body)) { $msg .= ': ' . (string) $res->body; }
            return [false, $msg];
        } catch (\Throwable $e) { return [false, $e->getMessage()]; }
    }
}
