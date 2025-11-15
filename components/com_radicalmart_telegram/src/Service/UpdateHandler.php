<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RMUserHelper;

class UpdateHandler
{
    protected TelegramClient $client;
    protected SessionStore $store;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
        $this->store  = new SessionStore();
    }

    public function handle(string $rawBody): void
    {
        Log::add('UpdateHandler::handle called with ' . strlen($rawBody) . ' bytes', Log::DEBUG, 'com_radicalmart.telegram');

        if (!$this->client->isConfigured()) {
            Log::add('Client not configured, skipping update', Log::WARNING, 'com_radicalmart.telegram');
            return;
        }

        $data   = new Registry($rawBody);
        $update = $data->toArray();

        Log::add('Update parsed: update_id=' . ($update['update_id'] ?? 'missing') . ', has_message=' . (isset($update['message']) ? 'YES' : 'NO'), Log::DEBUG, 'com_radicalmart.telegram');

        $updateId = (int) ($update['update_id'] ?? 0);
        $chatId   = 0;
        if (!empty($update['message']['chat']['id'])) {
            $chatId = (int) $update['message']['chat']['id'];
        } elseif (!empty($update['callback_query']['message']['chat']['id'])) {
            $chatId = (int) $update['callback_query']['message']['chat']['id'];
        }

        Log::add('Chat ID: ' . $chatId, Log::DEBUG, 'com_radicalmart.telegram');

        if ($updateId && $chatId && $this->store->isDuplicate($chatId, $updateId)) {
            Log::add('Duplicate update skipped: ' . $updateId, Log::DEBUG, 'com_radicalmart.telegram');
            return;
        }

        if (!empty($update['message'])) {
            Log::add('Processing message...', Log::DEBUG, 'com_radicalmart.telegram');
            $this->onMessage($update['message']);
            if ($updateId && $chatId) {
                $this->store->setLastUpdate($chatId, $updateId);
            }
            return;
        }

        if (!empty($update['callback_query'])) {
            $this->onCallback($update['callback_query']);
            if ($updateId && $chatId) {
                $this->store->setLastUpdate($chatId, $updateId);
            }
            return;
        }

        // Telegram Payments: pre_checkout_query
        if (!empty($update['pre_checkout_query'])) {
            $this->onPreCheckout($update['pre_checkout_query']);
            return;
        }
    }

    protected function onMessage(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text   = (string) ($message['text'] ?? '');

        // Contact sharing (requestContact): link phone and user
        if (!empty($message['contact']) && $chatId) {
            try {
                $phoneRaw = (string) ($message['contact']['phone_number'] ?? '');
                $phoneClean = RMUserHelper::cleanPhone($phoneRaw) ?: $phoneRaw;
                // Варианты для поиска (RadicalMart хранит с +, SMS-компонент без +)
                $digitsOnly = preg_replace('#[^0-9]#','', $phoneClean);
                $withoutPlus = ltrim($phoneClean, '+');
                // Нормализация российских номеров: если начинается с '8' заменить на '7'
                $rusAlt = $digitsOnly;
                if (strlen($digitsOnly) >= 11) {
                    if ($digitsOnly[0] === '8') {
                        $rusAlt = '7' . substr($digitsOnly, 1);
                    } elseif ($digitsOnly[0] === '7') {
                        $rusAlt = '8' . substr($digitsOnly, 1); // обратный вариант
                    }
                }
                $candidates = array_unique(array_filter([
                    $phoneClean,
                    '+' . $digitsOnly,
                    $digitsOnly,
                    $withoutPlus,
                    '+' . $rusAlt,
                    $rusAlt,
                ]));
                $username = (string) ($message['from']['username'] ?? '');
                $tgUserId = (int) ($message['from']['id'] ?? 0);
                $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
                // upsert row for chat_id
                $row = (object) [
                    'chat_id' => $chatId,
                    'tg_user_id' => $tgUserId,
                    'username' => $username,
                    'phone' => $phoneClean,
                    'created' => (new \Joomla\CMS\Date\Date())->toSql(),
                ];
                // find existing
                $q = $db->getQuery(true)
                    ->select('id,user_id')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chatId);
                $exist = $db->setQuery($q, 0, 1)->loadAssoc();
                $userId = 0;
                // Поиск пользователя по всем вариантам телефонов
                foreach ($candidates as $cand) {
                    if ($userId) { break; }
                    try {
                        $found = RMUserHelper::findUser(['phone' => $cand]);
                        if ($found && !empty($found->id)) { $userId = (int) $found->id; break; }
                    } catch (\Throwable $e) { /* ignore */ }
                }
                // Fallback: попробовать найти created_by в логах com_j_sms_registration (номер без '+')
                if (!$userId) {
                    $plain = preg_replace('#[^0-9]#','', $phoneClean);
                    if (!empty($plain)) {
                        try {
                            $q2 = $db->getQuery(true)
                                ->select('created_by')
                                ->from($db->quoteName('#__j_sms_registration_logs'))
                                ->where($db->quoteName('phone') . ' = :p')
                                ->where($db->quoteName('created_by') . ' > 0')
                                ->order($db->quoteName('id') . ' DESC');
                            $q2->bind(':p', $plain);
                            $logUser = (int) $db->setQuery($q2, 0, 1)->loadResult();
                            if ($logUser > 0) { $userId = $logUser; }
                        } catch (\Throwable $e) { /* ignore */ }
                    }
                }
                if ($exist) {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('tg_user_id') . ' = ' . (int) $tgUserId)
                        ->set($db->quoteName('username') . ' = ' . $db->quote($username))
                        ->set($db->quoteName('phone') . ' = ' . $db->quote($phoneClean));
                    if ($userId > 0) { $upd->set($db->quoteName('user_id') . ' = ' . (int) $userId); }
                    $upd->where($db->quoteName('id') . ' = ' . (int) $exist['id']);
                    $db->setQuery($upd)->execute();
                } else {
                    if ($userId > 0) { $row->user_id = $userId; }
                    $db->insertObject('#__radicalmart_telegram_users', $row);
                }
                $msg = $userId > 0
                    ? \Joomla\CMS\Language\Text::_('COM_RADICALMART_TELEGRAM_CONTACT_LINKED')
                    : \Joomla\CMS\Language\Text::_('COM_RADICALMART_TELEGRAM_CONTACT_SAVED');
                $this->client->sendMessage($chatId, $msg);
                return;
            } catch (\Throwable $e) {
                \Joomla\CMS\Log\Log::add('Contact link error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'com_radicalmart.telegram');
            }
        }

        // successful payment
        if (!empty($message['successful_payment'])) {
            $sp = $message['successful_payment'];
            $payload = (string) ($sp['invoice_payload'] ?? '');
            $total   = (int) ($sp['total_amount'] ?? 0);
            if ($chatId) {
                $rub = number_format($total / 100, 2, ',', ' ');
                $this->client->sendMessage($chatId, 'Оплата получена: ' . $rub . ' ₽.');
            }
            // Change order status by payload order:<number>
            if (strpos($payload, 'order:') === 0) {
                $number = substr($payload, strlen('order:'));
                try {
                    $paidStatus = (int) \Joomla\CMS\Factory::getApplication()->getParams('com_radicalmart_telegram')->get('paid_status_id', 0);
                    if ($paidStatus > 0 && $number !== '') {
                        // Find order id by number via PaymentModel
                        $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
                        $order = $pm->getOrder($number, 'number');
                        if ($order && !empty($order->id)) {
                            // Log provider payment charge id if present
                            $chargeId = isset($sp['provider_payment_charge_id']) ? (string) $sp['provider_payment_charge_id'] : '';
                            try {
                                $admLog = new \Joomla\Component\RadicalMart\Administrator\Model\OrderModel();
                                $admLog->addLog((int) $order->id, 'telegram_payment', [
                                    'message' => 'Telegram successful_payment',
                                    'provider_payment_charge_id' => $chargeId,
                                ]);
                            } catch (\Throwable $e) { /* ignore */ }
                            $adm = new \Joomla\Component\RadicalMart\Administrator\Model\OrderModel();
                            $adm->updateStatus((int) $order->id, $paidStatus, false, null, 'Telegram: successful_payment');
                        }
                    }
                } catch (\Throwable $e) {
                    \Joomla\CMS\Log\Log::add('Telegram successful_payment status error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_radicalmart.telegram');
                }
            }
            return;
        }

        if (!$chatId) {
            return;
        }

        $text = trim($text);
        $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        $storeTitle = (string) ($params->get('store_title', 'магазин Cacao.Land'));

        Log::add('Message text: "' . $text . '"', Log::DEBUG, 'com_radicalmart.telegram');

        if ($text === '/start' || $text === '/help') {
            Log::add('Processing /start or /help command', Log::DEBUG, 'com_radicalmart.telegram');

            // Check consent first
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q = $db->getQuery(true)
                    ->select(['consent_personal_data', 'phone'])
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chatId);
                $user = $db->setQuery($q, 0, 1)->loadAssoc();

                $hasConsent = $user && (int)$user['consent_personal_data'] === 1;
                $hasPhone = $user && !empty($user['phone']);

                Log::add('User consent: ' . ($hasConsent ? 'YES' : 'NO') . ', phone: ' . ($hasPhone ? 'YES' : 'NO'), Log::DEBUG, 'com_radicalmart.telegram');

                // Unified consent keyboard (personal_data + terms mandatory)
                $statuses = ConsentHelper::getConsents($chatId);
                $needUnified = empty($statuses['personal_data']) || empty($statuses['terms']);
                if ($needUnified) {
                    $consentText = $this->composeConsentMessage($statuses);
                    $keyboard = $this->buildConsentKeyboard($statuses);
                    $opts = [ 'parse_mode' => 'HTML', 'reply_markup' => $keyboard ];
                    $this->client->sendMessage($chatId, $consentText, $opts);
                    return;
                }

                // Step 2: Welcome + request phone if consent given but no phone
                $welcome  = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $storeTitle);
                $welcome .= "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $storeTitle);
                $opts = [];

                if (!$hasPhone) {
                    $opts['reply_markup'] = [
                        'keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_SEND_PHONE'), 'request_contact' => true ] ]],
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true,
                    ];
                }

                Log::add('Sending welcome message to chat ' . $chatId, Log::DEBUG, 'com_radicalmart.telegram');
                $result = $this->client->sendMessage($chatId, $welcome, $opts);
                Log::add('sendMessage result: ' . ($result ? 'SUCCESS' : 'FAILED'), Log::DEBUG, 'com_radicalmart.telegram');

                // Step 3: Offer marketing opt-in if not accepted yet (if enabled in settings)
                try {
                    $stAll = ConsentHelper::getConsents($chatId);
                    $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
                    $marketingEnabled = (int) $params->get('marketing_prompt_enabled', 1) === 1;
                    if ($marketingEnabled && array_key_exists('marketing', $stAll) && empty($stAll['marketing'])) {
                        $this->sendMarketingPrompt($chatId);
                    }
                } catch (\Throwable $e) { /* ignore */ }

            } catch (\Throwable $e) {
                Log::add('Error in /start handler: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
            }
            return;
        }

        $cmd = mb_strtolower($text, 'UTF-8');
        $webAppBase = rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp';
        $webAppUrl  = $webAppBase . '&chat=' . $chatId;
        $webAppButton = [
            'inline_keyboard' => [[
                [ 'text' => Text::sprintf('COM_RADICALMART_TELEGRAM_OPEN_STORE', $storeTitle), 'web_app' => [ 'url' => $webAppUrl ] ],
            ]],
        ];

        // Global consent gate for any further commands (except /start handled above)
        try {
            $cons = ConsentHelper::getConsents($chatId);
            if (empty($cons['personal_data']) || empty($cons['terms'])) {
                $consentText = $this->composeConsentMessage($cons);
                $keyboard = $this->buildConsentKeyboard($cons);
                $this->client->sendMessage($chatId, $consentText, [ 'parse_mode' => 'HTML', 'reply_markup' => $keyboard ]);
                return;
            }
        } catch (\Throwable $e) {
            // In case of DB issues, just continue
        }

        switch ($cmd) {
            case '/catalog':
            case 'каталог':
                $page = 1;
                $this->store->setState($chatId, 'browsing', ['page' => $page]);

                $list = (new CatalogService())->listProducts($page, 5);
                $text = Text::sprintf('COM_RADICALMART_TELEGRAM_OPENED_CATALOG', $storeTitle) . "\n\n";
                if ($list) {
                    $i = 1;
                    foreach ($list as $p) {
                        $title = trim($p['title']);
                        if ($title === '') { continue; }
                        $text .= $i++ . '. ' . $title . "\n";
                    }
                } else {
                    $text .= Text::_('COM_RADICALMART_TELEGRAM_PRODUCTS_LIST_EMPTY');
                }

                $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $webAppButton ]);
                return;

            case '/cart':
            case 'корзина':
                $this->store->setState($chatId, 'cart');
                $cartSvc = new CartService();
                $cart = $cartSvc->getCart($chatId);
                $text = $cartSvc->renderCartMessage($cart);
                $keyboard = $cartSvc->getKeyboard($cart, $webAppBase . '&chat=' . $chatId);
                $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]);
                return;

            case '/checkout':
            case 'оформить заказ':
            case 'оформить':
                $this->store->setState($chatId, 'checkout');
                $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_CHECKOUT_GO_WEBAPP', $storeTitle), [ 'reply_markup' => $webAppButton ]);
                return;

            case '/orders':
            case 'мои заказы':
                $this->store->setState($chatId, 'orders');
                $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_ORDERS_HINT', $storeTitle), [ 'reply_markup' => $webAppButton ]);
                return;

            case '/promo':
            case 'промокод':
                $this->store->setState($chatId, 'promo');
                $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_PROMO_HINT', $storeTitle), [ 'reply_markup' => $webAppButton ]);
                return;

            case '/points':
            case 'баллы':
                $this->store->setState($chatId, 'points');
                $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_HINT', $storeTitle), [ 'reply_markup' => $webAppButton ]);
                return;
        }

        $this->client->sendMessage(
            $chatId,
            Text::sprintf('COM_RADICALMART_TELEGRAM_OPEN_WEBAPP_HINT', $storeTitle),
            [ 'reply_markup' => $webAppButton ]
        );
    }

    protected function onCallback(array $callback): void
    {
        $id    = (string) ($callback['id'] ?? '');
        $data  = (string) ($callback['data'] ?? '');
        $chatId = (int) ($callback['message']['chat']['id'] ?? 0);
        $messageId = (int) ($callback['message']['message_id'] ?? 0);

        if ($id) {
            $this->client->answerCallbackQuery($id);
        }

        if (!$chatId) {
            return;
        }

        // Unified consent callbacks first
        try {
            // Toggle individual consent
            if (str_starts_with($data, 'consent_toggle:')) {
                $type = substr($data, strlen('consent_toggle:'));
                if (in_array($type, ['personal_data','terms','marketing'], true)) {
                    ConsentHelper::saveConsent($chatId, $type, true);
                    $st = ConsentHelper::getConsents($chatId);
                    if (!empty($st['personal_data']) && !empty($st['terms'])) {
                        // Если это переключение маркетинга после обязательных — просто подтвердим и уберём клавиатуру
                        if ($type === 'marketing') {
                            if ($messageId) {
                                $this->client->editMessageText($chatId, $messageId, Text::_('COM_RADICALMART_TELEGRAM_MARKETING_ENABLED'));
                            } else {
                                $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_MARKETING_ENABLED'));
                            }
                            return;
                        }
                        $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CONSENT_ALL_ACCEPTED'));
                        $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
                        $storeTitle = (string) ($params->get('store_title', 'магазин Cacao.Land'));
                        $welcome  = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $storeTitle);
                        $welcome .= "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $storeTitle);
                        $opts = [
                            'reply_markup' => [
                                'keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_SEND_PHONE'), 'request_contact' => true ] ]],
                                'one_time_keyboard' => true,
                                'resize_keyboard' => true,
                            ]
                        ];
                        $this->client->sendMessage($chatId, $welcome, $opts);
                        // Предложим подписку на маркетинг, если ещё не принята и включено в настройках
                        $params2 = Factory::getApplication()->getParams('com_radicalmart_telegram');
                        $marketingEnabled2 = (int) $params2->get('marketing_prompt_enabled', 1) === 1;
                        if ($marketingEnabled2 && array_key_exists('marketing', $st) && empty($st['marketing'])) {
                            $this->sendMarketingPrompt($chatId);
                        }
                    } else if ($messageId) {
                        // Обновляем и текст, и клавиатуру, чтобы отразить текущие статусы
                        $text = $this->composeConsentMessage($st);
                        $this->client->editMessageText($chatId, $messageId, $text, [
                            'parse_mode' => 'HTML',
                            'reply_markup' => $this->buildConsentKeyboard($st)
                        ]);
                    }
                    return;
                }
            }

            // Accept all remaining
            if ($data === 'consent_all') {
                $st = ConsentHelper::getConsents($chatId);
                foreach (['personal_data','terms','marketing'] as $t) {
                    if (isset($st[$t]) && empty($st[$t])) {
                        ConsentHelper::saveConsent($chatId, $t, true);
                    }
                }
                $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CONSENT_ALL_ACCEPTED'));
                $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
                $storeTitle = (string) ($params->get('store_title', 'магазин Cacao.Land'));
                $welcome  = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $storeTitle);
                $welcome .= "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $storeTitle);
                $opts = [
                    'reply_markup' => [
                        'keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_SEND_PHONE'), 'request_contact' => true ] ]],
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true,
                    ]
                ];
                $this->client->sendMessage($chatId, $welcome, $opts);
                // Все согласия приняты; отдельное приглашение не нужно, но если маркетинг был отключён в конфиге — ничего не отправляем
                return;
            }

            if ($data === 'marketing_skip') {
                if ($messageId) {
                    $this->client->editMessageText($chatId, $messageId, Text::_('COM_RADICALMART_TELEGRAM_MARKETING_SKIPPED'));
                } else {
                    $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_MARKETING_SKIPPED'));
                }
                return;
            }

            // Legacy single consent button
            if ($data === 'consent_accept') {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $now = (new \Joomla\CMS\Date\Date())->toSql();
                $q = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chatId);
                $userId = (int) $db->setQuery($q, 0, 1)->loadResult();
                if ($userId > 0) {
                    $q = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('consent_personal_data') . ' = 1')
                        ->set($db->quoteName('consent_personal_data_at') . ' = ' . $db->quote($now))
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':id', $userId);
                    $db->setQuery($q)->execute();
                } else {
                    $obj = (object) [
                        'chat_id' => $chatId,
                        'consent_personal_data' => 1,
                        'consent_personal_data_at' => $now,
                        'created' => $now,
                    ];
                    $db->insertObject('#__radicalmart_telegram_users', $obj);
                }
                $st = ConsentHelper::getConsents($chatId);
                if (empty($st['terms'])) {
                    $consentText = $this->composeConsentMessage($st);
                    $keyboard = $this->buildConsentKeyboard($st);
                    $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CONSENT_ACCEPTED'));
                    $this->client->sendMessage($chatId, $consentText, [ 'parse_mode' => 'HTML', 'reply_markup' => $keyboard ]);
                    return;
                }
                $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CONSENT_ALL_ACCEPTED'));
                $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
                $storeTitle = (string) ($params->get('store_title', 'магазин Cacao.Land'));
                $welcome  = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $storeTitle);
                $welcome .= "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $storeTitle);
                $opts = [
                    'reply_markup' => [
                        'keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_SEND_PHONE'), 'request_contact' => true ] ]],
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true,
                    ]
                ];
                $this->client->sendMessage($chatId, $welcome, $opts);
                // После общего принятия можно отдельно не предлагать, если не требуется
                return;
            }
        } catch (\Throwable $e) { /* ignore consent errors */ }

        // Other callbacks below
        if (strpos($data, 'catalog:page:') === 0) {
                $page = (int) substr($data, strlen('catalog:page:'));
                if ($page < 1) { $page = 1; }
                $this->store->setState($chatId, 'browsing', ['page' => $page]);
                $list = (new CatalogService())->listProducts($page, 5);
                $text = Text::sprintf('COM_RADICALMART_TELEGRAM_CATALOG_PAGE', $page) . "\n\n";
                if ($list) {
                    $i = 1 + (5 * ($page - 1));
                    foreach ($list as $p) {
                        $title = trim($p['title']);
                        if ($title === '') { continue; }
                        $text .= $i++ . '. ' . $title . "\n";
                    }
                } else {
                    $text .= Text::_('COM_RADICALMART_TELEGRAM_NOT_FOUND');
                }
                $keyboard = [
                    'inline_keyboard' => [[
                        [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_BACK'), 'callback_data' => 'catalog:page:' . max(1, $page - 1) ],
                        [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_MORE_NEXT'), 'callback_data' => 'catalog:page:' . ($page + 1) ],
                    ]],
                ];
                if ($messageId) {
                    $this->client->editMessageText($chatId, $messageId, $text, ['reply_markup' => $keyboard]);
                } else {
                    $this->client->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
                }
                return;
            }

            if (strpos($data, 'product:') === 0) {
                $productId = (int) substr($data, strlen('product:'));
                $this->store->setState($chatId, 'product', ['id' => $productId]);

                $product = (new ProductService())->getProduct($productId);
                if ($product && is_object($product)) {
                    $text = (new ProductPresenter())->toMessage($product);
                    $keyboard = [
                        'inline_keyboard' => [[
                            [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'), 'callback_data' => 'cart:add:' . $productId ],
                        ],[
                            [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_BACK_TO_CATALOG'), 'callback_data' => 'catalog:page:1' ],
                        ]],
                    ];
                    if ($messageId) {
                        $this->client->editMessageText($chatId, $messageId, $text, ['reply_markup' => $keyboard]);
                    } else {
                        $this->client->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
                    }
                } else {
                    $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_PRODUCT_NOT_FOUND'));
                }
                return;
            }

            if (strpos($data, 'cart:add:') === 0) {
                $productId = (int) substr($data, strlen('cart:add:'));
                $svc = new CartService();
                $res = $svc->addProduct($chatId, $productId, 1);
                if ($res === false) {
                    $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART_FAILED'));
                    return;
                }
                $cart = $res['cart'] ?? null;
                $text = $svc->renderCartMessage($cart);
                $keyboard = $svc->getKeyboard($cart, rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp&chat=' . $chatId);
                if ($messageId) {
                    $this->client->editMessageText($chatId, $messageId, $text, [ 'reply_markup' => $keyboard ]);
                } else {
                    $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]);
                }
                return;
            }

            if (strpos($data, 'cart:inc:') === 0 || strpos($data, 'cart:dec:') === 0 || strpos($data, 'cart:rm:') === 0) {
                $svc = new CartService();
                $productId = (int) preg_replace('#^cart:(?:inc|dec|rm):#','',$data);
                if ($productId <= 0) { return; }
                if (str_starts_with($data, 'cart:rm:')) {
                    $res = $svc->remove($chatId, $productId);
                } else {
                    $cart = $svc->getCart($chatId);
                    $cur = 0.0;
                    if ($cart && !empty($cart->products)) {
                        foreach ($cart->products as $key => $p) {
                            if ((int)($p->id ?? 0) === $productId) { $cur = (float)($p->order['quantity'] ?? 1); break; }
                        }
                    }
                    $newQty = $cur + (str_starts_with($data, 'cart:inc:') ? 1 : -1);
                    if ($newQty <= 0) {
                        $res = $svc->remove($chatId, $productId);
                    } else {
                        $res = $svc->setQuantity($chatId, $productId, $newQty);
                    }
                }
                if ($res === false) { $this->client->answerCallbackQuery($id, Text::_('COM_RADICALMART_TELEGRAM_CART_UPDATE_ERROR'), true); return; }
                $cart = $res['cart'] ?? null;
                $text = $svc->renderCartMessage($cart);
                $keyboard = $svc->getKeyboard($cart, rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp&chat=' . $chatId);
                if ($messageId) {
                    $this->client->editMessageText($chatId, $messageId, $text, [ 'reply_markup' => $keyboard ]);
                } else {
                    $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]);
                }
                return;
            }

            $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_CMD_ECHO', $data));
        }

    public function onPreCheckout(array $query): void
    {
        $id = (string) ($query['id'] ?? '');
        if (!$id) return;
        // For now always approve; validation may be added later
        $this->client->answerPreCheckoutQuery($id, true);
    }

    /**
     * Build unified consent inline keyboard reflecting current statuses.
     * personal_data & terms mandatory; marketing optional.
     */
    protected function buildConsentKeyboard(array $statuses): array
    {
        $rows = [];
        $map = [
            'personal_data' => Text::_('COM_RADICALMART_TELEGRAM_CONSENT_LABEL_PERSONAL'),
            'terms' => Text::_('COM_RADICALMART_TELEGRAM_CONSENT_LABEL_TERMS'),
            'marketing' => Text::_('COM_RADICALMART_TELEGRAM_CONSENT_LABEL_MARKETING'),
        ];
        foreach (['personal_data','terms','marketing'] as $t) {
            if (!array_key_exists($t, $statuses)) continue; // skip absent
            $isOpt = ($t === 'marketing');
            // Only show marketing if exists in statuses array (configured)
            if ($t === 'marketing' && !isset($statuses['marketing'])) continue;
            $icon = !empty($statuses[$t]) ? '✅' : ($t === 'marketing' ? '➕' : '❌');
            $rows[] = [ [ 'text' => $icon . ' ' . $map[$t], 'callback_data' => 'consent_toggle:' . $t ] ];
        }
        // Accept all button if any mandatory missing
        if (empty($statuses['personal_data']) || empty($statuses['terms']) || (isset($statuses['marketing']) && empty($statuses['marketing']))) {
            $rows[] = [ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_CONSENT_BUTTON_ALL'), 'callback_data' => 'consent_all' ] ];
        }
        return [ 'inline_keyboard' => $rows ];
    }

    /**
     * Compose consent intro message with visible links to all docs.
     */
    protected function composeConsentMessage(array $statuses): string
    {
        $urls = ConsentHelper::getAllDocumentUrls();
        // Fallbacks from language if not configured
        $privacyUrl   = $urls['privacy']   ?: Text::_('COM_RADICALMART_TELEGRAM_PRIVACY_POLICY_URL');
        $consentUrl   = $urls['consent']   ?: Text::_('COM_RADICALMART_TELEGRAM_CONSENT_URL');
        $termsUrl     = $urls['terms']     ?: Text::_('COM_RADICALMART_TELEGRAM_TERMS_URL');
        $marketingUrl = $urls['marketing'] ?: '';

        $intro = '<b>' . Text::_('COM_RADICALMART_TELEGRAM_CONSENT_INTRO') . '</b>';
        $docsTitle = "\n\n" . Text::_('COM_RADICALMART_TELEGRAM_CONSENT_DOCS_TITLE') . "\n";
        $list  = '';
        $list .= '• <a href="' . htmlspecialchars($consentUrl, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_RADICALMART_TELEGRAM_CONSENT_DOC') . '</a>' . "\n";
        $list .= '• <a href="' . htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_RADICALMART_TELEGRAM_PRIVACY') . '</a>' . "\n";
        $list .= '• <a href="' . htmlspecialchars($termsUrl, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_RADICALMART_TELEGRAM_TERMS') . '</a>' . "\n";
        if ($marketingUrl !== '') {
            $list .= '• <a href="' . htmlspecialchars($marketingUrl, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_RADICALMART_TELEGRAM_MARKETING') . '</a> <i>(' . Text::_('COM_RADICALMART_TELEGRAM_OPTIONAL') . ')</i>' . "\n";
        }
        $hint = "\n" . Text::_('COM_RADICALMART_TELEGRAM_CONSENT_MULTI_HINT');
        return $intro . $docsTitle . $list . $hint;
    }

    /**
     * Send separate prompt to opt-in to marketing notifications.
     */
    protected function sendMarketingPrompt(int $chatId): void
    {
        $text = Text::_('COM_RADICALMART_TELEGRAM_MARKETING_PROMPT');
        $keyboard = [
            'inline_keyboard' => [[
                [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_MARKETING_ENABLE'), 'callback_data' => 'consent_toggle:marketing' ],
                [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_MARKETING_SKIP'), 'callback_data' => 'marketing_skip' ],
            ]],
        ];
        $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]);
    }
}
