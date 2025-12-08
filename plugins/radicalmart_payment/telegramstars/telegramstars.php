<?php
/**
 * @package    PlgRadicalMart_PaymentTelegramstars
 * Telegram Stars payment plugin for RadicalMart
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

Log::addLogger([
    'text_file' => 'plg_radicalmart_payment_telegramstars.php',
], Log::ALL, ['plg_radicalmart_payment_telegramstars']);

class PlgRadicalMart_PaymentTelegramstars extends CMSPlugin
{
    protected $autoloadLanguage = true;
    protected $db;

    /**
     * Parse IDs from parameter (supports both CSV string and array)
     */
    private function parseIds($value): array
    {
        if (empty($value)) return [];

        // If already array (from multiple select field)
        if (is_array($value)) {
            return array_map('intval', array_filter($value, 'is_numeric'));
        }

        // Legacy CSV format
        $out = [];
        foreach (explode(',', (string) $value) as $p) {
            $p = trim($p);
            if ($p !== '' && is_numeric($p)) {
                $out[] = (int) $p;
            }
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
        $allowed = $this->parseIds($this->params->get('allowed_categories', []));
        $excluded = $this->parseIds($this->params->get('excluded_categories', []));
        if (empty($allowed) && empty($excluded)) return true; // no restriction
        if (empty($order->products) || !is_array($order->products)) return true; // cannot determine -> allow
        foreach ($order->products as $prod) {
            $ids = $this->productCategoryIds($prod);
            if (!empty($ids)) {
                // If allowed list is set, check if any product category is in allowed list
                if (!empty($allowed)) {
                    $ok = false;
                    foreach ($ids as $cid) {
                        if (in_array($cid, $allowed, true)) {
                            $ok = true;
                            break;
                        }
                    }
                    if (!$ok) return false;
                }
                // Check excludes override
                foreach ($ids as $cid) {
                    if (in_array($cid, $excluded, true)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function isAllowedByProducts(object $order): bool
    {
        $allowed = $this->parseIds($this->params->get('allowed_products', []));
        $excluded = $this->parseIds($this->params->get('excluded_products', []));
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

    /**
     * Check if current request is from Telegram WebApp
     */
    private function isTelegramContext(): bool
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        // Check if we're in com_radicalmart_telegram component
        $option = $input->getCmd('option', '');
        if ($option === 'com_radicalmart_telegram') {
            return true;
        }

        // Check for Telegram WebApp headers/params
        $initData = $input->getString('initData', '') ?: $input->server->getString('HTTP_X_TELEGRAM_INIT_DATA', '');
        if (!empty($initData)) {
            return true;
        }

        // Check tmpl=tgwebapp or tmpl=webapp
        $tmpl = $input->getCmd('tmpl', '');
        if (in_array($tmpl, ['tgwebapp', 'webapp'], true)) {
            return true;
        }

        return false;
    }

    public function onRadicalMartGetPaymentMethods(string $context, object $method, array $formData, array $products, array $currency)
    {
        // Check bot_only setting - hide on website if enabled
        $botOnly = (int) $this->params->get('bot_only', 1);
        if ($botOnly && !$this->isTelegramContext()) {
            // Hide this payment method on website
            $method->disabled = true;
            return;
        }

        // Default visible
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
        // Check if user has linked Telegram chat
        $chatId = $this->findChatId((int) ($order->created_by ?? 0));
        if ($chatId <= 0) {
            Log::add('TelegramStars: user ' . ($order->created_by ?? 0) . ' has no linked Telegram chat', Log::WARNING, 'plg_radicalmart_payment_telegramstars');
            return false;
        }
        // Availability checks by categories/products
        return $this->isAllowedByCategories($order) && $this->isAllowedByProducts($order);
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

    public function onRadicalMartPay(object $order, array $links, Registry $params): array
    {
        $result = [
            'pay_instant' => false,
            'link' => \Joomla\CMS\Uri\Uri::root(),
            'page_title' => 'Telegram Stars',
            'page_message' => 'Счёт на оплату отправлен в Telegram'
        ];

        // Compute stars amount from RUB using rub_per_star and conversion percent
        $rub = 0.0;
        if (!empty($order->total['final'])) {
            $rub = (float) $order->total['final'];
        } elseif (!empty($order->total['final_string'])) {
            $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
            $num = str_replace(' ', '', $num);
            $num = str_replace(',', '.', $num);
            $rub = (float) $num;
        }

        $rubPerStar = (float) $this->params->get('rub_per_star', 1.0);
        $percent    = (float) $this->params->get('conversion_percent', 0);
        if ($rubPerStar <= 0) { $rubPerStar = 1.0; }

        $rubWithMarkup = $rub * (1.0 + ($percent / 100.0));
        $stars = (int) round($rubWithMarkup / $rubPerStar);

        Log::add('TelegramStars: order=' . ($order->number ?? $order->id) . ', rub=' . $rub . ', rubPerStar=' . $rubPerStar . ', percent=' . $percent . ', stars=' . $stars, Log::INFO, 'plg_radicalmart_payment_telegramstars');

        if ($stars <= 0) {
            Log::add('TelegramStars: invalid stars amount: ' . $stars, Log::WARNING, 'plg_radicalmart_payment_telegramstars');
            $result['page_message'] = 'Неверная сумма счёта';
            return $result;
        }

        // Send Stars invoice (currency XTR)
        try {
            $token = (string) Factory::getApplication()->getParams('com_radicalmart_telegram')->get('bot_token', '');
            $chatId = $this->findChatId((int) ($order->created_by ?? 0));

            if (empty($token)) {
                Log::add('TelegramStars: bot_token is empty', Log::ERROR, 'plg_radicalmart_payment_telegramstars');
                $result['page_message'] = 'Ошибка конфигурации бота';
                return $result;
            }

            if ($chatId <= 0) {
                Log::add('TelegramStars: no chat_id for user ' . ($order->created_by ?? 0), Log::WARNING, 'plg_radicalmart_payment_telegramstars');
                $result['page_message'] = 'Telegram не привязан к аккаунту';
                return $result;
            }

            $http = new \Joomla\CMS\Http\Http();
            $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);

            $url = 'https://api.telegram.org/bot' . $token . '/sendInvoice';
            $title = 'Заказ ' . ($order->number ?? ('#' . (int)$order->id));
            $desc  = '⭐ Оплата ' . $stars . ' Stars';
            $payload = 'order:' . (string) ($order->number ?? (string) $order->id);
            $prices = json_encode([[ 'label' => $title, 'amount' => $stars ]], JSON_UNESCAPED_UNICODE);

            $paramsReq = [
                'chat_id' => $chatId,
                'title' => $title,
                'description' => $desc,
                'payload' => $payload,
                'provider_token' => '',  // Empty for Telegram Stars
                'currency' => 'XTR',
                'prices' => $prices
            ];

            $response = $http->post($url, $paramsReq, ['Content-Type' => 'application/x-www-form-urlencoded']);
            $body = json_decode($response->body, true);

            if (!empty($body['ok'])) {
                Log::add('TelegramStars: invoice sent to chat ' . $chatId . ' for ' . $stars . ' stars', Log::INFO, 'plg_radicalmart_payment_telegramstars');
                $result['page_message'] = '⭐ Счёт на ' . $stars . ' Stars отправлен в Telegram';
            } else {
                $error = $body['description'] ?? 'Unknown error';
                Log::add('TelegramStars: sendInvoice failed: ' . $error, Log::ERROR, 'plg_radicalmart_payment_telegramstars');
                $result['page_message'] = 'Ошибка отправки счёта: ' . $error;
            }

        } catch (\Throwable $e) {
            Log::add('TelegramStars: exception: ' . $e->getMessage(), Log::ERROR, 'plg_radicalmart_payment_telegramstars');
            $result['page_message'] = 'Ошибка: ' . $e->getMessage();
        }

        return $result;
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

    /**
     * Get current Star rate from Telegram or external source
     *
     * Telegram Stars pricing (as of 2024):
     * - 1 Star ≈ $0.02 USD (Telegram takes 30% commission)
     * - User pays ~$0.013 per Star for purchases
     * - Bot owner receives ~70% of Star value
     *
     * For RUB conversion, we use CBR rate USD/RUB
     *
     * @return float|null Rate in RUB per Star, or null if failed
     */
    public function fetchCurrentRate(): ?float
    {
        try {
            // Get USD/RUB rate from CBR
            $http = new \Joomla\CMS\Http\Http();
            $response = $http->get('https://www.cbr-xml-daily.ru/daily_json.js');

            if ($response->code !== 200) {
                Log::add('TelegramStars: CBR API error, code=' . $response->code, Log::WARNING, 'plg_radicalmart_payment_telegramstars');
                return null;
            }

            $data = json_decode($response->body, true);
            if (empty($data['Valute']['USD']['Value'])) {
                Log::add('TelegramStars: CBR response missing USD rate', Log::WARNING, 'plg_radicalmart_payment_telegramstars');
                return null;
            }

            $usdRub = (float) $data['Valute']['USD']['Value'];

            // 1 Star ≈ $0.02 USD (Telegram's approximate rate, configurable)
            $starUsd = (float) $this->params->get('star_usd_rate', 0.02);
            if ($starUsd <= 0) {
                $starUsd = 0.02;
            }
            $rubPerStar = round($starUsd * $usdRub, 2);

            Log::add('TelegramStars: fetched rate USD/RUB=' . $usdRub . ', starUsd=' . $starUsd . ', rubPerStar=' . $rubPerStar, Log::INFO, 'plg_radicalmart_payment_telegramstars');

            return $rubPerStar;

        } catch (\Throwable $e) {
            Log::add('TelegramStars: fetchCurrentRate error: ' . $e->getMessage(), Log::ERROR, 'plg_radicalmart_payment_telegramstars');
            return null;
        }
    }

    /**
     * Update rate in plugin params (called from task or manually)
     */
    public function updateRate(): bool
    {
        $rate = $this->fetchCurrentRate();
        if ($rate === null) {
            return false;
        }

        try {
            $db = $this->db;

            // Get current plugin params
            $query = $db->getQuery(true)
                ->select('params')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'));

            $paramsJson = $db->setQuery($query)->loadResult();
            $params = new Registry($paramsJson ?: '{}');

            // Update rate and timestamp
            $params->set('rub_per_star', $rate);
            $params->set('rate_last_updated', (new \DateTime())->format('Y-m-d H:i:s'));

            // Save back
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
                ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'));

            $db->setQuery($update)->execute();

            // Update local params
            $this->params->set('rub_per_star', $rate);
            $this->params->set('rate_last_updated', $params->get('rate_last_updated'));

            Log::add('TelegramStars: rate updated to ' . $rate . ' RUB/Star', Log::INFO, 'plg_radicalmart_payment_telegramstars');

            return true;

        } catch (\Throwable $e) {
            Log::add('TelegramStars: updateRate save error: ' . $e->getMessage(), Log::ERROR, 'plg_radicalmart_payment_telegramstars');
            return false;
        }
    }

    /**
     * Hook for scheduled task to update rate
     */
    public function onTaskTelegramStarsUpdateRate(): bool
    {
        if (!(int) $this->params->get('auto_update_rate', 0)) {
            return true; // Auto-update disabled
        }

        return $this->updateRate();
    }
}
