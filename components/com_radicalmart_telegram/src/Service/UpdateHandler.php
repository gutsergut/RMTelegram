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
        if (!$this->client->isConfigured()) {
            return;
        }

        $data   = new Registry($rawBody);
        $update = $data->toArray();

        $updateId = (int) ($update['update_id'] ?? 0);
        $chatId   = 0;
        if (!empty($update['message']['chat']['id'])) {
            $chatId = (int) $update['message']['chat']['id'];
        } elseif (!empty($update['callback_query']['message']['chat']['id'])) {
            $chatId = (int) $update['callback_query']['message']['chat']['id'];
        }

        if ($updateId && $chatId && $this->store->isDuplicate($chatId, $updateId)) {
            Log::add('Duplicate update skipped: ' . $updateId, Log::DEBUG, 'com_radicalmart.telegram');
            return;
        }

        if (!empty($update['message'])) {
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
                $phone = RMUserHelper::cleanPhone($phoneRaw) ?: $phoneRaw;
                $username = (string) ($message['from']['username'] ?? '');
                $tgUserId = (int) ($message['from']['id'] ?? 0);
                $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
                // upsert row for chat_id
                $row = (object) [
                    'chat_id' => $chatId,
                    'tg_user_id' => $tgUserId,
                    'username' => $username,
                    'phone' => $phone,
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
                // try map by phone
                try { $found = RMUserHelper::findUser(['phone' => $phone]); if ($found && !empty($found->id)) { $userId = (int) $found->id; } } catch (\Throwable $e) {}
                if ($exist) {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('tg_user_id') . ' = ' . (int) $tgUserId)
                        ->set($db->quoteName('username') . ' = ' . $db->quote($username))
                        ->set($db->quoteName('phone') . ' = ' . $db->quote($phone));
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

        if ($text === '/start' || $text === '/help') {
            $welcome  = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $storeTitle);
            $welcome .= "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $storeTitle);
            // Offer contact request if no mapping exists yet
            $opts = [];
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q = $db->getQuery(true)
                    ->select('phone')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chatId);
                $has = (string) $db->setQuery($q, 0, 1)->loadResult();
                if ($has === '') {
                    $opts['reply_markup'] = [
                        'keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_SEND_PHONE'), 'request_contact' => true ] ]],
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true,
                    ];
                }
            } catch (\Throwable $e) {}
            $this->client->sendMessage($chatId, $welcome, $opts);
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

        $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CMD_RECEIVED'));
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

        if ($chatId) {
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
    }

    protected function onPreCheckout(array $query): void
    {
        $id = (string) ($query['id'] ?? '');
        if (!$id) return;
        // For now always approve; validation may be added later
        $this->client->answerPreCheckoutQuery($id, true);
    }
}
