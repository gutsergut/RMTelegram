<?php
/**
 * @package     com_radicalmart_telegram
 * Telegram User Helper
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;

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

    /**
     * Parse start_param from initData (for ?startapp=ref_CODE links)
     * Returns the referral code if start_param starts with "ref_", otherwise null
     */
    public static function parseStartParamFromInit(string $initData): ?string
    {
        if (empty($initData)) return null;
        try {
            parse_str($initData, $params);
            if (!empty($params['start_param'])) {
                $startParam = (string)$params['start_param'];
                // Check if it's a referral code (starts with ref_)
                if (strpos($startParam, 'ref_') === 0) {
                    return substr($startParam, 4); // Remove "ref_" prefix
                }
                return $startParam;
            }
        } catch (\Exception $e) {}
        return null;
    }

    /**
     * Process referral code from WebApp start_param
     * Called from API controller on first request
     */
    public static function processStartParamReferral(int $chatId, string $initData): void
    {
        if ($chatId <= 0 || empty($initData)) return;

        $startParam = self::parseStartParamFromInit($initData);
        if (empty($startParam)) return;

        // Check if this is a referral code
        if (strpos($startParam, 'ref_') !== 0 && !preg_match('/^[a-z0-9]{6,12}$/i', $startParam)) {
            return; // Not a referral code format
        }

        // Remove ref_ prefix if present
        $referralCode = (strpos($startParam, 'ref_') === 0) ? substr($startParam, 4) : $startParam;

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // Check if user already has a referral code
            $query = $db->getQuery(true)
                ->select(['id', 'referral_code', 'user_id'])
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . $db->quote($chatId));
            $user = $db->setQuery($query)->loadObject();

            if ($user && !empty($user->referral_code)) {
                // Already has referral code, skip
                return;
            }

            // Validate referral code exists in RadicalMart Bonuses
            if (class_exists(\Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper::class)) {
                $codeData = \Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper::find($referralCode, 'code');
                if (!$codeData || empty($codeData->referral)) {
                    return; // Not a valid referral code
                }
            }

            if ($user) {
                // Update existing user
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__radicalmart_telegram_users'))
                    ->set($db->quoteName('referral_code') . ' = ' . $db->quote($referralCode))
                    ->where($db->quoteName('id') . ' = ' . (int)$user->id);
                $db->setQuery($upd)->execute();
            } else {
                // Create new user with referral code
                $row = (object)[
                    'chat_id' => $chatId,
                    'referral_code' => $referralCode,
                    'created' => (new \Joomla\CMS\Date\Date())->toSql(),
                ];
                $db->insertObject('#__radicalmart_telegram_users', $row);
            }

            LogHelper::debug('Saved referral code from WebApp start_param: ' . $referralCode . ' for chat ' . $chatId, 'com_radicalmart.telegram');
        } catch (\Exception $e) {
            // Silently ignore errors
        }
    }

    public static function getDebugInfo(): array { return self::$debugInfo; }
    public static function reset(): void { self::$currentUser = null; self::$debugInfo = []; }
    public static function isAuthenticated(): bool { $u = self::getCurrentUser(); return $u && ($u['chat_id'] > 0 || $u['user_id'] > 0); }
    public static function isLinked(): bool { $u = self::getCurrentUser(); return $u && $u['chat_id'] > 0 && $u['user_id'] > 0; }
    public static function getUserId(): int { return self::getCurrentUser()['user_id'] ?? 0; }
    public static function getChatId(): int { return self::getCurrentUser()['chat_id'] ?? 0; }
}
