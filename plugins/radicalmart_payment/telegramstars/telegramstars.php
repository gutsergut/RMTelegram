<?php
/**
 * @package    PlgRadicalMart_PaymentTelegramstars
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

class PlgRadicalMart_PaymentTelegramstars extends CMSPlugin
{
    protected $autoloadLanguage = true;
    protected $db;

    private function parseCsvIds(string $csv): array
    {
        if ($csv === '') return [];
        $out = [];
        foreach (explode(',', $csv) as $p) {
            $p = trim($p);
            if ($p !== '' && ctype_digit($p)) { $out[] = (int) $p; }
        }
        return array_values(array_unique($out));
    }

    private function productCategoryIds(object $product): array
    {
        $ids = [];
        // Try common shapes
        if (!empty($product->category) && is_object($product->category) && !empty($product->category->id)) {
            $ids[] = (int) $product->category->id;
        }
        if (!empty($product->categories) && is_array($product->categories)) {
            foreach ($product->categories as $c) {
                if (is_array($c) && !empty($c['id']) && is_numeric($c['id'])) { $ids[] = (int) $c['id']; }
                if (is_object($c) && !empty($c->id)) { $ids[] = (int) $c->id; }
            }
        }
        if (!empty($product->category_id) && is_numeric($product->category_id)) { $ids[] = (int) $product->category_id; }
        return array_values(array_unique($ids));
    }

    private function isAllowedByCategories(object $order): bool
    {
        $allowedCsv = (string) $this->params->get('allowed_categories', '');
        $allowed = $this->parseCsvIds($allowedCsv);
        $excluded = $this->parseCsvIds((string) $this->params->get('excluded_categories', ''));
        if (empty($allowed)) return true; // no restriction
        if (empty($order->products) || !is_array($order->products)) return true; // cannot determine -> allow
        foreach ($order->products as $prod) {
            $ids = $this->productCategoryIds($prod);
            if (!empty($ids)) {
                // If any of product categories in allowed list, accept; otherwise reject
                $ok = false;
                foreach ($ids as $cid) { if (in_array($cid, $allowed, true)) { $ok = true; break; } }
                // Check excludes override
                foreach ($ids as $cid) { if (in_array($cid, $excluded, true)) { $ok = false; break; } }
                if (!$ok) return false;
            }
        }
        return true;
    }

    private function isAllowedByProducts(object $order): bool
    {
        $allowed = $this->parseCsvIds((string) $this->params->get('allowed_products', ''));
        $excluded = $this->parseCsvIds((string) $this->params->get('excluded_products', ''));
        if (empty($allowed) && empty($excluded)) return true;
        if (empty($order->products) || !is_array($order->products)) return true;
        foreach ($order->products as $prod) {
            $pid = (int) ($prod->id ?? 0);
            if ($pid <= 0) continue;
            if (!empty($allowed) && !in_array($pid, $allowed, true)) return false;
            if (!empty($excluded) && in_array($pid, $excluded, true)) return false;
        }
        return true;
    }

    public function onRadicalMartGetPaymentMethods(string $context, object $method, array $formData, array $products, array $currency)
    {
        // Default visible; categories restriction is enforced later on pay
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
        // Availability checks by categories/products
        return $this->isAllowedByCategories($order) && $this->isAllowedByProducts($order);
    }

    public function onRadicalMartPay(object $order, array $links, Registry $params): array
    {
        // Compute stars amount from RUB using rub_per_star and conversion percent
        $rub = 0.0;
        if (!empty($order->total['final'])) { $rub = (float) $order->total['final']; }
        elseif (!empty($order->total['final_string'])) {
            $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
            $num = str_replace(' ', '', $num); $num = str_replace(',', '.', $num); $rub = (float) $num;
        }
        $rubPerStar = (float) $this->params->get('rub_per_star', 1.0);
        $percent    = (float) $this->params->get('conversion_percent', 0);
        if ($rubPerStar <= 0) { $rubPerStar = 1.0; }
        $rub = $rub * (1.0 + ($percent/100.0));
        $stars = (int) round($rub / $rubPerStar);
        if ($stars <= 0) {
            return ['pay_instant' => false, 'link' => \Joomla\CMS\Uri\Uri::root(), 'page_title' => 'Telegram Stars', 'page_message' => 'Неверная сумма счёта'];
        }

        // Send Stars invoice (currency XTR)
        try {
            $token = (string) \Joomla\CMS\Factory::getApplication()->getParams('com_radicalmart_telegram')->get('bot_token', '');
            $db = $this->db;
            $q = $db->getQuery(true)->select('chat_id')->from($db->quoteName('#__radicalmart_telegram_users'))->where($db->quoteName('user_id') . ' = :uid')->bind(':uid', (int) ($order->created_by ?? 0));
            $chatId = (int) $db->setQuery($q, 0, 1)->loadResult();
            if ($token && $chatId > 0) {
                $http = new \Joomla\CMS\Http\Http();
                $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
                $url = 'https://api.telegram.org/bot' . $token . '/sendInvoice';
                $title = 'Заказ ' . ($order->number ?? ('#' . (int)$order->id));
                $desc  = 'Оплата Telegram Stars';
                $payload = 'order:' . (string) ($order->number ?? (string) $order->id);
                $prices = json_encode([[ 'label' => $title, 'amount' => $stars ]], JSON_UNESCAPED_UNICODE);
                $paramsReq = [ 'chat_id'=>$chatId, 'title'=>$title, 'description'=>$desc, 'payload'=>$payload, 'provider_token'=>'', 'currency'=>'XTR', 'prices'=>$prices ];
                $http->post($url, $paramsReq, ['Content-Type' => 'application/x-www-form-urlencoded']);
            }
        } catch (\Throwable $e) {}

        return ['pay_instant' => false, 'link' => \Joomla\CMS\Uri\Uri::root(), 'page_title' => 'Telegram Stars', 'page_message' => 'Счёт на оплату отправлен в чат'];
    }

    /**
     * Возвраты для Telegram Stars не поддерживаются.
     * Возвращаем отрицательный результат для административного инструмента возвратов.
     *
     * @param  object $order   Заказ RadicalMart
     * @param  float  $amount  Сумма возврата
     * @return array           ['ok'=>bool, 'message'=>string]
     */
    public function onRadicalMartPaymentRefund(object $order, float $amount): array
    {
        return [
            'ok' => false,
            'message' => 'Refund для Telegram Stars не поддерживается',
        ];
    }
}
