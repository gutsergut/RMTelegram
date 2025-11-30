<?php
/**
 * @package     RadicalMart Telegram Expiring Points Task
 * @subpackage  plg_task_radicalmart_telegram_expiring
 * @version     0.1.0
 */

namespace Joomla\Plugin\Task\RadicalmartTelegramExpiring\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Task plugin to notify customers about expiring bonus points
 *
 * @since  0.1.0
 */
final class RadicalMartTelegramExpiring extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var bool
     * @since 0.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @var array
     * @since 0.1.0
     */
    protected const TASKS_MAP = [
        'radicalmart_telegram.expiring_points' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_EXPIRING',
        ],
    ];

    /**
     * @return array
     * @since 0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'runExpiringCheck',
        ];
    }

    /**
     * Run the expiring points check task
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  void
     * @since   0.1.0
     */
    public function runExpiringCheck(ExecuteTaskEvent $event): void
    {
        if (!\array_key_exists($event->getRoutineId(), self::TASKS_MAP))
        {
            return;
        }

        $this->startRoutine($event);

        // Initialize logger
        Log::addLogger(
            ['text_file' => 'plg_task_radicalmart_telegram_expiring.php'],
            Log::ALL,
            ['plg_task_radicalmart_telegram_expiring']
        );

        try
        {
            $sent = $this->checkExpiringPoints();
            $this->logTask("Sent {$sent} expiring points notifications");
            $this->endRoutine($event, Status::OK);
        }
        catch (\Exception $e)
        {
            Log::add('Error: ' . $e->getMessage(), Log::ERROR, 'plg_task_radicalmart_telegram_expiring');
            $this->logTask('Error: ' . $e->getMessage(), 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
        }
    }

    /**
     * Check for expiring points and send notifications
     *
     * @return  int  Number of notifications sent
     * @since   0.1.0
     */
    protected function checkExpiringPoints(): int
    {
        // Get days before parameter (default 7)
        $daysBefore = (int) $this->params->get('days_before', 7);
        if ($daysBefore < 1)
        {
            $daysBefore = 7;
        }

        // Get bot token
        $botToken = $this->getBotToken();
        if (!$botToken)
        {
            Log::add('Bot token not configured', Log::WARNING, 'plg_task_radicalmart_telegram_expiring');
            return 0;
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Calculate date range: from today to N days ahead
        $now = Factory::getDate();
        $futureDate = Factory::getDate('+' . $daysBefore . ' days')->format('Y-m-d 23:59:59');
        $todayDate = $now->format('Y-m-d 00:00:00');

        // Find customers with expiring points
        // Group by customer to avoid multiple notifications per day
        $query = $db->getQuery(true)
            ->select([
                'p.customer',
                'SUM(p.rest) as expiring_points',
                'MIN(p.end) as earliest_end'
            ])
            ->from($db->quoteName('#__radicalmart_bonuses_points', 'p'))
            ->where($db->quoteName('p.end') . ' >= ' . $db->quote($todayDate))
            ->where($db->quoteName('p.end') . ' <= ' . $db->quote($futureDate))
            ->where($db->quoteName('p.rest') . ' > 0')
            ->group($db->quoteName('p.customer'));

        $expiringRecords = $db->setQuery($query)->loadObjectList();

        if (empty($expiringRecords))
        {
            Log::add('No expiring points found', Log::INFO, 'plg_task_radicalmart_telegram_expiring');
            return 0;
        }

        $sent = 0;

        foreach ($expiringRecords as $record)
        {
            $customerId = (int) $record->customer;
            $expiringPoints = (float) $record->expiring_points;
            $earliestEnd = $record->earliest_end;

            // Skip if already notified today (check last notification)
            if ($this->wasNotifiedToday($customerId))
            {
                continue;
            }

            // Get customer's Telegram chat_id
            $chatId = $this->getCustomerChatId($customerId);
            if (!$chatId)
            {
                continue;
            }

            // Get current balance
            $balance = 0;
            $balanceMoney = 0;
            if (class_exists(PointsHelper::class))
            {
                $balance = PointsHelper::getCustomerPoints($customerId);
                $balanceMoney = PointsHelper::convertToMoney($balance, 'RUB');
            }

            // Calculate days until expiration
            $endDate = Factory::getDate($earliestEnd);
            $daysLeft = (int) $now->diff($endDate)->days;

            // Format message
            $message = $this->formatExpiringMessage($expiringPoints, $daysLeft, $balance, $balanceMoney);

            // Send notification
            if ($this->sendTelegramMessage($botToken, $chatId, $message))
            {
                $this->markNotified($customerId);
                $sent++;
            }
        }

        Log::add("Sent {$sent} notifications", Log::INFO, 'plg_task_radicalmart_telegram_expiring');

        return $sent;
    }

    /**
     * Check if customer was already notified today
     *
     * @param   int  $customerId  Customer ID
     *
     * @return  bool
     * @since   0.1.0
     */
    protected function wasNotifiedToday(int $customerId): bool
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        $today = Factory::getDate()->format('Y-m-d');

        // Check in a simple notifications log table or session
        // For simplicity, we'll use a component params storage
        // In production, you might want a dedicated table

        $query = $db->getQuery(true)
            ->select('params')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('radicalmart_telegram_expiring'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'));

        $paramsJson = $db->setQuery($query)->loadResult();
        if (!$paramsJson)
        {
            return false;
        }

        $params = json_decode($paramsJson, true);
        $notified = $params['notified_today'] ?? [];
        $notifiedDate = $params['notified_date'] ?? '';

        // Reset if different day
        if ($notifiedDate !== $today)
        {
            return false;
        }

        return in_array($customerId, $notified);
    }

    /**
     * Mark customer as notified today
     *
     * @param   int  $customerId  Customer ID
     *
     * @return  void
     * @since   0.1.0
     */
    protected function markNotified(int $customerId): void
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        $today = Factory::getDate()->format('Y-m-d');

        $query = $db->getQuery(true)
            ->select('params')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('radicalmart_telegram_expiring'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'));

        $paramsJson = $db->setQuery($query)->loadResult();
        $params = $paramsJson ? json_decode($paramsJson, true) : [];

        $notifiedDate = $params['notified_date'] ?? '';

        // Reset if different day
        if ($notifiedDate !== $today)
        {
            $params['notified_today'] = [];
            $params['notified_date'] = $today;
        }

        $params['notified_today'][] = $customerId;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
            ->where($db->quoteName('element') . ' = ' . $db->quote('radicalmart_telegram_expiring'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'));

        $db->setQuery($query)->execute();
    }

    /**
     * Get customer's Telegram chat_id
     *
     * @param   int  $customerId  Customer ID (RadicalMart)
     *
     * @return  int|null
     * @since   0.1.0
     */
    protected function getCustomerChatId(int $customerId): ?int
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get user_id from customer
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_customers'))
            ->where($db->quoteName('id') . ' = ' . $customerId);

        $userId = $db->setQuery($query)->loadResult();
        if (!$userId)
        {
            return null;
        }

        // Get chat_id from telegram users
        $query = $db->getQuery(true)
            ->select('chat_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $userId);

        $chatId = $db->setQuery($query)->loadResult();

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
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('params')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_radicalmart_telegram'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $paramsJson = $db->setQuery($query)->loadResult();

        if (!$paramsJson)
        {
            return null;
        }

        $params = json_decode($paramsJson);

        return $params->bot_token ?? null;
    }

    /**
     * Format expiring points message
     *
     * @param   float   $points       Expiring points
     * @param   int     $daysLeft     Days until expiration
     * @param   float   $balance      Current balance
     * @param   float   $balanceMoney Balance in money
     *
     * @return  string
     * @since   0.1.0
     */
    protected function formatExpiringMessage(float $points, int $daysLeft, float $balance, float $balanceMoney): string
    {
        $pointsFormatted = number_format($points, 0, ',', ' ');
        $balanceFormatted = number_format($balance, 0, ',', ' ');

        if ($daysLeft === 0)
        {
            $message = "⚠️ <b>Баллы сгорают сегодня!</b>\n\n";
            $message .= "У вас сгорает <b>{$pointsFormatted}</b> баллов сегодня.\n";
        }
        elseif ($daysLeft === 1)
        {
            $message = "⏰ <b>Баллы сгорают завтра!</b>\n\n";
            $message .= "У вас сгорает <b>{$pointsFormatted}</b> баллов завтра.\n";
        }
        else
        {
            $daysWord = $this->getDaysWord($daysLeft);
            $message = "⏰ <b>Скоро сгорят баллы!</b>\n\n";
            $message .= "Через {$daysLeft} {$daysWord} у вас сгорит <b>{$pointsFormatted}</b> баллов.\n";
        }

        $message .= "\n💰 Текущий баланс: <b>{$balanceFormatted}</b> баллов";

        if ($balanceMoney > 0)
        {
            $message .= " (≈ " . number_format($balanceMoney, 0, ',', ' ') . " ₽)";
        }

        $message .= "\n\nУспейте использовать баллы при следующем заказе!";

        return $message;
    }

    /**
     * Get correct Russian word form for days
     *
     * @param   int  $days  Number of days
     *
     * @return  string
     * @since   0.1.0
     */
    protected function getDaysWord(int $days): string
    {
        $lastTwo = $days % 100;
        $lastOne = $days % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14)
        {
            return 'дней';
        }

        if ($lastOne === 1)
        {
            return 'день';
        }

        if ($lastOne >= 2 && $lastOne <= 4)
        {
            return 'дня';
        }

        return 'дней';
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

            Log::add('Telegram API error: ' . ($result->description ?? 'Unknown'), Log::ERROR, 'plg_task_radicalmart_telegram_expiring');

            return false;
        }
        catch (\Exception $e)
        {
            Log::add('Telegram send error: ' . $e->getMessage(), Log::ERROR, 'plg_task_radicalmart_telegram_expiring');

            return false;
        }
    }
}
