<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\RadicalMartTelegram\Site\Service\CatalogService;
use Joomla\Component\RadicalMartTelegram\Site\Service\CartService;
use Joomla\Component\RadicalMart\Site\Model\CheckoutModel;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RMUserHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMartTelegram\Site\Service\TelegramClient;
use Joomla\CMS\Language\Text;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as AdminOrderModel;

class ApiController extends BaseController
{
    protected int $tgUserId = 0;
    protected string $tgUsername = '';
    protected function guardInitData(): void
    {
        $app = Factory::getApplication();
        $raw = (string) $app->input->get('tg_init', '', 'raw');
        $params = $app->getParams('com_radicalmart_telegram');
        $strict = (int) $params->get('strict_tg_init', 0) === 1;
        Log::add('guardInitData: raw=' . (strlen($raw) > 0 ? 'present (' . strlen($raw) . ' bytes)' : 'EMPTY') . ', strict=' . ($strict ? 'YES' : 'NO'), Log::DEBUG, 'com_radicalmart.telegram');
        if ($raw === '') {
            if ($strict) {
                Log::add('Missing Telegram initData in strict mode', Log::WARNING, 'com_radicalmart.telegram');
                echo new JsonResponse(null, 'initData required', true);
                $app->close();
            }
            return;
        }
        try {
            $botToken = (string) $params->get('bot_token', '');
            if ($botToken === '') {
                Log::add('Bot token is empty — skip initData verify', Log::WARNING, 'com_radicalmart.telegram');
                return;
            }
            if (!$this->verifyInitData($raw, $botToken)) {
                Log::add('Invalid Telegram initData signature', Log::WARNING, 'com_radicalmart.telegram');
                echo new JsonResponse(null, 'Invalid initData', true);
                $app->close();
            }
            // extract Telegram user id if present
            $pairs = [];
            parse_str($raw, $pairs);
            if (!empty($pairs['user'])) {
                $userObj = json_decode((string) $pairs['user'], true);
                if (is_array($userObj) && !empty($userObj['id'])) {
                    $this->tgUserId = (int) $userObj['id'];
                    if (!empty($userObj['username'])) { $this->tgUsername = (string) $userObj['username']; }
                }
            }
            // If strict mode: validate that provided chat id is mapped to same tg_user_id when mapping exists
            if ($strict) {
                $chat = $this->getChatId();
                if ($chat > 0 && $this->tgUserId > 0) {
                    try {
                        $db = Factory::getContainer()->get('DatabaseDriver');
                        $q = $db->getQuery(true)
                            ->select(['user_id','tg_user_id'])
                            ->from($db->quoteName('#__radicalmart_telegram_users'))
                            ->where($db->quoteName('chat_id') . ' = :chat')
                            ->bind(':chat', $chat);
                        $row = $db->setQuery($q, 0, 1)->loadAssoc();
                        if ($row && !empty($row['tg_user_id']) && (int)$row['tg_user_id'] !== $this->tgUserId) {
                            Log::add('Strict initData mismatch: chat_id not bound to this tg_user_id', Log::WARNING, 'com_radicalmart.telegram');
                            echo new JsonResponse(null, 'Unauthorized chat mapping', true);
                            $app->close();
                        }
                    } catch (\Throwable $e) { /* ignore db errors here */ }
                }
            }
        } catch (\Throwable $e) {
            Log::add('initData verify error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
        }
    }

    protected function guardRateLimit(string $scope, int $maxPerMinute): void
    {
        $app = Factory::getApplication();
        $session = $app->getSession();
        $now = time();
        $key = 'com_radicalmart_telegram.rlm.' . md5($scope);
        $arr = $session->get($key, []);
        if (!is_array($arr)) { $arr = []; }
        $arr = array_values(array_filter($arr, function($t) use ($now) { return is_int($t) && $t > $now - 60; }));
        if (count($arr) >= $maxPerMinute) {
            echo new JsonResponse(null, 'Too many requests', true);
            $app->close();
        }
        $arr[] = $now;
        $session->set($key, $arr);
    }

    protected function rateKey(): string
    {
        $chat = $this->getChatId();
        if ($this->tgUserId > 0) return 'tg:' . $this->tgUserId;
        if ($chat > 0) return 'chat:' . $chat;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return 'ip:' . $ip;
    }

    protected function guardRateLimitDb(string $scope, int $maxPerMinute): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');
        $key = substr($this->rateKey(), 0, 64);
        $now = new \Joomla\CMS\Date\Date();
        $window = new \Joomla\CMS\Date\Date(date('Y-m-d H:i:00', $now->toUnix()));
        $windowSql = $window->toSql();
        try {
            $ins = $db->getQuery(true)
                ->insert($db->quoteName('#__radicalmart_telegram_ratelimits'))
                ->columns([$db->quoteName('scope'), $db->quoteName('rkey'), $db->quoteName('window_start'), $db->quoteName('count')])
                ->values(implode(',', [ $db->quote($scope), $db->quote($key), $db->quote($windowSql), '1' ]))
                ->onDuplicateKeyUpdate([ $db->quoteName('count') . ' = ' . $db->quoteName('count') . ' + 1' ]);
            $db->setQuery($ins)->execute();
            $sel = $db->getQuery(true)
                ->select($db->quoteName('count'))
                ->from($db->quoteName('#__radicalmart_telegram_ratelimits'))
                ->where($db->quoteName('scope') . ' = :s')
                ->where($db->quoteName('rkey') . ' = :k')
                ->where($db->quoteName('window_start') . ' = :w')
                ->bind(':s', $scope)
                ->bind(':k', $key)
                ->bind(':w', $windowSql);
            $cnt = (int) $db->setQuery($sel, 0, 1)->loadResult();
            if ($cnt > $maxPerMinute) {
                echo new JsonResponse(null, 'Too many requests', true);
                $app->close();
            }
        } catch (\Throwable $e) {
            // fallback to session limiter on DB error
            $this->guardRateLimit($scope, $maxPerMinute);
        }
    }

    protected function guardNonce(string $scope): void
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_radicalmart_telegram');
        $strict = (int) $params->get('strict_nonce', 0) === 1;
        $nonce = trim((string) $app->input->get('nonce', '', 'string'));
        if ($nonce === '') {
            if ($strict) {
                echo new JsonResponse(null, 'Nonce required', true);
                $app->close();
            }
            return;
        }
        $chat = $this->getChatId();
        if ($chat <= 0) { return; }
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $row = (object) [
                'chat_id' => $chat,
                'scope'   => substr($scope, 0, 32),
                'nonce'   => substr($nonce, 0, 64),
                'created' => (new \Joomla\CMS\Date\Date())->toSql(),
            ];
            $db->insertObject('#__radicalmart_telegram_nonces', $row);
        } catch (\Throwable $e) {
            // Duplicate => nonce already used
            echo new JsonResponse(null, 'Duplicate request', true);
            $app->close();
        }
    }

    protected function verifyInitData(string $rawInit, string $botToken): bool
    {
        if ($rawInit === '' || $botToken === '' || strlen($rawInit) > 4096) {
            Log::add('verifyInitData FAIL: empty or too long. raw=' . strlen($rawInit) . ', token=' . (strlen($botToken) > 0 ? 'set' : 'empty'), Log::DEBUG, 'com_radicalmart.telegram');
            return false;
        }
        $pairs = [];
        parse_str($rawInit, $pairs);
        if (empty($pairs) || !isset($pairs['hash'])) {
            Log::add('verifyInitData FAIL: no pairs or no hash', Log::DEBUG, 'com_radicalmart.telegram');
            return false;
        }
        $receivedHash = (string) $pairs['hash'];
        unset($pairs['hash']);
        ksort($pairs, SORT_STRING);
        $lines = [];
        foreach ($pairs as $k => $v) {
            if (is_array($v)) {
                Log::add('verifyInitData FAIL: array value in pairs', Log::DEBUG, 'com_radicalmart.telegram');
                return false;
            }
            $lines[] = $k . '=' . (string) $v;
        }
        $dataCheckString = implode("\n", $lines);
        $secretKey = hash_hmac('sha256', 'WebAppData', $botToken, true);
        $calc = hash_hmac('sha256', $dataCheckString, $secretKey);
        $result = hash_equals(strtolower($calc), strtolower($receivedHash));
        Log::add('verifyInitData: ' . ($result ? 'OK' : 'FAIL hash mismatch') . ', received=' . substr($receivedHash, 0, 16) . '..., calc=' . substr($calc, 0, 16) . '...', Log::DEBUG, 'com_radicalmart.telegram');
        return $result;
    }
    protected function getChatId(): int
    {
        return (int) Factory::getApplication()->input->get('chat', 0, 'int');
    }

    public function list(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('list', 60);
        $page = $app->input->getInt('page', 1);
        $lim  = $app->input->getInt('limit', 12);
        $inStock = $app->input->getInt('in_stock', 0) === 1;
        $sort = trim((string) $app->input->get('sort', '', 'string'));
        $priceFrom = trim((string) $app->input->get('price_from', '', 'string'));
        $priceTo   = trim((string) $app->input->get('price_to', '', 'string'));

        $filters = [];
        if ($inStock) { $filters['in_stock'] = 1; }
        if ($sort !== '') { $filters['sort'] = $sort; }
        if ($priceFrom !== '' || $priceTo !== '') { $filters['price'] = ['from'=>$priceFrom, 'to'=>$priceTo]; }
        // Field filters: read configured aliases and pick values from request
        try {
            $params = $app->getParams('com_radicalmart_telegram');
            $cfg = $params->get('filters_fields');
            $fields = [];
            if (!empty($cfg) && is_array($cfg)) {
                foreach ($cfg as $row) {
                    if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                    $alias = isset($row['alias']) ? trim((string)$row['alias']) : '';
                    if ($alias === '') continue;
                    $type = isset($row['type']) ? (string) $row['type'] : 'text';
                    if ($type === 'range') {
                        $from = $app->input->getString('field_' . $alias . '_from', '');
                        $to   = $app->input->getString('field_' . $alias . '_to', '');
                        if ($from !== '' || $to !== '') { $fields[$alias] = ['from' => $from, 'to' => $to]; }
                    } else {
                        // Accept both field_alias and field[alias]
                        $val = $app->input->getString('field_' . $alias, null);
                        if ($val === null) {
                            $arr = $app->input->get('field', [], 'array');
                            if (isset($arr[$alias])) { $val = (string) $arr[$alias]; }
                        }
                        if ($val !== null && $val !== '') { $fields[$alias] = $val; }
                    }
                }
            }
            if (!empty($fields)) { $filters['fields'] = $fields; }
        } catch (\Throwable $e) { /* ignore */ }

        $items = (new CatalogService())->listProducts($page, $lim, $filters);
        echo new JsonResponse(['items' => $items]);
        $app->close();
    }

    public function add(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('add');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        $qty  = (float) $app->input->get('qty', 1, 'float');

        if ($chat <= 0 || $id <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }

        $svc = new CartService();
        $res = $svc->addProduct($chat, $id, $qty);
        if ($res === false) {
            echo new JsonResponse(null, 'Add failed', true);
            $app->close();
        }

        $cart = $res['cart'] ?? null;
        echo new JsonResponse(['cart' => $cart]);
        $app->close();
    }

    public function cart(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('cart', 60);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }

        $svc  = new CartService();
        $cart = $svc->getCart($chat);
        echo new JsonResponse(['cart' => $cart]);
        $app->close();
    }

    public function qty(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('qty');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        $qty  = (float) $app->input->get('qty', 1, 'float');
        if ($chat <= 0 || $id <= 0 || $qty < 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }
        $svc = new CartService();
        $res = $svc->setQuantity($chat, $id, $qty);
        if ($res === false) { echo new JsonResponse(null, 'Update failed', true); $app->close(); }
        echo new JsonResponse(['cart' => $res['cart'] ?? null]);
        $app->close();
    }

    public function remove(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('remove');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        if ($chat <= 0 || $id <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }
        $svc = new CartService();
        $res = $svc->remove($chat, $id);
        if ($res === false) { echo new JsonResponse(null, 'Remove failed', true); $app->close(); }
        echo new JsonResponse(['cart' => $res['cart'] ?? null]);
        $app->close();
    }

    public function checkout(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('checkout', 20);
        $this->guardNonce('checkout');
        $chat = $this->getChatId();
        $op   = $app->input->getString('action', 'create');

        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid chat', true);
            $app->close();
        }

        if ($op !== 'create') {
            echo new JsonResponse(null, 'Unsupported action', true);
            $app->close();
        }

        $first = trim($app->input->getString('first_name', ''));
        $second = trim($app->input->getString('second_name', '')); // отчество
        $last  = trim($app->input->getString('last_name', ''));
        $fallbackName = $app->input->getString('name', '');
        $phone = $app->input->getString('phone', '');
        $email = trim($app->input->getString('email', ''));
        $shippingId = $app->input->getInt('shipping_id', 0);
        $paymentId  = $app->input->getInt('payment_id', 0);

        try {
            // Ensure cart exists
            $cartSvc = new CartService();
            $cart    = $cartSvc->getCart($chat);
            if (!$cart || empty($cart->id)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), 400);
            }

            // Basic validation
            $phone = RMUserHelper::cleanPhone($phone) ?: $phone;
            if (empty($phone)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_REQUIRED'), 400); }
            if (!preg_match('#^\+?7?\d{10,11}$#', $phone)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FORMAT'), 400); }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_FORMAT'), 400); }
            if (empty($first) || empty($last)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_NAME_REQUIRED'), 400); }

            // Resolve or create user
            $db = Factory::getContainer()->get('DatabaseDriver');
            $userId = 0;

            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

            if ($userId <= 0 && !empty($phone)) {
                $found = RMUserHelper::findUser(['phone' => $phone]);
                if ($found && $found->id) {
                    $userId = (int) $found->id;
                }
            }

            if ($userId <= 0) {
                // Create new user from provided contacts (минимально необходимые поля)
                $contacts = [];
                if (!empty($first)) { $contacts['first_name'] = $first; }
                if (!empty($second)) { $contacts['second_name'] = $second; }
                if (!empty($last)) { $contacts['last_name'] = $last; }
                if (!empty($phone)) { $contacts['phone'] = $phone; }
                if (!empty($email)) { $contacts['email'] = $email; }

                $res = RMUserHelper::saveData('com_radicalmart.checkout', 0, $contacts, false);
                if (!$res || empty($res['result']) || empty($res['user']) || empty($res['user']->id)) {
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CREATE_USER'), 500);
                }
                $userId = (int) $res['user']->id;

                // Map Telegram chat to user
                $link = (object) [
                    'chat_id' => $chat,
                    'tg_user_id' => $this->tgUserId ?: null,
                    'user_id' => $userId,
                    'username' => $this->tgUsername ?: '',
                    'phone' => $phone,
                    'locale' => 'ru',
                    'consent_notifications' => 0,
                    'consent_personal' => 0,
                    'created' => (new \Joomla\CMS\Date\Date())->toSql(),
                ];
                try {
                    $db->insertObject('#__radicalmart_telegram_users', $link);
                } catch (\Throwable $e) {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('user_id') . ' = :uid')
                        ->set($db->quoteName('phone') . ' = :ph')
                        ->set($db->quoteName('tg_user_id') . ' = :tg')
                        ->set($db->quoteName('username') . ' = :un')
                        ->where($db->quoteName('chat_id') . ' = :chat')
                        ->bind(':uid', $userId)
                        ->bind(':ph', $phone)
                        ->bind(':tg', $this->tgUserId)
                        ->bind(':un', $this->tgUsername)
                        ->bind(':chat', $chat);
                    $db->setQuery($upd)->execute();
                }
            }

            if ($userId <= 0) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FOR_REG'), 400);
            }

            // Persist chosen shipping/payment in session state for RadicalMart checkout
            // Validate selected shipping/payment via available methods
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            if ($shippingId > 0) {
                $allowed = [];
                if (!empty($item->shippingMethods)) { foreach ($item->shippingMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$shippingId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_SHIPPING_UNAVAILABLE'), 400); }
                $sessionData['shipping']['id'] = $shippingId;
            }
            if ($paymentId > 0) {
                $allowed = [];
                if (!empty($item->paymentMethods)) { foreach ($item->paymentMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$paymentId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PAYMENT_UNAVAILABLE'), 400); }
                $sessionData['payment']['id'] = $paymentId;
            }
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Create order via CheckoutModel
            // Re-init model after session update
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);

            $orderData = [
                'created_by' => $userId,
                'contacts' => [
                    'first_name' => ($first ?: ($fallbackName ?: '')),
                    'second_name' => $second,
                    'last_name' => $last,
                    'phone' => $phone,
                    'email' => $email,
                ],
            ];

            if (!$order = $model->createOrder($orderData)) {
                $errors = $model->getErrors();
                $msg = $errors ? (is_array($errors) ? implode("\n", array_map(fn($e)=> ($e instanceof \Exception)?$e->getMessage():$e, $errors)) : (string) $errors) : 'Ошибка оформления заказа';
                throw new \RuntimeException($msg, 500);
            }

            $number  = $order->number ?? null;
            $orderId = (int) ($order->id ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Заказ создан, но не получен идентификатор', 500);
            }
            $payUrl = rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart&task=payment.pay&order_number=' . urlencode((string) $number);

            // Optionally, send Telegram Payment invoice (cards) if enabled and selected payment is telegram*
            try {
                $params = $app->getParams('com_radicalmart_telegram');
                $cardsEnabled = (int) $params->get('payments_telegram_cards', 1) === 1;
                $provider = (string) $params->get('provider_cards', 'yookassa');
                $env      = (string) $params->get('payments_env', 'test');
                $ptoken   = '';
                if ($provider === 'yookassa') {
                    $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
                } else {
                    $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
                }
                if ($cardsEnabled && $ptoken !== '') {
                    // Try to get order total and currency
                    $model2 = new CheckoutModel();
                    $model2->setState('cart.id', (int) $cart->id);
                    $model2->setState('cart.code', (string) $cart->code);
                    $order2 = $model2->getItem();
                    $paymentPlugin = (!empty($order2->payment) && !empty($order2->payment->plugin)) ? (string) $order2->payment->plugin : '';
                    $isTelegramPayment = ($paymentPlugin !== '' && stripos($paymentPlugin, 'telegram') !== false);
                    if (!$isTelegramPayment) { throw new \RuntimeException('Skip invoice: payment not telegram'); }
                    $currency = (!empty($order2->currency['code'])) ? (string) $order2->currency['code'] : 'RUB';
                    $amountStr = (!empty($order2->total['final_string'])) ? (string) $order2->total['final_string'] : '';
                    $amountMinor = 0;
                    if (!empty($order2->total['final'])) {
                        $amountMinor = (int) round(((float) $order2->total['final']) * 100);
                    } elseif ($amountStr !== '') {
                        $num = preg_replace('#[^0-9\.,]#', '', $amountStr);
                        $num = str_replace(' ', '', $num);
                        $num = str_replace(',', '.', $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }
                    if ($amountMinor > 0) {
                        $title = 'Заказ ' . ($number ?: ('#' . $orderId));
                        $desc  = 'Оплата заказа в магазине';
                        $payload = 'order:' . (string) $number;
                        $tg = new TelegramClient();
                        if ($tg->isConfigured()) {
                            $tg->sendInvoice((int) $chat, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Send chat message with payment link (optional)
            try {
                $tg = new TelegramClient();
                if ($tg->isConfigured()) {
                    $message = 'Заказ ' . ($number ?: ('#' . $orderId)) . " создан.\nПерейдите к оплате по ссылке.";
                    $tg->sendMessage((int) $chat, $message, [
                        'reply_markup' => [
                            'inline_keyboard' => [[
                                [ 'text' => 'Оплатить заказ', 'url' => $payUrl ],
                            ]],
                        ],
                    ]);
                }
            } catch (\Throwable $e) { /* ignore */ }

            echo new JsonResponse([
                'order_id' => $orderId,
                'order_number' => $number,
                'pay_url' => $payUrl,
            ]);
            $app->close();
        }
        catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function methods(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('methods', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(['shipping'=>['methods'=>[],'selected'=>0],'payment'=>['methods'=>[],'selected'=>0]]); $app->close(); }

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            $map = function($list){
                $out=[]; if ($list) { foreach ($list as $m) { $out[] = [ 'id'=>(int)$m->id, 'title'=>(string)$m->title, 'disabled'=>!empty($m->disabled), 'plugin'=> isset($m->plugin) ? (string)$m->plugin : '' ]; } }
                return $out;
            };

            $selectedShipping = (!empty($item->shipping) && !empty($item->shipping->id)) ? (int) $item->shipping->id : 0;
            $selectedPayment  = (!empty($item->payment) && !empty($item->payment->id)) ? (int) $item->payment->id : 0;
            $res = [
                'shipping' => [
                    'selected' => $selectedShipping,
                    'methods'  => $map($item->shippingMethods ?? []),
                ],
                'payment' => [
                    'selected' => $selectedPayment,
                    'methods'  => $map($item->paymentMethods ?? []),
                ],
            ];
            // If chat is not mapped to a user, mark telegram payments disabled
            $hasMap = false;
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chat);
                $hasMap = ((int) $db->setQuery($q, 0, 1)->loadResult()) > 0;
            } catch (\Throwable $e) { $hasMap = false; }
            if ((!$hasMap) && !empty($res['payment']['methods'])) {
                foreach ($res['payment']['methods'] as &$pm) {
                    if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegram') !== false) {
                        $pm['disabled'] = true;
                    }
                }
                unset($pm);
            }
            // Stars method: optionally hide by plugin rules (categories/products)
            try {
                if (!empty($item->products) && !empty($res['payment']['methods'])) {
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select($db->quoteName('params'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'));
                    $paramsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
                    $conf = [];
                    if ($paramsJson !== '') { try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) { $conf = []; } }
                    $parseCsv = function($csv){ $out=[]; foreach (explode(',', (string)$csv) as $v){ $v=trim($v); if ($v!=='' && ctype_digit($v)) $out[]=(int)$v; } return array_values(array_unique($out)); };
                    $allowedCats = $parseCsv($conf['allowed_categories'] ?? '');
                    $excludedCats= $parseCsv($conf['excluded_categories'] ?? '');
                    $allowedProd = $parseCsv($conf['allowed_products'] ?? '');
                    $excludedProd= $parseCsv($conf['excluded_products'] ?? '');
                    $productCats = function($prod){ $ids=[]; if (!empty($prod->category) && !empty($prod->category->id)) $ids[]=(int)$prod->category->id; if (!empty($prod->categories) && is_array($prod->categories)) { foreach ($prod->categories as $c){ if (is_array($c) && !empty($c['id']) && is_numeric($c['id'])) $ids[]=(int)$c['id']; if (is_object($c) && !empty($c->id)) $ids[]=(int)$c->id; } } if (!empty($prod->category_id) && is_numeric($prod->category_id)) $ids[]=(int)$prod->category_id; return array_values(array_unique($ids)); };
                    $isAllowedByCats = function($products) use ($allowedCats,$excludedCats,$productCats){ if (empty($allowedCats)) return true; foreach ($products as $prod){ $ids=$productCats($prod); if (!empty($ids)){ $ok=false; foreach($ids as $cid){ if (in_array($cid,$allowedCats,true)) { $ok=true; break; } } foreach($ids as $cid){ if (in_array($cid,$excludedCats,true)) { $ok=false; break; } } if (!$ok) return false; } } return true; };
                    $isAllowedByProd = function($products) use ($allowedProd,$excludedProd){ if (empty($allowedProd) && empty($excludedProd)) return true; foreach ($products as $prod){ $pid=(int)($prod->id ?? 0); if ($pid<=0) continue; if (!empty($allowedProd) && !in_array($pid,$allowedProd,true)) return false; if (!empty($excludedProd) && in_array($pid,$excludedProd,true)) return false; } return true; };
                    $allowedAll = $isAllowedByCats($item->products) && $isAllowedByProd($item->products);
                    if (!$allowedAll) {
                        foreach ($res['payment']['methods'] as &$pm) {
                            if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegramstars') !== false) {
                                $pm['disabled'] = true;
                            }
                        }
                        unset($pm);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            echo new JsonResponse($res); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function setshipping(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('setshipping');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        if ($chat <= 0 || $id <= 0) { echo new JsonResponse(null, 'Invalid parameters', true); $app->close(); }
        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            // Validate id against available methods first
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $curr  = $model->getItem();
            $allowed = [];
            if (!empty($curr->shippingMethods)) { foreach ($curr->shippingMethods as $m) { $allowed[(int)$m->id] = true; } }
            if (!isset($allowed[$id])) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_SHIPPING_UNAVAILABLE'), true); $app->close(); }

            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $sessionData['shipping']['id'] = $id;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Rebuild item with new shipping
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            $map = function($list){ $out=[]; if ($list) { foreach ($list as $m) { $out[] = [ 'id'=>(int)$m->id, 'title'=>(string)$m->title, 'disabled'=>!empty($m->disabled), 'plugin'=> isset($m->plugin) ? (string)$m->plugin : '' ]; } } return $out; };

            $selectedShipping = (!empty($item->shipping) && !empty($item->shipping->id)) ? (int) $item->shipping->id : 0;
            $selectedPayment  = (!empty($item->payment) && !empty($item->payment->id)) ? (int) $item->payment->id : 0;
            echo new JsonResponse([
                'shipping_id'   => $selectedShipping,
                'payment'       => [ 'selected' => $selectedPayment, 'methods' => $map($item->paymentMethods ?? []) ],
                'shipping_title'=> (!empty($item->shipping) && !empty($item->shipping->title)) ? (string) $item->shipping->title : '',
                'order_total'   => (!empty($item->total) && !empty($item->total['final_string'])) ? (string) $item->total['final_string'] : '',
            ]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function setpayment(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('setpayment');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        if ($chat <= 0 || $id <= 0) { echo new JsonResponse(null, 'Invalid parameters', true); $app->close(); }
        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            // Validate id against available methods first
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $curr  = $model->getItem();
            $allowed = [];
            if (!empty($curr->paymentMethods)) { foreach ($curr->paymentMethods as $m) { $allowed[(int)$m->id] = true; } }
            if (!isset($allowed[$id])) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_PAYMENT_UNAVAILABLE'), true); $app->close(); }

            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $sessionData['payment']['id'] = $id;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Rebuild item with new payment
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            $selectedPayment  = (!empty($item->payment) && !empty($item->payment->id)) ? (int) $item->payment->id : 0;
            echo new JsonResponse([
                'payment_id'    => $selectedPayment,
                'shipping_title'=> (!empty($item->shipping) && !empty($item->shipping->title)) ? (string) $item->shipping->title : '',
                'order_total'   => (!empty($item->total) && !empty($item->total['final_string'])) ? (string) $item->total['final_string'] : '',
            ]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function summary(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('summary', 60);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            // Ensure cart exists
            $cartSvc = new CartService();
            $cart    = $cartSvc->getCart($chat);
            if (!$cart || empty($cart->id)) {
                echo new JsonResponse(['order_total' => '', 'shipping_title' => '', 'payment_title' => '', 'codes' => [], 'points' => 0]);
                $app->close();
            }

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $order = $model->getItem();

            $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
            $paymentTitle  = (!empty($order->payment) && !empty($order->payment->title)) ? (string) $order->payment->title : '';
            $orderTotal    = (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';
            $orderDiscount = (!empty($order->total) && !empty($order->total['discount_string'])) ? (string) $order->total['discount_string'] : '';
            $pvz           = (!empty($order->formData['shipping']['point']) && is_array($order->formData['shipping']['point']))
                ? $order->formData['shipping']['point'] : [];

            // Applied bonuses (codes/points) from formData
            $appliedCodes = [];
            $pointsUsed   = 0;
            if (!empty($order->formData['plugins']['bonuses'])) {
                $b = $order->formData['plugins']['bonuses'];
                if (!empty($b['codes']) && is_array($b['codes'])) {
                    $codes = CodesHelper::getCodes($b['codes']);
                    foreach ($b['codes'] as $cid) {
                        $co = $codes[$cid] ?? null;
                        // Compute discount per code from products
                        $amount = 0.0; $amountStr = '';
                        if (!empty($order->products)) {
                            foreach ($order->products as $prod) {
                                if (!empty($prod->order['plugins']['bonuses'])) {
                                    $plug = $prod->order['plugins']['bonuses'];
                                    $key  = 'discount_code_' . (int) $cid;
                                    if (isset($plug[$key])) {
                                        $unit = (float) $plug[$key];
                                        $qty  = (float) ($prod->order['quantity'] ?? 1);
                                        $amount += ($unit * $qty);
                                    }
                                }
                            }
                        }
                        try {
                            $curCode = !empty($order->currency['code']) ? $order->currency['code'] : '';
                            if ($curCode !== '') {
                                $amountStr = \Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper::toString($amount, $curCode);
                            } else {
                                $amountStr = (string) $amount;
                            }
                        } catch (\Throwable $e) { $amountStr = (string) $amount; }

                        if ($co) { $appliedCodes[] = ['id' => (int) $co->id, 'code' => (string) $co->code, 'amount' => $amountStr]; }
                        else { $appliedCodes[] = ['id' => (int) $cid, 'code' => (string) $cid, 'amount' => $amountStr]; }
                    }
                }
                if (isset($b['points'])) { $pointsUsed = (float) $b['points']; }
            }

            echo new JsonResponse([
                'order_total'    => $orderTotal,
                'shipping_title' => $shippingTitle,
                'payment_title'  => $paymentTitle,
                'codes'          => $appliedCodes,
                'points'         => $pointsUsed,
                'discount'       => $orderDiscount,
                'pvz'            => $pvz,
            ]);
            $app->close();
        }
        catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function promocode(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $this->guardNonce('promocode');
        $chat = $this->getChatId();
        $op   = $app->input->getString('action', 'add');
        $code = trim($app->input->getString('code', ''));
        $id   = $app->input->getInt('id', 0);
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if (empty($sessionData['plugins']['bonuses'])) { $sessionData['plugins']['bonuses'] = []; }
            if (empty($sessionData['plugins']['bonuses']['codes'])) { $sessionData['plugins']['bonuses']['codes'] = []; }

            if ($op === 'remove') {
                $targetId = $id;
                if (!$targetId && $code !== '') {
                    $found = CodesHelper::find($code);
                    $targetId = $found ? (int) $found->id : 0;
                }
                if ($targetId) {
                    $sessionData['plugins']['bonuses']['codes'] = array_values(array_filter(
                        $sessionData['plugins']['bonuses']['codes'], fn($c) => (int)$c !== (int)$targetId
                    ));
                }
            } else {
                if ($code === '') { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_REQUIRED'), true); $app->close(); }
                $found = CodesHelper::find($code);
                if ($found === false) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_NOT_FOUND'), true); $app->close(); }
                $cid = (int) $found->id;
                if (!in_array($cid, $sessionData['plugins']['bonuses']['codes'])) {
                    $sessionData['plugins']['bonuses']['codes'][] = $cid;
                }
            }

            // trigger recalculation
            $sessionData['plugins']['bonuses']['recalculate'] = 1;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Recompute
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $order = $model->getItem();

            // Build response via summary structure
            $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
            $paymentTitle  = (!empty($order->payment) && !empty($order->payment->title)) ? (string) $order->payment->title : '';
            $orderTotal    = (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';
            $appliedCodes  = [];
            if (!empty($order->formData['plugins']['bonuses']['codes'])) {
                $codes = CodesHelper::getCodes($order->formData['plugins']['bonuses']['codes']);
                foreach ($order->formData['plugins']['bonuses']['codes'] as $cid) {
                    $co = $codes[$cid] ?? null;
                    if ($co) { $appliedCodes[] = ['id' => (int) $co->id, 'code' => (string) $co->code]; }
                }
            }
            echo new JsonResponse([
                'order_total'    => $orderTotal,
                'shipping_title' => $shippingTitle,
                'payment_title'  => $paymentTitle,
                'codes'          => $appliedCodes,
            ]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function points(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $this->guardNonce('points');
        $chat = $this->getChatId();
        $pts  = (float) $app->input->get('points', 0, 'float');
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            if ($pts < 0) { $pts = 0; }
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if (empty($sessionData['plugins']['bonuses'])) { $sessionData['plugins']['bonuses'] = []; }
            $sessionData['plugins']['bonuses']['points'] = $pts;
            $sessionData['plugins']['bonuses']['recalculate'] = 1;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $order = $model->getItem();

            $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
            $paymentTitle  = (!empty($order->payment) && !empty($order->payment->title)) ? (string) $order->payment->title : '';
            $orderTotal    = (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';
            $pointsUsed    = (float) ($order->formData['plugins']['bonuses']['points'] ?? 0);

            echo new JsonResponse([
                'order_total'    => $orderTotal,
                'shipping_title' => $shippingTitle,
                'payment_title'  => $paymentTitle,
                'points'         => $pointsUsed,
            ]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function bonuses(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('bon', 20);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            // Map chat to user
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

            $available = 0.0;
            if ($userId > 0) {
                $available = (float) PointsHelper::getCustomerPoints($userId);
            }

            echo new JsonResponse(['points_available' => $available]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function setpvz(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $this->guardNonce('setpvz');
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        $shippingId = $app->input->getInt('shipping_id', 0);
        $provider   = $app->input->getString('provider', '');
        $extId      = $app->input->getString('id', '');
        $title      = $app->input->getString('title', '');
        $address    = $app->input->getString('address', '');
        $lat        = (float) $app->input->get('lat', 0, 'float');
        $lon        = (float) $app->input->get('lon', 0, 'float');

        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if ($shippingId > 0) { $sessionData['shipping']['id'] = $shippingId; }
            $sessionData['shipping']['point'] = [
                'id'        => $extId,
                'provider'  => $provider,
                'title'     => $title,
                'address'   => $address,
                'latitude'  => $lat,
                'longitude' => $lon,
            ];
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $order = $model->getItem();

            $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
            $orderTotal    = (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';

            echo new JsonResponse([
                'shipping_title' => $shippingTitle,
                'order_total'    => $orderTotal,
                'pvz'            => $sessionData['shipping']['point'],
            ]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function apishipfetch(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('adminfetch', 5);
        $chat = $this->getChatId(); // optional
        try {
            $params    = $app->getParams('com_radicalmart_telegram');
            $token     = (string) $params->get('apiship_api_key', '');
            $providers = $app->input->getString('providers', (string) $params->get('apiship_providers', 'yataxi,cdek,x5'));
            $providers = array_filter(array_map('trim', explode(',', $providers)));
            $operation = ['giveout'];
            if (empty($token)) { echo new JsonResponse(null, 'Missing ApiShip token', true); $app->close(); }
            $db = Factory::getContainer()->get('DatabaseDriver');
            $outDir = JPATH_ROOT . '/media/com_radicalmart_telegram/apiship';
            if (!Folder::exists($outDir)) Folder::create($outDir);
            $result = [];
            $logsEnabled = (int) $params->get('logs_enabled', 1) === 1;
            foreach ($providers as $prov) {
                $total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
                $rows  = [];
                $limit = 500; $offset = 0;
                while ($offset < $total) {
                    $chunk = ApiShipHelper::getPoints($token, [$prov], $operation, $offset, $limit);
                    if (!$chunk) break;
                    foreach ($chunk as $row) {
                        $rows[] = $row;
                        $extId   = (string) ($row['id'] ?? ($row['externalId'] ?? ''));
                        $title   = (string) ($row['title'] ?? ($row['name'] ?? ''));
                        $address = (string) ($row['address'] ?? '');
                        $lat     = isset($row['latitude']) ? (float)$row['latitude'] : (isset($row['location']['latitude']) ? (float)$row['location']['latitude'] : null);
                        $lon     = isset($row['longitude']) ? (float)$row['longitude'] : (isset($row['location']['longitude']) ? (float)$row['location']['longitude'] : null);
                        if ($lat === null || $lon === null || $extId === '') continue;
                        $meta    = json_encode($row, JSON_UNESCAPED_UNICODE);
                        $q = $db->getQuery(true)
                            ->insert($db->quoteName('#__radicalmart_apiship_points'))
                            ->columns([
                                $db->quoteName('provider'), $db->quoteName('ext_id'), $db->quoteName('title'), $db->quoteName('address'),
                                $db->quoteName('lat'), $db->quoteName('lon'), $db->quoteName('operation'), $db->quoteName('point'),
                                $db->quoteName('meta'), $db->quoteName('updated_at')
                            ])
                            ->values(implode(',', [
                                $db->quote($prov), $db->quote($extId), $db->quote($title), $db->quote($address),
                                (string)$lat, (string)$lon, $db->quote('giveout'),
                                "ST_GeomFromText('POINT(" . (string)$lon . " " . (string)$lat . ")', 4326)",
                                $db->quote($meta), $db->quote((new \Joomla\CMS\Date\Date())->toSql())
                            ]))
                            ->onDuplicateKeyUpdate([
                                $db->quoteName('title') . ' = VALUES(' . $db->quoteName('title') . ')',
                                $db->quoteName('address') . ' = VALUES(' . $db->quoteName('address') . ')',
                                $db->quoteName('lat') . ' = VALUES(' . $db->quoteName('lat') . ')',
                                $db->quoteName('lon') . ' = VALUES(' . $db->quoteName('lon') . ')',
                                $db->quoteName('point') . ' = VALUES(' . $db->quoteName('point') . ')',
                                $db->quoteName('meta') . ' = VALUES(' . $db->quoteName('meta') . ')',
                                $db->quoteName('updated_at') . ' = VALUES(' . $db->quoteName('updated_at') . ')',
                            ]);
                        $db->setQuery($q)->execute();
                    }
                    $offset += $limit;
                }
                $file = $outDir . '/points-' . $prov . '.json';
                File::write($file, json_encode(['provider' => $prov, 'total' => $total, 'rows' => $rows], JSON_UNESCAPED_UNICODE));
                // update meta
                $mq = $db->getQuery(true)
                    ->insert($db->quoteName('#__radicalmart_apiship_meta'))
                    ->columns([$db->quoteName('provider'), $db->quoteName('last_fetch'), $db->quoteName('last_total')])
                    ->values(implode(',', [ $db->quote($prov), $db->quote((new \Joomla\CMS\Date\Date())->toSql()), (string)$total ]))
                    ->onDuplicateKeyUpdate([
                        $db->quoteName('last_fetch') . ' = VALUES(' . $db->quoteName('last_fetch') . ')',
                        $db->quoteName('last_total') . ' = VALUES(' . $db->quoteName('last_total') . ')',
                    ]);
                $db->setQuery($mq)->execute();

                if ($logsEnabled) { \Joomla\CMS\Log\Log::add('ApiShip provider ' . $prov . ' fetched: total=' . $total, \Joomla\CMS\Log\Log::INFO, 'com_radicalmart.telegram'); }
                $result[] = ['provider' => $prov, 'total' => $total, 'saved' => basename($file), 'db' => 'ok'];
            }

            echo new JsonResponse(['providers' => $result]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function pvz(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('pvz', 20);
        $bbox = $app->input->getString('bbox', ''); // lon1,lat1,lon2,lat2
        $prov = $app->input->getString('providers', '');
        $limit= $app->input->getInt('limit', 1000);
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $params = $app->getParams('com_radicalmart_telegram');
            $allowedDefault = array_filter(array_map('trim', explode(',', (string) $params->get('apiship_providers', 'yataxi,cdek,x5'))));
            $providersIn = array_filter(array_map('trim', explode(',', $prov)));
            // sanitize providers against allowed list
            $providers = !empty($providersIn) ? array_values(array_intersect($providersIn, $allowedDefault)) : $allowedDefault;
            // clamp limit
            $limit = max(1, min((int) $limit, 2000));
            $coords = array_map('floatval', explode(',', $bbox));
            $hasB  = (count($coords) === 4);
            $minLon= $hasB ? min($coords[0], $coords[2]) : null;
            $maxLon= $hasB ? max($coords[0], $coords[2]) : null;
            $minLat= $hasB ? min($coords[1], $coords[3]) : null;
            $maxLat= $hasB ? max($coords[1], $coords[3]) : null;
            // Try cache
            $items = null;
            $cacheEnabled = (int) $params->get('pvz_cache_enabled', 1) === 1;
            $ttlSeconds   = (int) $params->get('pvz_cache_ttl', 60);
            $precision    = max(0, min(4, (int) $params->get('pvz_cache_precision', 2)));
            $cacheDir     = JPATH_ROOT . '/media/com_radicalmart_telegram/apiship/cache';
            if ($cacheEnabled && $ttlSeconds > 0 && $hasB) {
                $step = pow(10, -$precision);
                $norm = function($v,$s){ return number_format(round($v / $s) * $s, 6, '.', ''); };
                $nb = [ $norm($minLon,$step), $norm($minLat,$step), $norm($maxLon,$step), $norm($maxLat,$step) ];
                $provKey = $providers; sort($provKey); $provKey = implode(',', $provKey);
                $key = sha1($provKey . '|' . implode(',', $nb) . '|' . (string)$limit);
                $file = $cacheDir . '/pvz-' . $key . '.json';
                if (\Joomla\CMS\Filesystem\File::exists($file)) {
                    $raw = @file_get_contents($file);
                    if ($raw) {
                        $json = json_decode($raw, true);
                        if (is_array($json) && isset($json['expires']) && (time() < (int)$json['expires']) && isset($json['items']) && is_array($json['items'])) {
                            $items = $json['items'];
                        }
                    }
                }
            }
            $where = [];
            if (!empty($providers)) {
                $where[] = $db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $providers)) . ')';
            }
            if ($hasB) {
                $poly = sprintf('POLYGON((%f %f,%f %f,%f %f,%f %f,%f %f))', $minLon, $minLat, $maxLon, $minLat, $maxLon, $maxLat, $minLon, $maxLat, $minLon, $minLat);
                $where[] = 'MBRWithin(' . $db->quoteName('point') . ', ST_GeomFromText(' . $db->quote($poly) . ', 4326))';
            } else {
                // No bbox provided — return empty set to avoid heavy queries
                echo new JsonResponse(['items' => []]); $app->close();
            }
            if ($items === null) {
                $showPostamats = (int) $params->get('show_postamats', 1) === 1;

                $query = $db->getQuery(true)
                    ->select([$db->quoteName('provider'), $db->quoteName('ext_id'), $db->quoteName('title'), $db->quoteName('address'), $db->quoteName('lat'), $db->quoteName('lon'), $db->quoteName('pvz_type')])
                    ->from($db->quoteName('#__radicalmart_apiship_points'))
                    ->where($db->quoteName('operation') . ' = ' . $db->quote('giveout'))
                    ->order($db->quoteName('provider') . ' ASC');

                if (!$showPostamats) {
                    $query->where($db->quoteName('pvz_type') . ' = ' . $db->quote('1'));
                }

                if (!empty($where)) { $query->where(implode(' AND ', $where)); }
                $db->setQuery($query, 0, $limit);
                $rows = $db->loadAssocList();
                $items = array_map(function($r){ return [
                    'id' => $r['ext_id'],
                    'provider' => $r['provider'],
                    'title' => $r['title'],
                    'address' => $r['address'],
                    'lat' => (float)$r['lat'], 'lon' => (float)$r['lon'],
                ]; }, $rows ?: []);
                // write cache
                if ($cacheEnabled && $ttlSeconds > 0) {
                    if (!\Joomla\CMS\Filesystem\Folder::exists($cacheDir)) { \Joomla\CMS\Filesystem\Folder::create($cacheDir); }
                    if (!isset($key)) {
                        $provKey = $providers; sort($provKey); $provKey = implode(',', $provKey);
                        $key = sha1($provKey . '|' . implode(',', [$minLon,$minLat,$maxLon,$maxLat]) . '|' . (string)$limit);
                    }
                    $file = $cacheDir . '/pvz-' . $key . '.json';
                    $payload = json_encode(['expires' => time() + $ttlSeconds, 'items' => $items], JSON_UNESCAPED_UNICODE);
                    @file_put_contents($file, $payload);
                }
            }

            echo new JsonResponse(['items' => $items]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function orders(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('orders', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q  = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $uid = (int) $db->setQuery($q, 0, 1)->loadResult();
            if ($uid <= 0) { echo new JsonResponse(['items'=>[], 'has_more'=>false, 'page'=>1]); $app->close(); }
            $page  = max(1, (int) $app->input->getInt('page', 1));
            $limit = min(50, max(1, (int) $app->input->getInt('limit', 10)));
            $status = trim((string) $app->input->get('status', '', 'string'));
            $q2 = $db->getQuery(true)
                ->select(['id','number'])
                ->from($db->quoteName('#__radicalmart_orders'))
                ->where($db->quoteName('created_by') . ' = :uid')
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('id') . ' DESC')
                ->bind(':uid', $uid);
            if ($status !== '' && ctype_digit($status)) {
                $q2->where($db->quoteName('status') . ' = :st')->bind(':st', (int) $status);
            }
            $offset = ($page - 1) * $limit;
            $db->setQuery($q2, $offset, $limit + 1);
            $rows = $db->loadAssocList() ?: [];
            $hasMore = false;
            if (count($rows) > $limit) { $hasMore = true; array_pop($rows); }
            $out = [];
            $omodel = new AdminOrderModel();
            foreach ($rows as $r) {
                $order = $omodel->getItem((int)$r['id']);
                if (!$order || empty($order->id)) continue;
                $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
                $canResend = ($plugin !== '' && stripos($plugin, 'telegram') !== false);
                $out[] = [
                    'id'     => (int) $order->id,
                    'number' => (string) ($order->number ?? ''),
                    'status' => (!empty($order->status) && !empty($order->status->title)) ? (string) $order->status->title : '',
                    'total'  => (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '',
                    'pay_url'=> rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart&task=payment.pay&order_number=' . urlencode((string) ($order->number ?? '')),
                    'created'=> (string) ($order->created ?? ''),
                    'payment_plugin' => $plugin,
                    'can_resend' => $canResend,
                ];
            }
            // Load statuses list (optional)
            $statuses = [];
            try {
                $qs = $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName('title')])
                    ->from($db->quoteName('#__radicalmart_statuses'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order($db->quoteName('ordering') . ' ASC');
                $statuses = $db->setQuery($qs)->loadAssocList() ?: [];
            } catch (\Throwable $e) { $statuses = []; }

            echo new JsonResponse(['items'=>$out, 'has_more'=>$hasMore, 'page'=>$page, 'statuses'=>$statuses]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function invoice(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('invoice', 10);
        $this->guardNonce('invoice');
        $chat = $this->getChatId();
        $number = trim((string) $app->input->getString('number', ''));
        if ($chat <= 0 || $number === '') { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        try {
            // Load order by number
            $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
            $order = $pm->getOrder($number, 'number');
            if (!$order || empty($order->id)) { echo new JsonResponse(null, 'Order not found', true); $app->close(); }
            // Check it's telegram payment
            $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
            if ($plugin === '' || stripos($plugin, 'telegram') === false) { echo new JsonResponse(null, 'Payment method is not Telegram', true); $app->close(); }
            // Resolve provider token from component settings (for cards)
            $params = $app->getParams('com_radicalmart_telegram');
            $provider = (string) $params->get('provider_cards', 'yookassa');
            $env      = (string) $params->get('payments_env', 'test');
            $ptoken   = '';
            if (stripos($plugin, 'telegramstars') === false) {
                if ($provider === 'yookassa') {
                    $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
                } else {
                    $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
                }
                if ($ptoken === '') { echo new JsonResponse(null, 'Missing provider token', true); $app->close(); }
            }
            // Compute amount and currency from AdminOrderModel
            $adm = new AdminOrderModel();
            $ord = $adm->getItem((int) $order->id);
            if (!$ord || empty($ord->id)) { echo new JsonResponse(null, 'Order not found', true); $app->close(); }
            $title = 'Заказ ' . ($ord->number ?? ('#' . (int) $ord->id));
            $desc  = 'Оплата заказа в магазине';
            $payload = 'order:' . (string) ($ord->number ?? (string) $ord->id);
            $tg = new TelegramClient();
            if ($tg->isConfigured()) {
                if (stripos($plugin, 'telegramstars') !== false) {
                    // Convert RUB to stars
                    $rub = 0.0;
                    if (!empty($ord->total['final'])) { $rub = (float) $ord->total['final']; }
                    elseif (!empty($ord->total['final_string'])) {
                        $num = preg_replace('#[^0-9\.,]#', '', (string) $ord->total['final_string']);
                        $num = str_replace(' ', '', $num); $num = str_replace(',', '.', $num); $rub = (float) $num;
                    }
                    // Read stars plugin params
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select($db->quoteName('params'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'));
                    $paramsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
                    $conf = [];
                    if ($paramsJson !== '') { try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) { $conf = []; } }
                    $rubPerStar = isset($conf['rub_per_star']) ? (float) $conf['rub_per_star'] : 1.0;
                    $percent = isset($conf['conversion_percent']) ? (float) $conf['conversion_percent'] : 0.0;
                    if ($rubPerStar <= 0) { $rubPerStar = 1.0; }
                    $rub = $rub * (1.0 + ($percent/100.0));
                    $stars = (int) round($rub / $rubPerStar);
                    if ($stars <= 0) { echo new JsonResponse(null, 'Bad amount', true); $app->close(); }
                    $tg->sendInvoice((int) $chat, $title, $desc, $payload, '', 'XTR', $stars, []);
                } else {
                    $currency = (!empty($ord->currency['code'])) ? (string) $ord->currency['code'] : 'RUB';
                    $amountMinor = 0;
                    if (!empty($ord->total['final'])) {
                        $amountMinor = (int) round(((float) $ord->total['final']) * 100);
                    } elseif (!empty($ord->total['final_string'])) {
                        $num = preg_replace('#[^0-9\.,]#', '', (string) $ord->total['final_string']);
                        $num = str_replace(' ', '', $num); $num = str_replace(',', '.', $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }
                    if ($amountMinor <= 0) { echo new JsonResponse(null, 'Bad amount', true); $app->close(); }
                    $tg->sendInvoice((int) $chat, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
                }
            }
            echo new JsonResponse(['ok' => true]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }
}
