<?php

/**
 * @package     plg_task_radicalmart_telegram_cart_reminders
 * @subpackage  Task
 * @copyright   Copyright (C) 2024-2025 Jexter. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Joomla\Plugin\Task\RadicalmartTelegramCartReminders\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Component\RadicalMartTelegram\Administrator\Service\AbandonedCartService;
use Joomla\Component\RadicalMartTelegram\Site\Service\TelegramService;

/**
 * Task plugin to send abandoned cart reminders via Telegram
 *
 * @since 0.1.90
 */
final class RadicalMartTelegramCartReminders extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var bool
     */
    protected $autoloadLanguage = true;

    /**
     * Task routine ID to handler mapping
     */
    protected const TASKS_MAP = [
        'radicalmart_telegram.cart_reminders' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_CART_REMINDERS',
        ],
    ];

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'standardRoutineHandler',
        ];
    }

    /**
     * Process abandoned cart reminders
     *
     * @param ExecuteTaskEvent $event
     *
     * @return int Status code
     */
    protected function radicalmart_telegramCart_reminders(ExecuteTaskEvent $event): int
    {
        $this->initLogger();

        $params = ComponentHelper::getParams('com_radicalmart_telegram');

        if (!(int) $params->get('abandoned_cart_enabled', 0)) {
            $this->log('Abandoned cart reminders are disabled in settings');
            return Status::OK;
        }

        try {
            $service = new AbandonedCartService();

            // Check quiet hours
            if ($service->isQuietHours()) {
                $this->log('Skipping: quiet hours active');
                return Status::OK;
            }

            // Expire old carts first
            $expired = $service->expireOldCarts();
            if ($expired > 0) {
                $this->log("Expired {$expired} old abandoned carts");
            }

            // Get carts due for reminder
            $carts = $service->getCartsForReminder(50);

            if (empty($carts)) {
                $this->log('No carts due for reminder');
                return Status::OK;
            }

            $this->log('Found ' . count($carts) . ' carts for reminder');

            // Initialize Telegram service
            $telegramService = $this->getTelegramService();

            $sent = 0;
            $failed = 0;

            foreach ($carts as $cart) {
                try {
                    $message = $service->buildReminderMessage($cart);

                    $result = $telegramService->sendMessage(
                        (int) $cart->chat_id,
                        $message['text'],
                        $message['reply_markup']
                    );

                    if ($result) {
                        $service->markReminderSent((int) $cart->chat_id);
                        $sent++;
                        $this->log("Sent reminder #{$cart->reminder_count} to chat {$cart->chat_id}");
                    } else {
                        $failed++;
                        $this->log("Failed to send reminder to chat {$cart->chat_id}", Log::WARNING);
                    }

                    // Rate limiting: 30 messages per second max for Telegram
                    usleep(50000); // 50ms delay

                } catch (\Exception $e) {
                    $failed++;
                    $this->log("Error sending to chat {$cart->chat_id}: " . $e->getMessage(), Log::ERROR);
                }
            }

            $this->log("Completed: sent={$sent}, failed={$failed}");

            return Status::OK;

        } catch (\Exception $e) {
            $this->log('Task failed: ' . $e->getMessage(), Log::ERROR);
            return Status::KNOCKOUT;
        }
    }

    /**
     * Get Telegram service instance
     *
     * @return TelegramService
     */
    private function getTelegramService(): TelegramService
    {
        // Get component params
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        $botToken = $params->get('bot_token', '');

        if (empty($botToken)) {
            throw new \RuntimeException('Bot token is not configured');
        }

        return new TelegramService($botToken, $params);
    }

    /**
     * Initialize logger
     *
     * @return void
     */
    private function initLogger(): void
    {
        Log::addLogger(
            ['text_file' => 'com_radicalmart.telegram.php'],
            Log::ALL,
            ['com_radicalmart.telegram']
        );
    }

    /**
     * Log message
     *
     * @param string $message
     * @param int    $priority
     *
     * @return void
     */
    private function log(string $message, int $priority = Log::INFO): void
    {
        Log::add('[CartReminders] ' . $message, $priority, 'com_radicalmart.telegram');
    }
}
