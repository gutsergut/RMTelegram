<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис оформления заказа - getMethods(), setPvz(), getTariffs(), setPayment(), createOrder()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMart\Site\Model\CheckoutModel;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RMUserHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Extension\ApiShip;

class CheckoutService
{
    public function getMethods(int $chatId): array
    {
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            return ['shipping' => ['methods' => [], 'selected' => 0], 'payment' => ['methods' => [], 'selected' => 0]];
        }
        $model = new CheckoutModel();
        $model->setState('cart.id', (int) $cart->id);
        $model->setState('cart.code', (string) $cart->code);
        $item = $model->getItem();
        $shippingMethods = $this->mapShippingMethods($item->shippingMethods ?? []);
        $paymentMethods = $this->mapPaymentMethods($item->paymentMethods ?? []);
        $selectedShipping = (!empty($item->shipping) && !empty($item->shipping->id)) ? (int) $item->shipping->id : 0;
        $selectedPayment = (!empty($item->payment) && !empty($item->payment->id)) ? (int) $item->payment->id : 0;
        $res = [
            'shipping' => ['selected' => $selectedShipping, 'methods' => $shippingMethods],
            'payment' => ['selected' => $selectedPayment, 'methods' => $paymentMethods]
        ];
        $hasMap = $this->chatHasUserMapping($chatId);
        if (!$hasMap && !empty($res['payment']['methods'])) {
            foreach ($res['payment']['methods'] as &$pm) {
                if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegram') !== false) {
                    $pm['disabled'] = true;
                }
            }
            unset($pm);
        }
        $res = $this->applyStarsRestrictions($res, $item);
        return $res;
    }

    public function setPvz(int $chatId, array $pvzData, int $shippingId = 0, string $tariffId = ''): array
    {
        $app = Factory::getApplication();
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'));
        }

        Log::add("[setPvz] Cart found: id={$cart->id}, products=" . count($cart->products ?? []), Log::DEBUG, 'com_radicalmart.telegram');

        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        if ($shippingId > 0) {
            $sessionData['shipping']['id'] = $shippingId;
        }
        $sessionData['shipping']['point'] = [
            'id' => $pvzData['id'] ?? '',
            'provider' => $pvzData['provider'] ?? '',
            'title' => $pvzData['title'] ?? '',
            'address' => $pvzData['address'] ?? '',
            'latitude' => (float)($pvzData['lat'] ?? 0),
            'longitude' => (float)($pvzData['lon'] ?? 0),
        ];

        $tariffs = [];
        $selectedTariff = null;
        $tariffDebug = null;

        if ($shippingId > 0 && class_exists(ApiShip::class)) {
            Log::add("[setPvz] Calling calculateTariff for provider={$pvzData['provider']}, pointId={$pvzData['id']}", Log::DEBUG, 'com_radicalmart.telegram');
            $tariffResult = ApiShipIntegrationHelper::calculateTariff($shippingId, $cart, $pvzData['id'] ?? '', $pvzData['provider'] ?? '');
            $tariffDebug = $tariffResult['__debug'] ?? null;
            $tariffs = $tariffResult['tariffs'] ?? [];

            Log::add("[setPvz] calculateTariff returned " . count($tariffs) . " tariffs", Log::DEBUG, 'com_radicalmart.telegram');

            if (!empty($tariffs)) {
                if (!empty($tariffId)) {
                    foreach ($tariffs as $t) {
                        if ((string)$t->tariffId === $tariffId) {
                            $selectedTariff = $t;
                            break;
                        }
                    }
                }
                if (!$selectedTariff && count($tariffs) === 1) {
                    $selectedTariff = $tariffs[0];
                }
                if ($selectedTariff) {
                    $deliveryCost = (float)($selectedTariff->deliveryCost ?? 0);
                    $sessionData['shipping']['tariff'] = [
                        'id' => (int)$selectedTariff->tariffId,
                        'name' => $selectedTariff->tariffName,
                        'hash' => '',
                        'deliveryCost' => $deliveryCost,
                        'daysMin' => (int)($selectedTariff->daysMin ?? 0),
                        'daysMax' => (int)($selectedTariff->daysMax ?? 0),
                    ];
                    $sessionData['shipping']['price'] = ['base' => $deliveryCost];
                    Log::add("[setPvz] Selected tariff: id={$selectedTariff->tariffId}, name={$selectedTariff->tariffName}, cost={$deliveryCost}", Log::DEBUG, 'com_radicalmart.telegram');
                } else {
                    unset($sessionData['shipping']['tariff']);
                    Log::add("[setPvz] No tariff selected (multiple available)", Log::DEBUG, 'com_radicalmart.telegram');
                }
            } else {
                Log::add("[setPvz] No tariffs returned from calculateTariff", Log::WARNING, 'com_radicalmart.telegram');
            }
        }

        $app->setUserState('com_radicalmart.checkout.data', $sessionData);

        $model = new CheckoutModel();
        $model->setState('cart.id', (int) $cart->id);
        $model->setState('cart.code', (string) $cart->code);
        $order = $model->getItem();
        $orderData = $this->buildOrderData($order, $selectedTariff);

        $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
        $orderTotal = $orderData['total']['final_string'] ?? '';

        Log::add("[setPvz] OUTPUT: shippingTitle=$shippingTitle, orderTotal=$orderTotal, tariffs=" . count($tariffs), Log::DEBUG, 'com_radicalmart.telegram');

        return [
            'shipping_title' => $shippingTitle,
            'order_total' => $orderTotal,
            'order' => $orderData,
            'pvz' => $sessionData['shipping']['point'],
            'tariffs' => $tariffs,
            'selected_tariff' => $selectedTariff ? $selectedTariff->tariffId : null,
            '_debug_tariff' => $tariffDebug,
            '_debug_session' => [
                'tariff_id' => $sessionData['shipping']['tariff']['id'] ?? null,
                'tariff_name' => $sessionData['shipping']['tariff']['name'] ?? null,
                'price_base' => $sessionData['shipping']['price']['base'] ?? null,
            ]
        ];
    }

    /**
     * Create order from checkout data
     */
    public function createOrder(int $chatId, array $contacts, int $shippingId = 0, int $paymentId = 0, ?int $tgUserId = null, ?string $tgUsername = null): array
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Check consents
        $cons = ConsentHelper::getConsents($chatId);
        if (empty($cons['personal_data']) || empty($cons['terms'])) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CONSENT_REQUIRED'));
        }

        // Get cart
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'));
        }

        // Validate contacts
        $phone = RMUserHelper::cleanPhone($contacts['phone'] ?? '') ?: ($contacts['phone'] ?? '');
        $first = trim($contacts['first_name'] ?? '');
        $second = trim($contacts['second_name'] ?? '');
        $last = trim($contacts['last_name'] ?? '');
        $email = trim($contacts['email'] ?? '');

        if (empty($phone)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_REQUIRED'));
        }
        if (!preg_match('#^\+?7?\d{10,11}$#', $phone)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FORMAT'));
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_FORMAT'));
        }
        if (empty($first) || empty($last)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_NAME_REQUIRED'));
        }

        // Resolve or create user
        $userId = $this->resolveOrCreateUser($chatId, $phone, $first, $second, $last, $email, $tgUserId, $tgUsername);
        if ($userId <= 0) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FOR_REG'));
        }

        // Validate and set shipping/payment in session
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        $model = new CheckoutModel();
        $model->setState('cart.id', (int) $cart->id);
        $model->setState('cart.code', (string) $cart->code);
        $item = $model->getItem();

        if ($shippingId > 0) {
            $allowed = [];
            if (!empty($item->shippingMethods)) {
                foreach ($item->shippingMethods as $m) {
                    $allowed[(int)$m->id] = true;
                }
            }
            if (!isset($allowed[$shippingId])) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_SHIPPING_UNAVAILABLE'));
            }
            $sessionData['shipping']['id'] = $shippingId;
        }

        if ($paymentId > 0) {
            $allowed = [];
            if (!empty($item->paymentMethods)) {
                foreach ($item->paymentMethods as $m) {
                    $allowed[(int)$m->id] = true;
                }
            }
            if (!isset($allowed[$paymentId])) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PAYMENT_UNAVAILABLE'));
            }
            $sessionData['payment']['id'] = $paymentId;
        }

        $app->setUserState('com_radicalmart.checkout.data', $sessionData);

        // Create order
        $model = new CheckoutModel();
        $model->setState('cart.id', (int) $cart->id);
        $model->setState('cart.code', (string) $cart->code);

        $orderData = [
            'created_by' => $userId,
            'contacts' => [
                'first_name' => $first,
                'second_name' => $second,
                'last_name' => $last,
                'phone' => $phone,
                'email' => $email,
            ],
        ];

        $order = $model->createOrder($orderData);
        if (!$order) {
            $errors = $model->getErrors();
            $msg = $errors ? (is_array($errors) ? implode("\n", array_map(fn($e) => ($e instanceof \Exception) ? $e->getMessage() : $e, $errors)) : (string) $errors) : 'Ошибка оформления заказа';
            throw new \RuntimeException($msg);
        }

        $number = $order->number ?? null;
        $orderId = (int) ($order->id ?? 0);
        if (!$orderId || empty($number)) {
            throw new \RuntimeException('Заказ создан, но не получен идентификатор или номер');
        }

        // Generate payment URL
        $rmParams = \Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper::getComponentParams();
        $paymentEntry = $rmParams->get('payment_entry', 'radicalmart_payment');
        $payUrl = rtrim(Uri::root(), '/') . '/' . $paymentEntry . '/pay/' . urlencode((string) $number);

        // Send Telegram notifications
        $this->sendOrderNotifications($chatId, $orderId, $number, $payUrl, $cart);

        return [
            'order_id' => $orderId,
            'order_number' => $number,
            'pay_url' => $payUrl,
        ];
    }

    /**
     * Resolve existing user or create new one
     */
    private function resolveOrCreateUser(int $chatId, string $phone, string $first, string $second, string $last, string $email, ?int $tgUserId, ?string $tgUsername): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Check if chat is already linked
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

        // Try to find by phone
        if ($userId <= 0 && !empty($phone)) {
            $found = RMUserHelper::findUser(['phone' => $phone]);
            if ($found && $found->id) {
                $userId = (int) $found->id;
            }
        }

        // Create new user if not found
        if ($userId <= 0) {
            $contacts = array_filter([
                'first_name' => $first,
                'second_name' => $second,
                'last_name' => $last,
                'phone' => $phone,
                'email' => $email,
            ]);

            $res = RMUserHelper::saveData('com_radicalmart.checkout', 0, $contacts, false);
            if (!$res || empty($res['result']) || empty($res['user']) || empty($res['user']->id)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CREATE_USER'));
            }
            $userId = (int) $res['user']->id;

            // Link Telegram chat to user
            $this->linkTelegramUser($chatId, $userId, $phone, $tgUserId, $tgUsername);
        }

        return $userId;
    }

    /**
     * Link Telegram chat to user
     */
    private function linkTelegramUser(int $chatId, int $userId, string $phone, ?int $tgUserId, ?string $tgUsername): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $link = (object) [
            'chat_id' => $chatId,
            'tg_user_id' => $tgUserId,
            'user_id' => $userId,
            'username' => $tgUsername ?? '',
            'phone' => $phone,
            'locale' => 'ru',
            'consent_notifications' => 0,
            'consent_personal' => 0,
            'created' => (new \Joomla\CMS\Date\Date())->toSql(),
        ];

        try {
            $db->insertObject('#__radicalmart_telegram_users', $link);
        } catch (\Throwable $e) {
            // Update existing record
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__radicalmart_telegram_users'))
                ->set($db->quoteName('user_id') . ' = :uid')
                ->set($db->quoteName('phone') . ' = :ph')
                ->set($db->quoteName('tg_user_id') . ' = :tg')
                ->set($db->quoteName('username') . ' = :un')
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':uid', $userId)
                ->bind(':ph', $phone)
                ->bind(':tg', $tgUserId)
                ->bind(':un', $tgUsername)
                ->bind(':chat', $chatId);
            $db->setQuery($upd)->execute();
        }
    }

    /**
     * Send Telegram notifications for created order
     */
    private function sendOrderNotifications(int $chatId, int $orderId, string $number, string $payUrl, $cart): void
    {
        $app = Factory::getApplication();

        try {
            $params = $app->getParams('com_radicalmart_telegram');
            $cardsEnabled = (int) $params->get('payments_telegram_cards', 1) === 1;
            $provider = (string) $params->get('provider_cards', 'yookassa');
            $env = (string) $params->get('payments_env', 'test');
            $ptoken = '';

            if ($provider === 'yookassa') {
                $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
            } else {
                $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
            }

            if ($cardsEnabled && $ptoken !== '') {
                $model = new CheckoutModel();
                $model->setState('cart.id', (int) $cart->id);
                $model->setState('cart.code', (string) $cart->code);
                $order = $model->getItem();

                $paymentPlugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
                if ($paymentPlugin !== '' && stripos($paymentPlugin, 'telegram') !== false) {
                    $currency = (!empty($order->currency['code'])) ? (string) $order->currency['code'] : 'RUB';
                    $amountMinor = 0;
                    if (!empty($order->total['final'])) {
                        $amountMinor = (int) round(((float) $order->total['final']) * 100);
                    } elseif (!empty($order->total['final_string'])) {
                        $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
                        $num = str_replace([' ', ','], ['', '.'], $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }

                    if ($amountMinor > 0) {
                        $tg = new TelegramClient();
                        if ($tg->isConfigured()) {
                            $title = 'Заказ ' . ($number ?: ('#' . $orderId));
                            $desc = 'Оплата заказа в магазине';
                            $payload = 'order:' . $number;
                            $tg->sendInvoice($chatId, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore invoice errors
        }

        // Send message with payment link
        try {
            $tg = new TelegramClient();
            if ($tg->isConfigured()) {
                $message = 'Заказ ' . ($number ?: ('#' . $orderId)) . " создан.\nПерейдите к оплате по ссылке.";
                $tg->sendMessage($chatId, $message, [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => 'Оплатить заказ', 'url' => $payUrl],
                        ]],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore message errors
        }
    }

    public function getTariffs(int $chatId, int $shippingId, string $provider, string $extId): array
    {
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            return ['tariffs' => []];
        }
        if (!class_exists(ApiShip::class)) {
            return ['tariffs' => []];
        }
        $tariffResult = ApiShipIntegrationHelper::calculateTariff($shippingId, $cart, $extId, $provider);
        return ['tariffs' => $tariffResult['tariffs'] ?? []];
    }

    /**
     * Batch tariff calculation for multiple PVZ points
     * @param int $chatId
     * @param array $pvzIds Array of ext_ids
     * @param int $shippingId
     * @return array Results keyed by ext_id
     */
    public function getTariffsBatch(int $chatId, array $pvzIds, int $shippingId = 0): array
    {
        if (empty($pvzIds)) {
            return ['results' => []];
        }

        // Limit to 20 points per request
        $pvzIds = array_slice($pvzIds, 0, 20);

        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'));
        }

        // Get PVZ details from DB via PvzService
        $pvzService = new PvzService();
        $points = $pvzService->getPoints($pvzIds);

        $results = [];
        $inactiveToMark = [];

        foreach ($pvzIds as $extId) {
            if (!isset($points[$extId])) {
                $results[$extId] = null; // Point not found or inactive
                continue;
            }

            $point = $points[$extId];
            $provider = $point['provider'];

            try {
                $tariffResult = ApiShipIntegrationHelper::calculateTariff($shippingId, $cart, $extId, $provider);
                $tariffs = $tariffResult['tariffs'] ?? [];
                $debug = $tariffResult['__debug'] ?? null;

                if (!empty($tariffs)) {
                    // Find minimum price
                    $minPrice = PHP_INT_MAX;
                    $tariffList = [];
                    foreach ($tariffs as $t) {
                        $cost = (float)($t->deliveryCost ?? 0);
                        if ($cost < $minPrice) {
                            $minPrice = $cost;
                        }
                        $tariffList[] = [
                            'id' => (string)$t->tariffId,
                            'name' => $t->tariffName ?? '',
                            'cost' => $cost,
                            'days_min' => (int)($t->daysMin ?? 0),
                            'days_max' => (int)($t->daysMax ?? 0),
                        ];
                    }

                    $results[$extId] = [
                        'min_price' => $minPrice === PHP_INT_MAX ? 0 : $minPrice,
                        'tariffs' => $tariffList,
                        'provider' => $provider,
                        '_debug' => $debug,
                    ];

                    // Reset inactive counter if point has tariffs
                    if ((int)$point['inactive_count'] > 0) {
                        $pvzService->resetInactiveCount($extId, $provider);
                    }
                } else {
                    $results[$extId] = [
                        'error' => 'no_tariffs',
                        'provider' => $provider,
                        '_debug' => $debug,
                    ];
                    $inactiveToMark[] = ['ext_id' => $extId, 'provider' => $provider];
                }
            } catch (\Throwable $e) {
                Log::add("[getTariffsBatch] Error calculating for $extId: " . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
                $results[$extId] = [
                    'error' => $e->getMessage(),
                    'provider' => $provider,
                ];
                $inactiveToMark[] = ['ext_id' => $extId, 'provider' => $provider];
            }
        }

        return [
            'results' => $results,
            'inactive_to_mark' => $inactiveToMark,
        ];
    }

    public function setPayment(int $chatId, int $paymentId): array
    {
        $app = Factory::getApplication();
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        $sessionData['payment']['id'] = $paymentId;
        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
        return ['payment_id' => $paymentId];
    }

    private function mapShippingMethods($list): array
    {
        $out = [];
        if ($list) {
            foreach ($list as $m) {
                $method = [
                    'id' => (int)$m->id,
                    'title' => (string)$m->title,
                    'disabled' => !empty($m->disabled),
                    'plugin' => isset($m->plugin) ? (string)$m->plugin : '',
                    'providers' => []
                ];
                if (!empty($m->plugin) && stripos($m->plugin, 'apiship') !== false) {
                    try {
                        $params = ApiShip::getShippingMethodParams((int)$m->id);
                        $providers = $params->get('providers', []);
                        if (!empty($providers)) {
                            $method['providers'] = array_values((array)$providers);
                        }
                    } catch (\Throwable $e) {}
                }
                $out[] = $method;
            }
        }
        return $out;
    }

    private function mapPaymentMethods($list): array
    {
        $out = [];
        if ($list) {
            foreach ($list as $m) {
                $method = [
                    'id' => (int)$m->id,
                    'title' => (string)$m->title,
                    'disabled' => !empty($m->disabled),
                    'plugin' => isset($m->plugin) ? (string)$m->plugin : '',
                    'description' => isset($m->description) ? (string)$m->description : '',
                    'icon' => ''
                ];
                if (!empty($m->media)) {
                    $iconPath = '';
                    if ($m->media instanceof \Joomla\Registry\Registry) {
                        $iconPath = $m->media->get('icon', '');
                    } elseif (is_object($m->media) && isset($m->media->icon)) {
                        $iconPath = $m->media->icon;
                    } elseif (is_array($m->media) && isset($m->media['icon'])) {
                        $iconPath = $m->media['icon'];
                    }
                    if ($iconPath) {
                        if (($hashPos = strpos($iconPath, '#')) !== false) {
                            $iconPath = substr($iconPath, 0, $hashPos);
                        }
                        if (preg_match('#^https?://[^/]+(/.*?)$#i', $iconPath, $match)) {
                            $iconPath = $match[1];
                        }
                        $iconPath = ltrim($iconPath, '/');
                        $method['icon'] = $iconPath;
                    }
                }
                $out[] = $method;
            }
        }
        return $out;
    }

    private function chatHasUserMapping(int $chatId): bool
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chatId);
            return ((int) $db->setQuery($q, 0, 1)->loadResult()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function applyStarsRestrictions(array $res, $item): array
    {
        try {
            if (empty($item->products) || empty($res['payment']['methods'])) {
                return $res;
            }
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'));
            $paramsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
            $conf = [];
            if ($paramsJson !== '') {
                try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) { $conf = []; }
            }
            $parseCsv = function($csv) {
                $out = [];
                foreach (explode(',', (string)$csv) as $v) {
                    $v = trim($v);
                    if ($v !== '' && ctype_digit($v)) $out[] = (int)$v;
                }
                return array_values(array_unique($out));
            };
            $allowedCats = $parseCsv($conf['allowed_categories'] ?? '');
            $excludedCats = $parseCsv($conf['excluded_categories'] ?? '');
            $allowedProd = $parseCsv($conf['allowed_products'] ?? '');
            $excludedProd = $parseCsv($conf['excluded_products'] ?? '');
            $productCats = function($prod) {
                $ids = [];
                if (!empty($prod->category) && !empty($prod->category->id)) $ids[] = (int)$prod->category->id;
                if (!empty($prod->categories) && is_array($prod->categories)) {
                    foreach ($prod->categories as $c) {
                        if (is_array($c) && !empty($c['id']) && is_numeric($c['id'])) $ids[] = (int)$c['id'];
                        if (is_object($c) && !empty($c->id)) $ids[] = (int)$c->id;
                    }
                }
                if (!empty($prod->category_id) && is_numeric($prod->category_id)) $ids[] = (int)$prod->category_id;
                return array_values(array_unique($ids));
            };
            $isAllowedByCats = function($products) use ($allowedCats, $excludedCats, $productCats) {
                if (empty($allowedCats)) return true;
                foreach ($products as $prod) {
                    $ids = $productCats($prod);
                    if (!empty($ids)) {
                        $ok = false;
                        foreach ($ids as $cid) { if (in_array($cid, $allowedCats, true)) { $ok = true; break; } }
                        foreach ($ids as $cid) { if (in_array($cid, $excludedCats, true)) { $ok = false; break; } }
                        if (!$ok) return false;
                    }
                }
                return true;
            };
            $isAllowedByProd = function($products) use ($allowedProd, $excludedProd) {
                if (empty($allowedProd) && empty($excludedProd)) return true;
                foreach ($products as $prod) {
                    $pid = (int)($prod->id ?? 0);
                    if ($pid <= 0) continue;
                    if (!empty($allowedProd) && !in_array($pid, $allowedProd, true)) return false;
                    if (!empty($excludedProd) && in_array($pid, $excludedProd, true)) return false;
                }
                return true;
            };
            $allowedAll = $isAllowedByCats($item->products) && $isAllowedByProd($item->products);
            if (!$allowedAll) {
                foreach ($res['payment']['methods'] as &$pm) {
                    if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegramstars') !== false) {
                        $pm['disabled'] = true;
                    }
                }
                unset($pm);
            }
        } catch (\Throwable $e) {}
        return $res;
    }

    private function buildOrderData($order, $selectedTariff): ?array
    {
        if (empty($order->total)) {
            return null;
        }
        $shippingPrice = 0;
        $rmShippingCalculated = false;
        if (!empty($order->shipping->order->price['final'])) {
            $shippingPrice = (float) $order->shipping->order->price['final'];
            $rmShippingCalculated = true;
        }
        if ($selectedTariff && $shippingPrice <= 0) {
            $shippingPrice = (float)($selectedTariff->deliveryCost ?? 0);
            $rmShippingCalculated = false;
        }
        $shippingString = '';
        if ($shippingPrice > 0) {
            $shippingString = number_format($shippingPrice, 0, '', ' ') . ' ₽';
        }
        $rmFinal = (float)($order->total['final'] ?? 0);
        $baseSum = (float)($order->total['base'] ?? 0);
        if ($rmShippingCalculated) {
            $productsSum = $rmFinal - $shippingPrice;
        } else {
            $productsSum = $rmFinal;
        }
        $finalTotal = $productsSum + $shippingPrice;
        $productsSumString = number_format($productsSum, 0, '', ' ') . ' ₽';
        $orderTotal = number_format($finalTotal, 0, '', ' ') . ' ₽';
        $productDiscount = $baseSum - $productsSum;
        $discountString = ($productDiscount > 0) ? number_format($productDiscount, 0, '', ' ') . ' ₽' : '';
        return [
            'total' => [
                'quantity' => $order->total['quantity'] ?? 0,
                'sum' => $productsSum,
                'sum_string' => $productsSumString,
                'final' => $productsSum,
                'final_string' => $productsSumString,
                'discount' => $productDiscount,
                'discount_string' => $discountString,
                'shipping' => $shippingPrice,
                'shipping_string' => $shippingString,
            ]
        ];
    }
}
