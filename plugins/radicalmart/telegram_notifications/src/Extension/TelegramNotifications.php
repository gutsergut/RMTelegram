<?php
/**
 * @package     RadicalMart Telegram Notifications Plugin
 * @subpackage  plg_radicalmart_telegram_notifications
 * @version     0.1.0
 * @author      RadicalMart Telegram
 * @copyright   Copyright (C) 2025
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Plugin\RadicalMart\TelegramNotifications\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Event\SubscriberInterface;

/**
 * Plugin to send Telegram notifications to customers about points
 *
 * @since  0.1.0
 */
class TelegramNotifications extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var    bool
     * @since  0.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     * @since   0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onRadicalMartAfterChangeOrderStatus' => 'onAfterChangeOrderStatus',
        ];
    }

    /**
     * Handle order status change - send notification about points accrual and status updates
     *
     * @param   string  $context    The context
     * @param   object  $order      The order object
     * @param   int     $oldStatus  Old status ID
     * @param   int     $newStatus  New status ID
     * @param   bool    $isNew      Is new order
     *
     * @return  void
     * @since   0.1.0
     */
    public function onAfterChangeOrderStatus(string $context, object $order, int $oldStatus, int $newStatus, bool $isNew): void
    {
        // Get bot token from component params
        $botToken = $this->getBotToken();
        if (!$botToken)
        {
            return;
        }

        // Get customer user ID
        $userId = $order->created_by ?? 0;
        if (!$userId)
        {
            return;
        }

        // Get customer's Telegram chat_id
        $chatId = $this->getCustomerChatId($userId);
        if (!$chatId)
        {
            return;
        }

        // For new orders - send creation notification
        if ($isNew)
        {
            $this->sendNewOrderNotification($order, $botToken, $chatId);
            return;
        }

        // Send status change notification
        $this->sendStatusChangeNotification($order, $oldStatus, $newStatus, $botToken, $chatId);

        // Send notification about customer points accrual (4.1)
        if (class_exists(PointsHelper::class))
        {
            $this->sendCustomerPointsNotification($order, $botToken);

            // Send notification about referral points accrual (4.2)
            $this->sendReferralPointsNotification($order, $botToken);
        }
    }

    /**
     * Send notification about new order creation
     *
     * @param   object  $order     Order object
     * @param   string  $botToken  Bot token
     * @param   int     $chatId    Chat ID
     *
     * @return  void
     * @since   0.1.0
     */
    protected function sendNewOrderNotification(object $order, string $botToken, int $chatId): void
    {
        // Check if payment is Telegram-based (cards or stars)
        $paymentPlugin = $order->payment->plugin ?? '';
        $isTelegramPayment = stripos($paymentPlugin, 'telegram') !== false;

        // Format message
        $orderNumber = $order->number ?? $order->id;
        $total = $order->total['final_string'] ?? '';

        $message = "✅ <b>Заказ №{$orderNumber} создан</b>\n\n";
        $message .= "Сумма: <b>{$total}</b>\n";

        if ($isTelegramPayment)
        {
            $message .= "\nСчёт на оплату отправлен ниже.";
        }
        else
        {
            $message .= "\nОплатите заказ для его обработки.";
        }

        // Create inline keyboard with WebApp button
        $webAppUrl = $this->getWebAppUrl() . '/index.php?option=com_radicalmart_telegram&view=order&id=' . (int) $order->id;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📦 Открыть заказ', 'web_app' => ['url' => $webAppUrl]]
                ]
            ]
        ];

        // Send notification with inline keyboard
        $this->sendTelegramMessageWithKeyboard($botToken, $chatId, $message, $keyboard);
    }

    /**
     * Send notification about order status change
     *
     * @param   object  $order      Order object
     * @param   int     $oldStatus  Old status ID
     * @param   int     $newStatus  New status ID
     * @param   string  $botToken   Bot token
     * @param   int     $chatId     Chat ID
     *
     * @return  void
     * @since   0.1.0
     */
    protected function sendStatusChangeNotification(object $order, int $oldStatus, int $newStatus, string $botToken, int $chatId): void
    {
        $orderNumber = $order->number ?? $order->id;

        // Get status title
        $statusTitle = $order->status->title ?? '';
        if (!$statusTitle)
        {
            $statusTitle = $this->getStatusTitle($newStatus);
        }

        // Determine emoji and message based on status
        $emoji = '📦';
        $isPaid = false;

        // Common status IDs: 1=New, 2=Paid, 3=Processing, 4=Shipped, 5=Delivered, 6=Cancelled
        // But better to check by title patterns
        $statusTitleLower = mb_strtolower($statusTitle);
        if (strpos($statusTitleLower, 'оплач') !== false || strpos($statusTitleLower, 'paid') !== false)
        {
            $emoji = '💳';
            $isPaid = true;
        }
        elseif (strpos($statusTitleLower, 'отправл') !== false || strpos($statusTitleLower, 'ship') !== false)
        {
            $emoji = '🚚';
        }
        elseif (strpos($statusTitleLower, 'доставл') !== false || strpos($statusTitleLower, 'deliver') !== false)
        {
            $emoji = '✅';
        }
        elseif (strpos($statusTitleLower, 'отмен') !== false || strpos($statusTitleLower, 'cancel') !== false)
        {
            $emoji = '❌';
        }
        elseif (strpos($statusTitleLower, 'обработ') !== false || strpos($statusTitleLower, 'process') !== false)
        {
            $emoji = '⚙️';
        }

        $message = "{$emoji} <b>Статус заказа №{$orderNumber} изменён</b>\n\n";
        $message .= "Новый статус: <b>{$statusTitle}</b>";

        if ($isPaid)
        {
            $message .= "\n\n🎉 Спасибо за оплату! Мы начали обработку вашего заказа.";
        }

        // Create inline keyboard with WebApp button
        $webAppUrl = $this->getWebAppUrl() . '/index.php?option=com_radicalmart_telegram&view=order&id=' . (int) $order->id;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📦 Открыть заказ', 'web_app' => ['url' => $webAppUrl]]
                ]
            ]
        ];

        // Send notification with inline keyboard
        $this->sendTelegramMessageWithKeyboard($botToken, $chatId, $message, $keyboard);
    }

    /**
     * Get status title by ID
     *
     * @param   int  $statusId  Status ID
     *
     * @return  string
     * @since   0.1.0
     */
    protected function getStatusTitle(int $statusId): string
    {
        try
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('title')
                ->from($db->quoteName('#__radicalmart_statuses'))
                ->where($db->quoteName('id') . ' = ' . $statusId);

            $db->setQuery($query);
            $title = $db->loadResult();

            return $title ? \Joomla\CMS\Language\Text::_($title) : '';
        }
        catch (\Exception $e)
        {
            return '';
        }
    }

    /**
     * Send notification to customer about points accrual
     *
     * @param   object  $order     Order object
     * @param   string  $botToken  Bot token
     *
     * @return  void
     * @since   0.1.0
     */
    protected function sendCustomerPointsNotification(object $order, string $botToken): void
    {
        // Get customer user ID
        $userId = $order->created_by ?? 0;
        if (!$userId)
        {
            return;
        }

        // Check if points were accrued for this order
        $pointsAccrued = $this->getAccruedPoints($order, 'accrual');
        if ($pointsAccrued <= 0)
        {
            return;
        }

        // Get customer's Telegram chat_id
        $chatId = $this->getCustomerChatId($userId);
        if (!$chatId)
        {
            return;
        }

        // Get current balance
        $balance = PointsHelper::getCustomerPoints($userId);
        $balanceMoney = PointsHelper::convertToMoney($balance, $order->currency['code'] ?? 'RUB');

        // Format message
        $message = $this->formatPointsAccrualMessage($order, $pointsAccrued, $balance, $balanceMoney);

        // Send notification
        $this->sendTelegramMessage($botToken, $chatId, $message);
    }

    /**
     * Send notification to referral parent about points accrual
     *
     * @param   object  $order     Order object
     * @param   string  $botToken  Bot token
     *
     * @return  void
     * @since   0.1.0
     */
    protected function sendReferralPointsNotification(object $order, string $botToken): void
    {
        // Check if bonuses plugin data exists with referral records
        if (empty($order->plugins['bonuses']['points']['records']))
        {
            return;
        }

        $records = $order->plugins['bonuses']['points']['records'];

        // Find referral accrual records and notify each parent
        foreach ($records as $record)
        {
            if (!isset($record->reason) || $record->reason !== 'referral_accrual')
            {
                continue;
            }

            $parentId = $record->parent_id ?? $record->customer_id ?? 0;
            $points = (float) ($record->value ?? 0);

            if (!$parentId || $points <= 0)
            {
                continue;
            }

            // Get parent's Telegram chat_id
            $chatId = $this->getCustomerChatId($parentId);
            if (!$chatId)
            {
                continue;
            }

            // Get parent's current balance
            $balance = PointsHelper::getCustomerPoints($parentId);
            $balanceMoney = PointsHelper::convertToMoney($balance, $order->currency['code'] ?? 'RUB');

            // Format message
            $message = $this->formatReferralAccrualMessage($points, $balance, $balanceMoney, $order->currency['symbol'] ?? '₽');

            // Send notification
            $this->sendTelegramMessage($botToken, $chatId, $message);
        }
    }

    /**
     * Get accrued points for the order
     *
     * @param   object  $order   Order object
     * @param   string  $reason  Reason filter ('accrual' or 'referral_accrual')
     *
     * @return  float
     * @since   0.1.0
     */
    protected function getAccruedPoints(object $order, string $reason = 'accrual'): float
    {
        // Check if bonuses plugin data exists
        if (empty($order->plugins['bonuses']['points']['records']))
        {
            return 0;
        }

        $records = $order->plugins['bonuses']['points']['records'];
        $accrued = 0;

        foreach ($records as $record)
        {
            if (isset($record->reason) && $record->reason === $reason && isset($record->value))
            {
                $accrued += (float) $record->value;
            }
        }

        return $accrued;
    }

    /**
     * Get customer's Telegram chat_id
     *
     * @param   int  $userId  User ID
     *
     * @return  int|null
     * @since   0.1.0
     */
    protected function getCustomerChatId(int $userId): ?int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('chat_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);

        $db->setQuery($query);
        $chatId = $db->loadResult();

        return $chatId ? (int) $chatId : null;
    }

    /**
     * Get bot token from component params
     *
     * @return  string|null
     * @since   0.1.0
     */
    protected function getBotToken(): ?string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('params')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_radicalmart_telegram'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $db->setQuery($query);
        $paramsJson = $db->loadResult();

        if (!$paramsJson)
        {
            return null;
        }

        $params = json_decode($paramsJson);

        return $params->bot_token ?? null;
    }

    /**
     * Format points accrual message
     *
     * @param   object  $order         Order object
     * @param   float   $points        Accrued points
     * @param   float   $balance       Current balance
     * @param   float   $balanceMoney  Balance in money
     *
     * @return  string
     * @since   0.1.0
     */
    protected function formatPointsAccrualMessage(object $order, float $points, float $balance, float $balanceMoney): string
    {
        $currency = $order->currency['symbol'] ?? '₽';
        $orderNumber = $order->number ?? $order->id;

        $message = "🎁 <b>Начислены бонусные баллы!</b>\n\n";
        $message .= "За заказ №{$orderNumber} вам начислено <b>" . number_format($points, 0, ',', ' ') . "</b> баллов.\n\n";
        $message .= "💰 Ваш баланс: <b>" . number_format($balance, 0, ',', ' ') . "</b> баллов";

        if ($balanceMoney > 0)
        {
            $message .= " (≈ " . number_format($balanceMoney, 0, ',', ' ') . " {$currency})";
        }

        $message .= "\n\nИспользуйте баллы при следующем заказе!";

        return $message;
    }

    /**
     * Format referral points accrual message
     *
     * @param   float   $points        Accrued points
     * @param   float   $balance       Current balance
     * @param   float   $balanceMoney  Balance in money
     * @param   string  $currency      Currency symbol
     *
     * @return  string
     * @since   0.1.0
     */
    protected function formatReferralAccrualMessage(float $points, float $balance, float $balanceMoney, string $currency = '₽'): string
    {
        $message = "👥 <b>Реферальный бонус!</b>\n\n";
        $message .= "Ваш реферал совершил заказ.\n";
        $message .= "Вам начислено <b>" . number_format($points, 0, ',', ' ') . "</b> баллов.\n\n";
        $message .= "💰 Ваш баланс: <b>" . number_format($balance, 0, ',', ' ') . "</b> баллов";

        if ($balanceMoney > 0)
        {
            $message .= " (≈ " . number_format($balanceMoney, 0, ',', ' ') . " {$currency})";
        }

        $message .= "\n\nПриглашайте друзей и зарабатывайте больше!";

        return $message;
    }

    /**
     * Send message via Telegram Bot API
     *
     * @param   string  $token   Bot token
     * @param   int     $chatId  Chat ID
     * @param   string  $text    Message text
     *
     * @return  bool
     * @since   0.1.0
     */
    protected function sendTelegramMessage(string $token, int $chatId, string $text): bool
    {
        try
        {
            $http = new Http();
            $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);

            $response = $http->get('https://api.telegram.org/bot' . $token . '/sendMessage?' . http_build_query([
                'chat_id'    => $chatId,
                'parse_mode' => 'HTML',
                'text'       => $text
            ]));

            $result = json_decode($response->body);

            if (!empty($result->ok))
            {
                return true;
            }

            // Log error
            $this->logError('Telegram API error: ' . ($result->description ?? 'Unknown error'));

            return false;
        }
        catch (\Exception $e)
        {
            $this->logError('Telegram send error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Send message via Telegram Bot API with inline keyboard
     *
     * @param   string  $token     Bot token
     * @param   int     $chatId    Chat ID
     * @param   string  $text      Message text
     * @param   array   $keyboard  Inline keyboard array
     *
     * @return  bool
     * @since   0.1.0
     */
    protected function sendTelegramMessageWithKeyboard(string $token, int $chatId, string $text, array $keyboard): bool
    {
        try
        {
            $http = new Http();
            $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);

            $params = [
                'chat_id'      => $chatId,
                'parse_mode'   => 'HTML',
                'text'         => $text,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)
            ];

            $response = $http->post(
                'https://api.telegram.org/bot' . $token . '/sendMessage',
                $params,
                ['Content-Type' => 'application/x-www-form-urlencoded']
            );

            $result = json_decode($response->body);

            if (!empty($result->ok))
            {
                return true;
            }

            // Log error
            $this->logError('Telegram API error: ' . ($result->description ?? 'Unknown error'));

            return false;
        }
        catch (\Exception $e)
        {
            $this->logError('Telegram send error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get WebApp base URL
     *
     * @return  string
     * @since   0.1.0
     */
    protected function getWebAppUrl(): string
    {
        return rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
    }

    /**
     * Log error message
     *
     * @param   string  $message  Error message
     *
     * @return  void
     * @since   0.1.0
     */
    protected function logError(string $message): void
    {
        Log::addLogger([
            'text_file'         => 'plg_radicalmart_telegram_notifications.php',
            'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"
        ], Log::ALL, ['plg_radicalmart_telegram_notifications']);

        Log::add($message, Log::ERROR, 'plg_radicalmart_telegram_notifications');
    }
}
