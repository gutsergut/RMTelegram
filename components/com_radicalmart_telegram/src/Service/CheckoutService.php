<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис оформления заказа - getMethods(), setPvz(), getTariffs(), setPayment()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMart\Site\Model\CheckoutModel;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;
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
        if ($shippingId > 0 && class_exists(ApiShip::class)) {
            $tariffResult = ApiShipIntegrationHelper::calculateTariff($shippingId, $cart, $pvzData['id'] ?? '', $pvzData['provider'] ?? '');
            $tariffs = $tariffResult['tariffs'] ?? [];
            if (!empty($tariffs) && !empty($tariffId)) {
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
            } else {
                unset($sessionData['shipping']['tariff']);
            }
        }
        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
        $model = new CheckoutModel();
        $model->setState('cart.id', (int) $cart->id);
        $model->setState('cart.code', (string) $cart->code);
        $order = $model->getItem();
        $orderData = $this->buildOrderData($order, $selectedTariff);
        return [
            'shipping_title' => (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '',
            'order_total' => $orderData['total']['final_string'] ?? '',
            'order' => $orderData,
            'pvz' => $sessionData['shipping']['point'],
            'tariffs' => $tariffs,
            'selected_tariff' => $selectedTariff ? $selectedTariff->tariffId : null
        ];
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
