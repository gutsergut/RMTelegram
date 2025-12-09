<?php
/*
 * @package     com_radicalmart_telegram (site)
 * Points history JSON view for AJAX loading
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Points;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

class JsonView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $app = Factory::getApplication();
        $start = $app->input->getInt('start', 0);
        $limit = 10;

        // Получаем текущего пользователя
        $tgUser = TelegramUserHelper::getCurrentUser();
        $userId = $tgUser['user_id'] ?? 0;

        $result = array(
            'items' => array(),
            'hasMore' => false,
            'start' => $start
        );

        if ($userId <= 0) {
            echo json_encode($result);
            $app->close();
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // customer_id = user_id в RadicalMart
            $customerId = (int) $userId;

            if ($customerId > 0) {
                // Загружаем историю баллов
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__radicalmart_bonuses_points'))
                    ->where($db->quoteName('customer') . ' = ' . (int) $customerId)
                    ->order($db->quoteName('created') . ' DESC')
                    ->setLimit($limit + 1, $start);
                $db->setQuery($query);
                $items = $db->loadObjectList();

                // Проверяем есть ли еще записи
                if (count($items) > $limit) {
                    $result['hasMore'] = true;
                    array_pop($items);
                }

                // Форматируем для JSON
                foreach ($items as $item) {
                    $result['items'][] = array(
                        'id' => $item->id,
                        'points' => $item->points,
                        'points_formatted' => number_format($item->points, 0, ',', ' '),
                        'context' => $item->context,
                        'context_label' => $this->getContextLabel($item),
                        'date' => HTMLHelper::date($item->created, 'd.m.Y'),
                        'time' => HTMLHelper::date($item->created, 'H:i'),
                        'created' => $item->created
                    );
                }
            }
        } catch (\Throwable $e) {
            // В случае ошибки возвращаем пустой результат
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * Получить контекст операции для отображения
     */
    protected function getContextLabel($item)
    {
        $context = $item->context ?? '';
        $data = $item->data ? json_decode($item->data, true) : array();

        if (strpos($context, 'order') !== false) {
            $orderId = isset($data['order_id']) ? $data['order_id'] : '';
            if ($item->points > 0) {
                return Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_ORDER_CREDIT', $orderId);
            } else {
                return Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_ORDER_DEBIT', $orderId);
            }
        }

        if (strpos($context, 'referral') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_REFERRAL');
        }

        if (strpos($context, 'refund') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_REFUND');
        }

        if (strpos($context, 'manual') !== false || strpos($context, 'admin') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_MANUAL');
        }

        if (strpos($context, 'expire') !== false || strpos($context, 'burn') !== false) {
            return Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_EXPIRED');
        }

        return $context ? $context : Text::_('COM_RADICALMART_TELEGRAM_POINTS_CONTEXT_OTHER');
    }
}
