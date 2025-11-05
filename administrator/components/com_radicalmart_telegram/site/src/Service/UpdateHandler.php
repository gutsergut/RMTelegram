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
    { $this->client = $client; $this->store  = new SessionStore(); }

    public function handle(string $rawBody): void
    {
        if (!$this->client->isConfigured()) { return; }
        $data   = new Registry($rawBody);
        $update = $data->toArray();
        $updateId = (int) ($update['update_id'] ?? 0);
        $chatId   = 0;
        if (!empty($update['message']['chat']['id'])) { $chatId = (int) $update['message']['chat']['id']; }
        elseif (!empty($update['callback_query']['message']['chat']['id'])) { $chatId = (int) $update['callback_query']['message']['chat']['id']; }
        if ($updateId && $chatId && $this->store->isDuplicate($chatId, $updateId)) { Log::add('Duplicate update skipped: ' . $updateId, Log::DEBUG, 'com_radicalmart.telegram'); return; }
        if (!empty($update['message'])) { $this->onMessage($update['message']); if ($updateId && $chatId) { $this->store->setLastUpdate($chatId, $updateId); } return; }
        if (!empty($update['callback_query'])) { $this->onCallback($update['callback_query']); if ($updateId && $chatId) { $this->store->setLastUpdate($chatId, $updateId); } return; }
        if (!empty($update['pre_checkout_query'])) { $this->onPreCheckout($update['pre_checkout_query']); return; }
    }

    protected function onMessage(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text   = (string) ($message['text'] ?? '');
        if (!empty($message['contact']) && $chatId) {
            try {
                $phoneRaw = (string) ($message['contact']['phone_number'] ?? '');
                $phone = RMUserHelper::cleanPhone($phoneRaw) ?: $phoneRaw;
                $username = (string) ($message['from']['username'] ?? '');
                $tgUserId = (int) ($message['from']['id'] ?? 0);
                $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
                $row = (object) [ 'chat_id' => $chatId, 'tg_user_id' => $tgUserId, 'username' => $username, 'phone' => $phone, 'created' => (new \Joomla\CMS\Date\Date())->toSql(), ];
                $q = $db->getQuery(true)->select('id,user_id')->from($db->quoteName('#__radicalmart_telegram_users'))->where($db->quoteName('chat_id') . ' = :chat')->bind(':chat', $chatId);
                $exist = $db->setQuery($q, 0, 1)->loadAssoc();
                $userId = 0; try { $found = RMUserHelper::findUser(['phone' => $phone]); if ($found && !empty($found->id)) { $userId = (int) $found->id; } } catch (\Throwable $e) {}
                if ($exist) {
                    $upd = $db->getQuery(true)->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('tg_user_id') . ' = ' . (int) $tgUserId)
                        ->set($db->quoteName('username') . ' = ' . $db->quote($username))
                        ->set($db->quoteName('phone') . ' = ' . $db->quote($phone));
                    if ($userId > 0) { $upd->set($db->quoteName('user_id') . ' = ' . (int) $userId); }
                    $upd->where($db->quoteName('id') . ' = ' . (int) $exist['id']);
                    $db->setQuery($upd)->execute();
                } else { if ($userId > 0) { $row->user_id = $userId; } $db->insertObject('#__radicalmart_telegram_users', $row); }
                $msg = $userId > 0 ? Text::_('COM_RADICALMART_TELEGRAM_CONTACT_LINKED') : Text::_('COM_RADICALMART_TELEGRAM_CONTACT_SAVED');
                $this->client->sendMessage($chatId, $msg);
                return;
            } catch (\Throwable $e) { Log::add('Contact link error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram'); }
        }
        if (!empty($message['successful_payment'])) { $this->onSuccessfulPayment($message); return; }
        $cmd = trim(mb_strtolower($text));
        if ($cmd === '/start' || $cmd === 'start') { $this->sendWelcome($chatId); return; }
        if ($cmd === '/help' || $cmd === 'help' || $cmd === 'помощь') { $this->sendHelp($chatId); return; }
        $this->sendWelcome($chatId);
    }

    protected function sendWelcome(int $chatId): void
    {
        $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        $title = (string) $params->get('store_title', 'магазин Cacao.Land');
        $root = rtrim(Uri::root(), '/');
        $url  = $root . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp&chat=' . $chatId;
        $text = Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME', $title) . "\n\n" . Text::sprintf('COM_RADICALMART_TELEGRAM_WELCOME_HINT', $title);
        $kb = [ 'inline_keyboard' => [[ [ 'text' => Text::sprintf('COM_RADICALMART_TELEGRAM_OPEN_STORE', $title), 'web_app' => [ 'url' => $url ] ] ]] ];
        $this->client->sendMessage($chatId, $text, ['reply_markup' => $kb]);
    }

    protected function sendHelp(int $chatId): void
    { $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_CMD_RECEIVED')); }

    protected function onPreCheckout(array $query): void
    { $id = (string) ($query['id'] ?? ''); if (!$id) return; $this->client->answerPreCheckoutQuery($id, true); }

    protected function onSuccessfulPayment(array $message): void
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $sp = $message['successful_payment'] ?? [];
        $payload = (string) ($sp['invoice_payload'] ?? '');
        if (strpos($payload, 'order:') !== 0) { return; }
        $num = substr($payload, strlen('order:'));
        try {
            $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
            $order = $pm->getOrder($num, 'number');
            if ($order && !empty($order->id)) {
                $statusId = (int) Factory::getApplication()->getParams('com_radicalmart_telegram')->get('paid_status_id', 0);
                if ($statusId > 0) { try { $pm->changeStatus((int) $order->id, $statusId, 'Paid via Telegram'); } catch (\Throwable $e) {} }
                $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_INVOICE_SENT'));
            }
        } catch (\Throwable $e) { Log::add('successful_payment error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram'); }
    }

    protected function onCallback(array $callback): void
    {
        $id      = (string) ($callback['id'] ?? '');
        $data    = (string) ($callback['data'] ?? '');
        $chatId  = (int) ($callback['message']['chat']['id'] ?? 0);
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        if (!$chatId || !$data) { return; }
        if (strpos($data, 'catalog:page:') === 0) {
            $page = (int) substr($data, strlen('catalog:page:'));
            $items = (new CatalogService())->listProducts($page, 5, []);
            $lines = [Text::sprintf('COM_RADICALMART_TELEGRAM_CATALOG_PAGE', $page)];
            if (!$items) { $lines[] = Text::_('COM_RADICALMART_TELEGRAM_NOT_FOUND'); }
            else { foreach ($items as $p) { $lines[] = '• ' . ($p['title'] ?? '') . (($p['price_final'] ?? '') ? (' — ' . $p['price_final']) : ''); } }
            $text = implode("\n", $lines);
            $keyboard = [ 'inline_keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_BACK'), 'callback_data' => 'catalog:page:' . max(1, $page - 1) ], [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_MORE_NEXT'), 'callback_data' => 'catalog:page:' . ($page + 1) ], ]] ];
            if ($messageId) { $this->client->editMessageText($chatId, $messageId, $text, ['reply_markup' => $keyboard]); }
            else { $this->client->sendMessage($chatId, $text, ['reply_markup' => $keyboard]); }
            return;
        }
        if (strpos($data, 'product:') === 0) {
            $productId = (int) substr($data, strlen('product:'));
            $this->store->setState($chatId, 'product', ['id' => $productId]);
            $product = (new ProductService())->getProduct($productId);
            if ($product && is_object($product)) {
                $text = (new ProductPresenter())->toMessage($product);
                $keyboard = [ 'inline_keyboard' => [[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART'), 'callback_data' => 'cart:add:' . $productId ], ],[ [ 'text' => Text::_('COM_RADICALMART_TELEGRAM_BACK_TO_CATALOG'), 'callback_data' => 'catalog:page:1' ], ]] ];
                if ($messageId) { $this->client->editMessageText($chatId, $messageId, $text, ['reply_markup' => $keyboard]); }
                else { $this->client->sendMessage($chatId, $text, ['reply_markup' => $keyboard]); }
            } else { $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_PRODUCT_NOT_FOUND')); }
            return;
        }
        if (strpos($data, 'cart:add:') === 0) {
            $productId = (int) substr($data, strlen('cart:add:'));
            $svc = new CartService();
            $res = $svc->addProduct($chatId, $productId, 1);
            if ($res === false) { $this->client->sendMessage($chatId, Text::_('COM_RADICALMART_TELEGRAM_ADD_TO_CART_FAILED')); return; }
            $cart = $res['cart'] ?? null;
            $text = $svc->renderCartMessage($cart);
            $keyboard = $svc->getKeyboard($cart, rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp&chat=' . $chatId);
            if ($messageId) { $this->client->editMessageText($chatId, $messageId, $text, [ 'reply_markup' => $keyboard ]); }
            else { $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]); }
            return;
        }
        if (strpos($data, 'cart:inc:') === 0 || strpos($data, 'cart:dec:') === 0 || strpos($data, 'cart:rm:') === 0) {
            $svc = new CartService();
            $productId = (int) preg_replace('#^cart:(?:inc|dec|rm):#','',$data);
            if ($productId <= 0) { return; }
            if (str_starts_with($data, 'cart:rm:')) { $res = $svc->remove($chatId, $productId); }
            else {
                $cart = $svc->getCart($chatId); $cur = 0.0;
                if ($cart && !empty($cart->products)) { foreach ($cart->products as $key => $p) { if ((int)($p->id ?? 0) === $productId) { $cur = (float)($p->order['quantity'] ?? 1); break; } } }
                $newQty = $cur + (str_starts_with($data, 'cart:inc:') ? 1 : -1);
                if ($newQty <= 0) { $res = $svc->remove($chatId, $productId); }
                else { $res = $svc->setQuantity($chatId, $productId, $newQty); }
            }
            if ($res === false) { $this->client->answerCallbackQuery($id, Text::_('COM_RADICALMART_TELEGRAM_CART_UPDATE_ERROR'), true); return; }
            $cart = $res['cart'] ?? null;
            $text = $svc->renderCartMessage($cart);
            $keyboard = $svc->getKeyboard($cart, rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart_telegram&view=app&layout=tgwebapp&chat=' . $chatId);
            if ($messageId) { $this->client->editMessageText($chatId, $messageId, $text, [ 'reply_markup' => $keyboard ]); }
            else { $this->client->sendMessage($chatId, $text, [ 'reply_markup' => $keyboard ]); }
            return;
        }
        $this->client->sendMessage($chatId, Text::sprintf('COM_RADICALMART_TELEGRAM_CMD_ECHO', $data));
    }
}

