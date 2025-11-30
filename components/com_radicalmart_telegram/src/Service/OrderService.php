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
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as AdminOrderModel;

class OrderService
{
    public function getOrders(int $chatId, int $limit = 20): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
        if ($userId <= 0) {
            return [];
        }
        $query = $db->getQuery(true)
            ->select(['o.id', 'o.number', 'o.total', 'o.state', 'o.created', 'o.shipping', 'o.payment'])
            ->from($db->quoteName('#__radicalmart_orders', 'o'))
            ->where($db->quoteName('o.created_by') . ' = :uid')
            ->bind(':uid', $userId)
            ->order($db->quoteName('o.created') . ' DESC');
        $rows = $db->setQuery($query, 0, $limit)->loadObjectList();
        $orders = [];
        foreach ($rows as $row) {
            $total = json_decode($row->total ?? '{}', true);
            $shipping = json_decode($row->shipping ?? '{}', true);
            $payment = json_decode($row->payment ?? '{}', true);
            $orders[] = [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'state' => (int) $row->state,
                'created' => (string) $row->created,
                'total' => $total['final_string'] ?? '',
                'shipping_title' => $shipping['title'] ?? '',
                'payment_title' => $payment['title'] ?? ''
            ];
        }
        return $orders;
    }

    public function sendInvoice(int $chatId, string $orderNumber, string $type = 'card'): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
        if ($userId <= 0) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_USER_NOT_LINKED'));
        }
        $query = $db->getQuery(true)
            ->select(['id', 'number', 'total', 'products'])
            ->from($db->quoteName('#__radicalmart_orders'))
            ->where($db->quoteName('number') . ' = :num')
            ->where($db->quoteName('created_by') . ' = :uid')
            ->bind(':num', $orderNumber)
            ->bind(':uid', $userId);
        $order = $db->setQuery($query, 0, 1)->loadObject();
        if (!$order) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_ORDER_NOT_FOUND'));
        }
        $telegram = new TelegramClient();
        if ($type === 'stars') {
            return $telegram->sendStarsInvoice($chatId, $order);
        } else {
            return $telegram->sendCardInvoice($chatId, $order);
        }
    }
}
