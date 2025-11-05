<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\RadicalMart\Administrator\Helper\PluginsHelper;

class PaymentsController extends BaseController
{
    public function refund(): void
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=payments', false));
            return;
        }
        $number = trim((string) $app->input->get('order_number', '', 'string'));
        $amount = (float) $app->input->get('amount', 0, 'float');
        $comment = trim((string) $app->input->get('comment', '', 'string'));
        try {
            if ($number === '' || $amount < 0) {
                throw new \RuntimeException('Bad params');
            }
            // Find order by number
            $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
            $order = $pm->getOrder($number, 'number');
            if (!$order || empty($order->id)) {
                throw new \RuntimeException('Order not found');
            }
            // Resolve numeric order total
            $orderTotal = 0.0;
            if (!empty($order->total['final'])) {
                $orderTotal = (float) $order->total['final'];
            } elseif (!empty($order->total['final_string'])) {
                $s = preg_replace('#[^0-9\.,]#', '', (string) $order->total['final_string']);
                $s = str_replace(' ', '', $s);
                $s = str_replace(',', '.', $s);
                $orderTotal = (float) $s;
            }
            if ($amount > 0 && $orderTotal > 0 && $amount > $orderTotal) {
                throw new \RuntimeException('Amount exceeds order total');
            }
            // Decide status
            $params = $app->getParams('com_radicalmart_telegram');
            $refunded = (int) $params->get('refunded_status_id', 0);
            $partial  = (int) $params->get('partial_refunded_status_id', 0);
            $statusId = 0;
            if ($amount > 0 && !empty($order->total['final']) && $amount < (float) $order->total['final']) {
                $statusId = $partial;
            } else {
                $statusId = $refunded;
            }
            // Try trigger refund event on payment plugin (optional)
            $refundMsg = '';
            $refundOk  = null;
            try {
                if (!empty($order->payment) && !empty($order->payment->plugin)) {
                    $event = 'onRadicalMartPaymentRefund';
                    $argc  = [$order, $amount, $params];
                    $result = PluginsHelper::triggerPlugin('radicalmart_payment', $order->payment->plugin, $event, $argc);
                    if (is_array($result)) {
                        $refundOk  = isset($result['ok']) ? (bool) $result['ok'] : null;
                        $refundMsg = isset($result['message']) ? (string) $result['message'] : '';
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            // Add log and change status
            $adm = new \Joomla\Component\RadicalMart\Administrator\Model\OrderModel();
            // Add log
            $adm->addLog((int) $order->id, 'telegram_refund', [
                'message' => 'Refund marked via admin',
                'amount'  => $amount,
                'plugin'  => 'telegram',
                'comment' => $comment,
                'provider_message' => $refundMsg,
                'provider_ok' => $refundOk,
            ]);
            if ($statusId > 0) {
                $adm->updateStatus((int) $order->id, $statusId, false, null, 'Telegram refund: ' . $comment);
            }
            if ($refundOk === false) {
                $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_REFUND_ERROR', $refundMsg ?: 'provider error'), 'error');
            } else {
                $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_REFUND_SUCCESS'), 'message');
                if ($refundMsg) {
                    $app->enqueueMessage($refundMsg, 'message');
                }
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_REFUND_ERROR', $e->getMessage()), 'error');
        }
        $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=payments', false));
    }
}

?>
