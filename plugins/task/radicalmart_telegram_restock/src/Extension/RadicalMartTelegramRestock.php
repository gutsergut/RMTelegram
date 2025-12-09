<?php

/**
 * @package     plg_task_radicalmart_telegram_restock
 * @subpackage  Task
 * @copyright   Copyright (C) 2024-2025 Jexter. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Joomla\Plugin\Task\RadicalmartTelegramRestock\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Component\RadicalMartTelegram\Administrator\Service\RestockService;
use Joomla\Component\RadicalMartTelegram\Site\Service\TelegramService;

/**
 * Task plugin to send restock (back in stock) notifications via Telegram
 *
 * @since 0.1.91
 */
final class RadicalMartTelegramRestock extends CMSPlugin implements SubscriberInterface
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
        'radicalmart_telegram.restock' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_RESTOCK',
        ],
    ];

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'runTask',
        ];
    }

    /**
     * Dispatcher for task execution
     *
     * @param ExecuteTaskEvent $event
     *
     * @return void
     */
    public function runTask(ExecuteTaskEvent $event): void
    {
        $routineId = $event->getRoutineId();

        $this->initLogger();
        $this->log('onExecuteTask received: routineId=' . $routineId);

        if (!\array_key_exists($routineId, self::TASKS_MAP)) {
            $this->log('RoutineId not in TASKS_MAP, skipping');
            return;
        }

        $this->startRoutine($event);

        $result = $this->sendRestockNotifications($event);

        $this->endRoutine($event, $result);
    }

    /**
     * Process restock notifications
     *
     * @param ExecuteTaskEvent $event
     *
     * @return int Status code
     */
    private function sendRestockNotifications(ExecuteTaskEvent $event): int
    {
        $this->initLogger();

        $params = ComponentHelper::getParams('com_radicalmart_telegram');

        if (!(int) $params->get('restock_notifications_enabled', 1)) {
            $this->log('Restock notifications are disabled in settings');
            return Status::OK;
        }

        try {
            $restockService = new RestockService();

            // Cleanup old notified subscriptions
            $cleaned = $restockService->cleanup(30);
            if ($cleaned > 0) {
                $this->log("Cleaned up {$cleaned} old subscriptions");
            }

            // Get products that are now in stock
            $products = $restockService->getProductsForNotification(50);

            if (empty($products)) {
                $this->log('No products with pending restock notifications');
                return Status::OK;
            }

            $this->log('Found ' . count($products) . ' products for restock notification');

            // Initialize Telegram service
            $telegramService = $this->getTelegramService();

            $sent = 0;
            $failed = 0;

            foreach ($products as $productId) {
                try {
                    // Get product info
                    $product = $restockService->getProductInfo($productId);

                    if (!$product) {
                        $this->log("Product {$productId} not found, skipping");
                        continue;
                    }

                    // Get subscribers
                    $subscribers = $restockService->getSubscribersForProduct($productId);

                    if (empty($subscribers)) {
                        continue;
                    }

                    // Build message
                    $message = $restockService->buildNotificationMessage($product);

                    foreach ($subscribers as $subscriber) {
                        try {
                            $result = $telegramService->sendMessage(
                                (int) $subscriber->chat_id,
                                $message['text'],
                                $message['reply_markup'],
                                $message['parse_mode'] ?? 'Markdown'
                            );

                            if ($result) {
                                $restockService->markNotified((int) $subscriber->id);
                                $sent++;
                                $this->log("Sent restock notification for product {$productId} to chat {$subscriber->chat_id}");
                            } else {
                                $failed++;
                                $this->log("Failed to send restock notification to chat {$subscriber->chat_id}", Log::WARNING);
                            }

                            // Rate limiting: 30 messages per second max for Telegram
                            usleep(50000); // 50ms delay

                        } catch (\Exception $e) {
                            $failed++;
                            $this->log("Error sending to chat {$subscriber->chat_id}: " . $e->getMessage(), Log::ERROR);
                        }
                    }

                } catch (\Exception $e) {
                    $this->log("Error processing product {$productId}: " . $e->getMessage(), Log::ERROR);
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
        Log::add('[Restock] ' . $message, $priority, 'com_radicalmart.telegram');
    }
}
