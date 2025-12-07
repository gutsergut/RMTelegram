<?php
/*
 * @package     com_radicalmart_telegram (site)
 *
 * Нативная интеграция с корзиной RadicalMart
 * Корзина привязывается к user_id через TelegramUserHelper
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\ParameterType;
use Joomla\Component\RadicalMart\Site\Model\CartModel;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;

class CartService
{
    /**
     * Авторизует Joomla пользователя в сессии на основе chat_id
     * Это позволяет CartModel загружать корзину по user_id
     * @return int|null user_id если авторизовали, null если не удалось
     */
    protected function loginTelegramUser(int $chatId): ?int
    {
        if ($chatId <= 0) {
            return null;
        }

        // Получаем user_id связанного с этим chat_id
        $userId = TelegramUserHelper::getUserIdByChatId($chatId);
        if ($userId <= 0) {
            LogHelper::debug('CartService::loginTelegramUser chat=' . $chatId . ' - no linked user');
            return null;
        }

        // Проверяем текущего пользователя Joomla
        $currentUser = Factory::getUser();
        if (!$currentUser->guest && (int)$currentUser->id === $userId) {
            // Уже авторизован нужный пользователь
            LogHelper::debug('CartService::loginTelegramUser chat=' . $chatId . ' - user ' . $userId . ' already logged in');
            return $userId;
        }

        // Загружаем пользователя
        $user = User::getInstance($userId);
        if ($user->guest || (int)$user->id <= 0) {
            LogHelper::warning('CartService::loginTelegramUser chat=' . $chatId . ' - user ' . $userId . ' not found');
            return null;
        }

        // Авторизуем пользователя в сессии Joomla (без реального логина)
        // Это позволит CartModel::getCurrentUser() вернуть правильного пользователя
        $app = Factory::getApplication();
        $session = $app->getSession();

        // Устанавливаем пользователя в сессию
        $session->set('user', $user);

        // Также обновляем identity в приложении
        $app->loadIdentity($user);

        LogHelper::debug('CartService::loginTelegramUser chat=' . $chatId . ' - logged in user ' . $userId . ' (' . $user->username . ')');

        return $userId;
    }

    /**
     * Добавить товар в корзину
     * Корзина привязывается к user_id если пользователь связан
     */
    public function addProduct(int $chatId, int $productId, float $quantity = 1.0): array|false
    {
        LogHelper::debug('CartService::addProduct chatId=' . $chatId . ' productId=' . $productId . ' qty=' . $quantity);

        // Авторизуем пользователя для привязки корзины
        $linkedUserId = $this->loginTelegramUser($chatId);
        LogHelper::debug('CartService::addProduct linkedUserId=' . ($linkedUserId ?? 'null'));

        $model = new CartModel();

        $res = $model->addProduct($productId, $quantity, []);
        if ($res === false) {
            $errors = $model->getErrors();
            LogHelper::warning('CartService::addProduct FAILED: ' . json_encode($errors));
            return false;
        }

        // Сохраняем cookies как в оригинальном CartController
        if (!empty($res['cart']) && is_object($res['cart'])) {
            $model->setCookies((int) $res['cart']->id, (string) $res['cart']->code);
            LogHelper::debug('CartService::addProduct SUCCESS cart.id=' . $res['cart']->id . ' user_id=' . ($res['cart']->user_id ?? 'null'));
        }

        return $res;
    }

    /**
     * Получить корзину
     * Загружает корзину по user_id если пользователь связан
     * ВАЖНО: Принудительно ищем корзину по user_id, игнорируя старые cookies
     */
    public function getCart(int $chatId)
    {
        LogHelper::debug('CartService::getCart chatId=' . $chatId);

        // Авторизуем пользователя для загрузки его корзины
        $linkedUserId = $this->loginTelegramUser($chatId);
        LogHelper::debug('CartService::getCart linkedUserId=' . ($linkedUserId ?? 'null'));

        $model = new CartModel();

        // Если пользователь связан — найти его последнюю корзину напрямую
        // Это обходит проблему со старыми cookies в Telegram WebApp контексте
        if ($linkedUserId) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(['id', 'code'])
                ->from($db->quoteName('#__radicalmart_carts'))
                ->where($db->quoteName('user_id') . ' = :userId')
                ->order($db->quoteName('modified') . ' DESC')
                ->setLimit(1);
            $query->bind(':userId', $linkedUserId, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $userCart = $db->loadObject();

            if ($userCart && $userCart->id > 0) {
                LogHelper::debug('CartService::getCart found user cart id=' . $userCart->id . ' code=' . $userCart->code);
                // Устанавливаем cookies на правильную корзину
                $model->setCookies((int) $userCart->id, (string) $userCart->code);
            } else {
                LogHelper::debug('CartService::getCart no cart found for user ' . $linkedUserId);
            }
        }

        $cart = $model->getItem();

        LogHelper::debug('CartService::getCart result=' . ($cart ? 'cart.id=' . ($cart->id ?? 'null') . ' user_id=' . ($cart->user_id ?? 'null') . ' products=' . (isset($cart->products) ? count($cart->products) : 0) : 'false/null'));

        return $cart;
    }

    /**
     * Установить количество товара
     */
    public function setQuantity(int $chatId, int $productId, float $quantity)
    {
        LogHelper::debug('CartService::setQuantity chatId=' . $chatId . ' productId=' . $productId . ' qty=' . $quantity);

        // Авторизуем пользователя
        $linkedUserId = $this->loginTelegramUser($chatId);

        $model = new CartModel();
        $res = $model->setProductQuantity($productId, $quantity, []);
        if ($res === false) {
            return false;
        }

        // Обновляем cookies
        if (!empty($res['cart']) && is_object($res['cart'])) {
            $model->setCookies((int) $res['cart']->id, (string) $res['cart']->code);
        }

        return $res;
    }

    /**
     * Удалить товар из корзины
     */
    public function remove(int $chatId, int $productId)
    {
        LogHelper::debug('CartService::remove chatId=' . $chatId . ' productId=' . $productId);

        // Авторизуем пользователя
        $linkedUserId = $this->loginTelegramUser($chatId);

        $model = new CartModel();
        $res = $model->removeProduct($productId, []);
        if ($res === false) {
            return false;
        }

        // Обновляем cookies
        if (!empty($res['cart']) && is_object($res['cart'])) {
            $model->setCookies((int) $res['cart']->id, (string) $res['cart']->code);
        }

        return $res;
    }

    /**
     * Клавиатура для Telegram бота
     */
    public function getKeyboard($cart, string $webAppUrl): array
    {
        $rows = [];
        if ($cart && !empty($cart->products)) {
            $count = 0;
            foreach ($cart->products as $key => $p) {
                $id = (int) ($p->id ?? 0);
                if ($id <= 0) continue;
                if ($count++ >= 5) break;
                $rows[] = [
                    [ 'text' => '−', 'callback_data' => 'cart:dec:' . $id ],
                    [ 'text' => 'Удалить', 'callback_data' => 'cart:rm:' . $id ],
                    [ 'text' => '+', 'callback_data' => 'cart:inc:' . $id ],
                ];
            }
        }
        $rows[] = [
            [ 'text' => 'Оформить', 'web_app' => [ 'url' => $webAppUrl . '#checkout' ] ],
            [ 'text' => 'Каталог',  'callback_data' => 'catalog:page:1' ],
        ];
        return [ 'inline_keyboard' => $rows ];
    }

    /**
     * Текстовое сообщение корзины для Telegram
     */
    public function renderCartMessage($cart): string
    {
        if (!$cart) {
            return 'Корзина пуста.';
        }
        $lines = [];
        $lines[] = 'Корзина:';
        $i = 1;
        if (!empty($cart->products)) {
            foreach ($cart->products as $key => $p) {
                $title = (string) ($p->title ?? 'Товар');
                $qty   = (string) ($p->order['quantity_string_short'] ?? $p->order['quantity'] ?? '1');
                $sum   = (string) ($p->order['sum_final_string'] ?? '');
                $lines[] = $i++ . '. ' . $title . ' × ' . $qty . (($sum !== '') ? (' — ' . $sum) : '');
            }
        }
        if (!empty($cart->total)) {
            $total = $cart->total['final_string'] ?? '';
            if ($total !== '') {
                $lines[] = '';
                $lines[] = 'Итого: ' . $total;
            }
        }
        return implode("\n", $lines);
    }
}
