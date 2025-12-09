<?php
declare(strict_types=1);
namespace Joomla\Component\RadicalMartTelegram\Site\Helper;
\defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
class AcyMailingHelper
{
    public static function isAvailable(): bool
    {
        $helperPath = JPATH_ADMINISTRATOR . '/components/com_acym/helpers/helper.php';
        return file_exists($helperPath);
    }
    protected static function initAcyMailing(): bool
    {
        static $initialized = null;
        if ($initialized !== null) {
            return $initialized;
        }
        $helperPath = JPATH_ADMINISTRATOR . '/components/com_acym/helpers/helper.php';
        if (!file_exists($helperPath)) {
            LogHelper::warning('AcyMailing not installed: ' . $helperPath);
            $initialized = false;
            return false;
        }
        include_once $helperPath;
        $initialized = true;
        return true;
    }
    public static function isEnabled(): bool
    {
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        return (bool) $params->get('acymailing_enabled', 0) && self::isAvailable();
    }
    public static function getListId(): int
    {
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        return (int) $params->get('acymailing_list_id', 0);
    }
    public static function shouldActivateUser(): bool
    {
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        return (bool) $params->get('acymailing_activate_user', 1);
    }
    public static function subscribe(string $email, string $name = '', ?int $listId = null): bool
    {
        if (!self::isEnabled()) {
            LogHelper::debug('AcyMailing integration disabled, skipping subscribe');
            return false;
        }
        if (!self::initAcyMailing()) {
            return false;
        }
        $listId = $listId ?? self::getListId();
        if ($listId <= 0) {
            LogHelper::warning('AcyMailing list_id not configured');
            return false;
        }
        try {
            $userClass = new \AcyMailing\Classes\UserClass();
            $subscriber = $userClass->getOneByEmail($email);
            if (!$subscriber) {
                $subscriber = new \stdClass();
                $subscriber->email = $email;
                $subscriber->name = $name;
                $activate = self::shouldActivateUser();
                $subscriber->confirmed = $activate ? 1 : 0;
                $subscriber->active = $activate ? 1 : 0;
                $subscriber->source = 'telegram_bot';
                $subscriberId = $userClass->save($subscriber);
                if (!$subscriberId) {
                    LogHelper::error('AcyMailing: failed to create subscriber for ' . $email);
                    return false;
                }
            } else {
                $subscriberId = (int) $subscriber->id;
            }
            $result = $userClass->subscribe($subscriberId, [$listId], true, false);
            if ($result) {
                LogHelper::info('AcyMailing: subscribed ' . $email . ' to list ' . $listId);
                return true;
            } else {
                LogHelper::warning('AcyMailing: subscribe failed for ' . $email . ' - ' . ($userClass->errors[0] ?? 'unknown error'));
                return false;
            }
        } catch (\Throwable $e) {
            LogHelper::error('AcyMailing subscribe error: ' . $e->getMessage());
            return false;
        }
    }
    public static function unsubscribe(string $email, ?int $listId = null): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        if (!self::initAcyMailing()) {
            return false;
        }
        $listId = $listId ?? self::getListId();
        if ($listId <= 0) {
            return false;
        }
        try {
            $userClass = new \AcyMailing\Classes\UserClass();
            $subscriber = $userClass->getOneByEmail($email);
            if (!$subscriber) {
                return true;
            }
            $result = $userClass->unsubscribe((int) $subscriber->id, [$listId]);
            if ($result) {
                LogHelper::info('AcyMailing: unsubscribed ' . $email . ' from list ' . $listId);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            LogHelper::error('AcyMailing unsubscribe error: ' . $e->getMessage());
            return false;
        }
    }
    public static function isSubscribed(string $email, ?int $listId = null): bool
    {
        if (!self::isAvailable() || !self::initAcyMailing()) {
            return false;
        }
        $listId = $listId ?? self::getListId();
        if ($listId <= 0) {
            return false;
        }
        try {
            $userClass = new \AcyMailing\Classes\UserClass();
            $subscriber = $userClass->getOneByEmail($email);
            if (!$subscriber) {
                return false;
            }
            $subscriptions = $userClass->getSubscriptionStatus((int) $subscriber->id);
            foreach ($subscriptions as $sub) {
                if ((int) $sub->list_id === $listId && (int) $sub->status === 1) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            LogHelper::error('AcyMailing isSubscribed error: ' . $e->getMessage());
            return false;
        }
    }
    public static function getLists(): array
    {
        if (!self::isAvailable() || !self::initAcyMailing()) {
            return [];
        }
        try {
            $listClass = new \AcyMailing\Classes\ListClass();
            $lists = $listClass->getAll();
            $result = [];
            foreach ($lists as $list) {
                $result[(int) $list->id] = $list->name;
            }
            return $result;
        } catch (\Throwable $e) {
            LogHelper::error('AcyMailing getLists error: ' . $e->getMessage());
            return [];
        }
    }
    public static function updateSubscriptionFlag(int $chatId, bool $subscribed): void
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $value = $subscribed ? 1 : 0;
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__radicalmart_telegram_users'))
                ->set($db->quoteName('acymailing_subscribed') . ' = :subscribed')
                ->where($db->quoteName('chat_id') . ' = :chatId')
                ->bind(':subscribed', $value, ParameterType::INTEGER)
                ->bind(':chatId', $chatId, ParameterType::INTEGER);
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            LogHelper::error('Failed to update acymailing_subscribed flag: ' . $e->getMessage());
        }
    }
    public static function subscribeAndUpdateFlag(int $chatId, string $email, string $name = ''): bool
    {
        $result = self::subscribe($email, $name);
        if ($result) {
            self::updateSubscriptionFlag($chatId, true);
        }
        return $result;
    }
    public static function unsubscribeAndUpdateFlag(int $chatId, string $email): bool
    {
        $result = self::unsubscribe($email);
        if ($result) {
            self::updateSubscriptionFlag($chatId, false);
        }
        return $result;
    }
}
