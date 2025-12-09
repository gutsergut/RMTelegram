<?php

/**
 * @package     com_radicalmart_telegram
 * @subpackage  Service
 * @copyright   (C) 2024-2025 Jexter. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Joomla\Component\RadicalMartTelegram\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * Service for handling restock (back in stock) notifications
 *
 * @since 0.1.91
 */
class RestockService
{
    /**
     * @var DatabaseInterface
     */
    private $db;

    /**
     * @var \Joomla\Registry\Registry
     */
    private $params;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->params = ComponentHelper::getParams('com_radicalmart_telegram');
    }

    /**
     * Subscribe user to restock notification for a product
     *
     * @param int      $chatId    Telegram chat ID
     * @param int      $productId Product ID
     * @param int|null $variantId Variant ID (optional)
     *
     * @return bool
     */
    public function subscribe(int $chatId, int $productId, ?int $variantId = null): bool
    {
        $now = Factory::getDate()->toSql();

        // Check if already subscribed
        $query = $this->db->getQuery(true)
            ->select('id, notified')
            ->from($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->where($this->db->quoteName('chat_id') . ' = ' . $chatId)
            ->where($this->db->quoteName('product_id') . ' = ' . $productId);

        if ($variantId) {
            $query->where($this->db->quoteName('variant_id') . ' = ' . $variantId);
        } else {
            $query->where($this->db->quoteName('variant_id') . ' IS NULL');
        }

        $existing = $this->db->setQuery($query)->loadObject();

        if ($existing) {
            // Reset if already notified
            if ((int) $existing->notified === 1) {
                $update = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
                    ->set($this->db->quoteName('notified') . ' = 0')
                    ->set($this->db->quoteName('notified_at') . ' = NULL')
                    ->set($this->db->quoteName('created') . ' = ' . $this->db->quote($now))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $existing->id);

                $this->db->setQuery($update)->execute();
            }

            return true;
        }

        // Insert new subscription
        $columns = ['chat_id', 'product_id', 'variant_id', 'notified', 'created'];
        $values = [
            $chatId,
            $productId,
            $variantId ? $variantId : 'NULL',
            0,
            $this->db->quote($now),
        ];

        $insert = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->columns($this->db->quoteName($columns))
            ->values(implode(',', $values));

        try {
            $this->db->setQuery($insert)->execute();
            $this->log("Subscribed chat {$chatId} to product {$productId}" . ($variantId ? " variant {$variantId}" : ''));
            return true;
        } catch (\Exception $e) {
            $this->log('Subscribe error: ' . $e->getMessage(), Log::ERROR);
            return false;
        }
    }

    /**
     * Unsubscribe user from restock notification
     *
     * @param int      $chatId    Telegram chat ID
     * @param int      $productId Product ID
     * @param int|null $variantId Variant ID (optional)
     *
     * @return bool
     */
    public function unsubscribe(int $chatId, int $productId, ?int $variantId = null): bool
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->where($this->db->quoteName('chat_id') . ' = ' . $chatId)
            ->where($this->db->quoteName('product_id') . ' = ' . $productId);

        if ($variantId) {
            $query->where($this->db->quoteName('variant_id') . ' = ' . $variantId);
        } else {
            $query->where($this->db->quoteName('variant_id') . ' IS NULL');
        }

        try {
            $this->db->setQuery($query)->execute();
            return true;
        } catch (\Exception $e) {
            $this->log('Unsubscribe error: ' . $e->getMessage(), Log::ERROR);
            return false;
        }
    }

    /**
     * Check if user is subscribed to product restock
     *
     * @param int      $chatId    Telegram chat ID
     * @param int      $productId Product ID
     * @param int|null $variantId Variant ID (optional)
     *
     * @return bool
     */
    public function isSubscribed(int $chatId, int $productId, ?int $variantId = null): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->where($this->db->quoteName('chat_id') . ' = ' . $chatId)
            ->where($this->db->quoteName('product_id') . ' = ' . $productId)
            ->where($this->db->quoteName('notified') . ' = 0');

        if ($variantId) {
            $query->where($this->db->quoteName('variant_id') . ' = ' . $variantId);
        }

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    /**
     * Get products that are now in stock and have pending subscriptions
     *
     * @param int $limit Maximum products to process
     *
     * @return array
     */
    public function getProductsForNotification(int $limit = 100): array
    {
        // Get unique product IDs with pending subscriptions
        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('s.product_id'))
            ->from($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions', 's'))
            ->where($this->db->quoteName('s.notified') . ' = 0')
            ->setLimit($limit);

        $productIds = $this->db->setQuery($query)->loadColumn();

        if (empty($productIds)) {
            return [];
        }

        // Check which products are now in stock
        $productsInStock = [];

        foreach ($productIds as $productId) {
            if ($this->isProductInStock((int) $productId)) {
                $productsInStock[] = (int) $productId;
            }
        }

        return $productsInStock;
    }

    /**
     * Check if a product is in stock
     *
     * @param int $productId Product ID
     *
     * @return bool
     */
    public function isProductInStock(int $productId): bool
    {
        // Check RadicalMart product state and in_stock
        $query = $this->db->getQuery(true)
            ->select('p.id, p.state, p.in_stock')
            ->from($this->db->quoteName('#__radicalmart_products', 'p'))
            ->where($this->db->quoteName('p.id') . ' = ' . $productId)
            ->where($this->db->quoteName('p.state') . ' = 1');

        $product = $this->db->setQuery($query)->loadObject();

        if (!$product) {
            return false;
        }

        // in_stock can be: 1 (in stock), 0 (out of stock), -1 (preorder), etc.
        return (int) $product->in_stock === 1;
    }

    /**
     * Get subscribers for a product
     *
     * @param int $productId Product ID
     *
     * @return array
     */
    public function getSubscribersForProduct(int $productId): array
    {
        $query = $this->db->getQuery(true)
            ->select('s.*, u.username, u.locale')
            ->from($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions', 's'))
            ->leftJoin(
                $this->db->quoteName('#__radicalmart_telegram_users', 'u') .
                ' ON ' . $this->db->quoteName('u.chat_id') . ' = ' . $this->db->quoteName('s.chat_id')
            )
            ->where($this->db->quoteName('s.product_id') . ' = ' . $productId)
            ->where($this->db->quoteName('s.notified') . ' = 0');

        return $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Mark subscription as notified
     *
     * @param int $subscriptionId Subscription ID
     *
     * @return void
     */
    public function markNotified(int $subscriptionId): void
    {
        $now = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->set($this->db->quoteName('notified') . ' = 1')
            ->set($this->db->quoteName('notified_at') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' = ' . $subscriptionId);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Get product info for notification message
     *
     * @param int $productId Product ID
     *
     * @return object|null
     */
    public function getProductInfo(int $productId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('p.id, p.title, p.alias, p.introtext, p.price, p.category, p.images')
            ->from($this->db->quoteName('#__radicalmart_products', 'p'))
            ->where($this->db->quoteName('p.id') . ' = ' . $productId);

        $product = $this->db->setQuery($query)->loadObject();

        if ($product && !empty($product->images)) {
            $images = json_decode($product->images, true);
            $product->image = $images['icon'] ?? ($images['intro'] ?? null);
        }

        return $product;
    }

    /**
     * Build notification message for restock
     *
     * @param object $product Product info
     *
     * @return array Message with text and reply_markup
     */
    public function buildNotificationMessage(object $product): array
    {
        $storeTitle = $this->params->get('store_title', 'Cacao.Land');
        $botUsername = $this->params->get('bot_username', '');

        $text = "🎉 *Товар снова в наличии!*\n\n";
        $text .= "*{$product->title}*\n";

        if (!empty($product->price)) {
            $text .= "💰 " . number_format((float) $product->price, 0, '.', ' ') . " ₽\n";
        }

        $text .= "\nТоропитесь, количество ограничено!";

        // Build WebApp URL for product
        $productUrl = 'https://cacao.land/index.php?option=com_radicalmart_telegram&view=app&product=' . $product->id . '&tmpl=tgwebapp';

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🛒 Перейти к товару',
                        'web_app' => ['url' => $productUrl],
                    ],
                ],
            ],
        ];

        return [
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $replyMarkup,
        ];
    }

    /**
     * Get subscription statistics
     *
     * @return object
     */
    public function getStats(): object
    {
        $query = $this->db->getQuery(true)
            ->select([
                'COUNT(*) as total',
                'SUM(CASE WHEN notified = 0 THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN notified = 1 THEN 1 ELSE 0 END) as notified',
                'COUNT(DISTINCT product_id) as products',
                'COUNT(DISTINCT chat_id) as users',
            ])
            ->from($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'));

        $stats = $this->db->setQuery($query)->loadObject();

        return $stats ?: (object) [
            'total' => 0,
            'pending' => 0,
            'notified' => 0,
            'products' => 0,
            'users' => 0,
        ];
    }

    /**
     * Clean up old notified subscriptions
     *
     * @param int $days Days to keep
     *
     * @return int Number of deleted rows
     */
    public function cleanup(int $days = 30): int
    {
        $cutoff = Factory::getDate('-' . $days . ' days')->toSql();

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__radicalmart_telegram_restock_subscriptions'))
            ->where($this->db->quoteName('notified') . ' = 1')
            ->where($this->db->quoteName('notified_at') . ' < ' . $this->db->quote($cutoff));

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Log message
     *
     * @param string $message  Message
     * @param int    $priority Log priority
     *
     * @return void
     */
    private function log(string $message, int $priority = Log::INFO): void
    {
        if ((int) $this->params->get('logs_enabled', 1)) {
            Log::add('[Restock] ' . $message, $priority, 'com_radicalmart.telegram');
        }
    }
}
