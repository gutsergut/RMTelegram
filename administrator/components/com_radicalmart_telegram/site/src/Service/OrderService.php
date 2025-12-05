<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис заказов - getOrders(), sendInvoice()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as AdminOrderModel;

class OrderService
{
    /**
     * Get orders with pagination and optional status filter
     */
    public function getOrders(int $chatId, int $page = 1, int $limit = 10, ?int $status = null): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get user_id from chat
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

        if ($userId <= 0) {
            return ['items' => [], 'has_more' => false, 'page' => $page, 'statuses' => []];
        }

        // Build orders query
        $q2 = $db->getQuery(true)
            ->select(['id', 'number'])
            ->from($db->quoteName('#__radicalmart_orders'))
            ->where($db->quoteName('created_by') . ' = :uid')
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('id') . ' DESC')
            ->bind(':uid', $userId);

        if ($status !== null) {
            $q2->where($db->quoteName('status') . ' = :st')->bind(':st', $status);
        }

        $offset = ($page - 1) * $limit;
        $db->setQuery($q2, $offset, $limit + 1);
        $rows = $db->loadAssocList() ?: [];

        $hasMore = false;
        if (count($rows) > $limit) {
            $hasMore = true;
            array_pop($rows);
        }

        // Load order details via AdminOrderModel
        $items = [];
        $omodel = new AdminOrderModel();

        foreach ($rows as $r) {
            $order = $omodel->getItem((int)$r['id']);
            if (!$order || empty($order->id)) continue;

            $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
            $canResend = ($plugin !== '' && stripos($plugin, 'telegram') !== false);

            $items[] = [
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

        // Load statuses list
        $statuses = $this->getStatuses();

        return ['items' => $items, 'has_more' => $hasMore, 'page' => $page, 'statuses' => $statuses];
    }

    /**
     * Get available order statuses
     */
    public function getStatuses(): array
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $qs = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__radicalmart_statuses'))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC');
            return $db->setQuery($qs)->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Send Telegram invoice for an order
     */
    public function sendInvoice(int $chatId, string $orderNumber): array
    {
        $app = Factory::getApplication();

        // Load order by number
        $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
        $order = $pm->getOrder($orderNumber, 'number');
        if (!$order || empty($order->id)) {
            throw new \RuntimeException('Order not found');
        }

        // Check it's telegram payment
        $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
        if ($plugin === '' || stripos($plugin, 'telegram') === false) {
            throw new \RuntimeException('Payment method is not Telegram');
        }

        // Resolve provider token from component settings
        $params = $app->getParams('com_radicalmart_telegram');
        $provider = (string) $params->get('provider_cards', 'yookassa');
        $env = (string) $params->get('payments_env', 'test');
        $ptoken = '';

        if (stripos($plugin, 'telegramstars') === false) {
            if ($provider === 'yookassa') {
                $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
            } else {
                $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
            }
            if ($ptoken === '') {
                throw new \RuntimeException('Missing provider token');
            }
        }

        // Get order details via AdminOrderModel
        $adm = new AdminOrderModel();
        $ord = $adm->getItem((int) $order->id);
        if (!$ord || empty($ord->id)) {
            throw new \RuntimeException('Order not found');
        }

        $title = 'Заказ ' . ($ord->number ?? ('#' . (int) $ord->id));
        $desc = 'Оплата заказа в магазине';
        $payload = 'order:' . (string) ($ord->number ?? (string) $ord->id);

        $tg = new TelegramClient();
        if (!$tg->isConfigured()) {
            throw new \RuntimeException('Telegram not configured');
        }

        if (stripos($plugin, 'telegramstars') !== false) {
            $stars = $this->calculateStars($ord);
            if ($stars <= 0) {
                throw new \RuntimeException('Bad amount');
            }
            $tg->sendInvoice((int) $chatId, $title, $desc, $payload, '', 'XTR', $stars, []);
        } else {
            $currency = (!empty($ord->currency['code'])) ? (string) $ord->currency['code'] : 'RUB';
            $amountMinor = $this->calculateAmountMinor($ord);
            if ($amountMinor <= 0) {
                throw new \RuntimeException('Bad amount');
            }
            $tg->sendInvoice((int) $chatId, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
        }

        return ['ok' => true];
    }

    /**
     * Calculate Telegram Stars amount from order
     */
    private function calculateStars($order): int
    {
        $rub = 0.0;
        if (!empty($order->total['final'])) {
            $rub = (float) $order->total['final'];
        } elseif (!empty($order->total['final_string'])) {
            $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
            $num = str_replace([' ', ','], ['', '.'], $num);
            $rub = (float) $num;
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
        if ($paramsJson !== '') {
            try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) {}
        }

        $rubPerStar = isset($conf['rub_per_star']) ? (float) $conf['rub_per_star'] : 1.0;
        $percent = isset($conf['conversion_percent']) ? (float) $conf['conversion_percent'] : 0.0;
        if ($rubPerStar <= 0) { $rubPerStar = 1.0; }

        $rub = $rub * (1.0 + ($percent / 100.0));
        return (int) round($rub / $rubPerStar);
    }

    /**
     * Calculate amount in minor units (kopecks) for card payment
     */
    private function calculateAmountMinor($order): int
    {
        if (!empty($order->total['final'])) {
            return (int) round(((float) $order->total['final']) * 100);
        }
        if (!empty($order->total['final_string'])) {
            $num = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
            $num = str_replace([' ', ','], ['', '.'], $num);
            return (int) round(((float) $num) * 100);
        }
        return 0;
    }
}
