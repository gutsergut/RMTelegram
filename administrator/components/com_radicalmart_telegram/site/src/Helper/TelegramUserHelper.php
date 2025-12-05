<?php
/**
 * @package     com_radicalmart_telegram
 * Telegram User Helper
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class TelegramUserHelper
{
    protected static ?array $currentUser = null;
    protected static array $debugInfo = [];

    public static function getCurrentUser(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        $app = Factory::getApplication();
        $input = $app->getInput();
        self::$debugInfo = ['step' => 'start', 'time' => date('H:i:s')];

        $chatId = $input->getInt('chat', 0);
        self::$debugInfo['chat_param'] = $chatId;

        if ($chatId > 0) {
            $userData = self::getUserDataByChatId($chatId);
            if ($userData) {
                self::$debugInfo['step'] = 'found_via_chat_param';
                self::$currentUser = $userData;
                return self::$currentUser;
            }
        }

        $tgInit = $input->get('tg_init', '', 'raw');
        self::$debugInfo['tg_init_length'] = strlen($tgInit);

        if ($tgInit) {
            $parsedChatId = self::parseChatIdFromInit($tgInit);
            self::$debugInfo['parsed_chat_id'] = $parsedChatId;

            if ($parsedChatId > 0) {
                $userData = self::getUserDataByChatId($parsedChatId);
                if ($userData) {
                    self::$debugInfo['step'] = 'found_via_tg_init';
                    self::$currentUser = $userData;
                    return self::$currentUser;
                }
            }
        }

        $user = Factory::getUser();
        self::$debugInfo['joomla_user_id'] = $user->id;
        self::$debugInfo['joomla_guest'] = $user->guest;

        if (!$user->guest && $user->id > 0) {
            $linkedChatId = self::getChatIdByUserId((int)$user->id);
            self::$debugInfo['linked_chat_id'] = $linkedChatId;

            self::$currentUser = [
                'chat_id' => $linkedChatId,
                'user_id' => (int)$user->id,
                'user' => $user,
                'source' => 'joomla_session',
                'username' => $user->username,
                'name' => $user->name,
            ];
            self::$debugInfo['step'] = 'found_via_joomla';
            return self::$currentUser;
        }

        self::$debugInfo['step'] = 'not_found';
        return null;
    }

    public static function getUserIdByChatId(int $chatId): int
    {
        if ($chatId <= 0) return 0;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . $db->quote($chatId));
            return (int)$db->setQuery($query)->loadResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function getChatIdByUserId(int $userId): int
    {
        if ($userId <= 0) return 0;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('chat_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('user_id') . ' = ' . (int)$userId);
            return (int)$db->setQuery($query)->loadResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function getUserDataByChatId(int $chatId): ?array
    {
        if ($chatId <= 0) return null;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . $db->quote($chatId));
            $tgUser = $db->setQuery($query)->loadObject();

            if (!$tgUser) {
                return [
                    'chat_id' => $chatId, 'user_id' => 0, 'user' => null,
                    'source' => 'telegram_unlinked', 'username' => null, 'name' => null, 'tg_data' => null,
                ];
            }

            $joomlaUser = null;
            if ($tgUser->user_id > 0) {
                $joomlaUser = Factory::getUser($tgUser->user_id);
                if ($joomlaUser->guest) $joomlaUser = null;
            }

            return [
                'chat_id' => (int)$tgUser->chat_id,
                'user_id' => (int)$tgUser->user_id,
                'user' => $joomlaUser,
                'source' => 'telegram_linked',
                'username' => $tgUser->username ?? ($joomlaUser ? $joomlaUser->username : null),
                'name' => $tgUser->first_name ?? ($joomlaUser ? $joomlaUser->name : null),
                'tg_data' => $tgUser,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function parseChatIdFromInit(string $initData): int
    {
        if (empty($initData)) return 0;
        try {
            parse_str($initData, $params);
            if (!empty($params['user'])) {
                $userData = json_decode($params['user'], true);
                if (isset($userData['id'])) return (int)$userData['id'];
            }
        } catch (\Exception $e) {}
        return 0;
    }

    public static function getDebugInfo(): array { return self::$debugInfo; }
    public static function reset(): void { self::$currentUser = null; self::$debugInfo = []; }
    public static function isAuthenticated(): bool { $u = self::getCurrentUser(); return $u && ($u['chat_id'] > 0 || $u['user_id'] > 0); }
    public static function isLinked(): bool { $u = self::getCurrentUser(); return $u && $u['chat_id'] > 0 && $u['user_id'] > 0; }
    public static function getUserId(): int { return self::getCurrentUser()['user_id'] ?? 0; }
    public static function getChatId(): int { return self::getCurrentUser()['chat_id'] ?? 0; }
}
