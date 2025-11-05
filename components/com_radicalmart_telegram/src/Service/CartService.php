<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Site\Model\CartModel;

class CartService
{
    protected SessionStore $store;

    public function __construct()
    {
        $this->store = new SessionStore();
    }

    protected function ensureModelState(CartModel $model, int $chatId): void
    {
        [$state, $payload] = $this->store->getStatePayload($chatId);
        $cart = $payload['cart'] ?? [];
        if (!empty($cart['id'])) {
            $model->setState('cart.id', (int) $cart['id']);
        }
        if (!empty($cart['code'])) {
            $model->setState('cart.code', (string) $cart['code']);
        }
    }

    protected function persistCartFromResult(int $chatId, array $result): void
    {
        $cartObj = $result['cart'] ?? false;
        if ($cartObj && is_object($cartObj)) {
            [$state, $payload] = $this->store->getStatePayload($chatId);
            $payload['cart'] = [
                'id' => (int) ($cartObj->id ?? 0),
                'code' => (string) ($cartObj->code ?? ''),
            ];
            $this->store->setState($chatId, $state ?: 'cart', $payload);
        }
    }

    public function addProduct(int $chatId, int $productId, float $quantity = 1.0): array|false
    {
        $model = new CartModel();
        $this->ensureModelState($model, $chatId);

        $res = $model->addProduct($productId, $quantity, []);
        if ($res === false) {
            return false;
        }

        $this->persistCartFromResult($chatId, $res);
        return $res;
    }

    public function getCart(int $chatId)
    {
        $model = new CartModel();
        $this->ensureModelState($model, $chatId);
        // Use state to retrieve cart
        return $model->getItem();
    }

    public function setQuantity(int $chatId, int $productId, float $quantity)
    {
        $model = new CartModel();
        $this->ensureModelState($model, $chatId);
        $res = $model->setProductQuantity($productId, $quantity, []);
        if ($res === false) return false;
        $this->persistCartFromResult($chatId, $res);
        return $res;
    }

    public function remove(int $chatId, int $productId)
    {
        $model = new CartModel();
        $this->ensureModelState($model, $chatId);
        $res = $model->removeProduct($productId, []);
        if ($res === false) return false;
        $this->persistCartFromResult($chatId, $res);
        return $res;
    }

    public function getKeyboard($cart, string $webAppUrl): array
    {
        $rows = [];
        if ($cart && !empty($cart->products)) {
            $count = 0;
            foreach ($cart->products as $key => $p) {
                $id = (int) ($p->id ?? 0);
                if ($id <= 0) continue;
                // Ограничим управление для первых 5 позиций, чтобы не перегружать клавиатуру
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
