<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Helper
 * @version     0.1.89
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\ParameterType;

class EmailVerificationHelper
{
    public const MAX_ATTEMPTS = 5;
    public const LOCKOUT_MINUTES = 30;
    public const CODE_EXPIRES_MINUTES = 15;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_EMAILS_PER_HOUR = 3;
    public const TOKEN_LENGTH = 32;

    /**
     * Generate a 6-digit OTP code (for manual input)
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a secure token for link-based verification
     * Token format: {chatId}_{randomHex}
     */
    public static function generateToken(int $chatId): string
    {
        $random = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return base64_encode($chatId . '_' . $random);
    }

    /**
     * Parse token to extract chat_id
     */
    public static function parseToken(string $token): ?array
    {
        try {
            $decoded = base64_decode($token, true);
            if (!$decoded) {
                return null;
            }
            $parts = explode('_', $decoded, 2);
            if (count($parts) !== 2) {
                return null;
            }
            $chatId = (int) $parts[0];
            if ($chatId <= 0) {
                return null;
            }
            return ['chatId' => $chatId, 'token' => $token];
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function validateFormat(string $email): array
    {
        $email = trim($email);
        if (strlen($email) > 255) {
            return ['valid' => false, 'error' => 'EMAIL_TOO_LONG'];
        }
        if (empty($email)) {
            return ['valid' => false, 'error' => 'EMAIL_EMPTY'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'EMAIL_INVALID_FORMAT'];
        }
        $parts = explode('@', $email);
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $parts[0])) {
            return ['valid' => false, 'error' => 'EMAIL_CYRILLIC_LOCAL'];
        }
        return ['valid' => true, 'error' => null];
    }

    public static function checkUniqueness(string $email, int $chatId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('chat_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('email') . ' = :email')
            ->where($db->quoteName('chat_id') . ' != :chat')
            ->bind(':email', $email)
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $existingChat = $db->setQuery($query, 0, 1)->loadResult();
        if ($existingChat) {
            return ['unique' => false, 'error' => 'EMAIL_USED_BY_OTHER_USER', 'conflictType' => 'telegram_user'];
        }
        $query = $db->getQuery(true)
            ->select(['u.id', 'tu.user_id AS linked_user_id'])
            ->from($db->quoteName('#__users', 'u'))
            ->leftJoin($db->quoteName('#__radicalmart_telegram_users', 'tu') . ' ON ' . $db->quoteName('tu.user_id') . ' = ' . $db->quoteName('u.id') . ' AND ' . $db->quoteName('tu.chat_id') . ' = :chat2')
            ->where($db->quoteName('u.email') . ' = :email2')
            ->bind(':email2', $email)
            ->bind(':chat2', $chatId, ParameterType::INTEGER);
        $joomlaUser = $db->setQuery($query, 0, 1)->loadAssoc();
        if ($joomlaUser && empty($joomlaUser['linked_user_id'])) {
            return ['unique' => false, 'error' => 'EMAIL_EXISTS_ON_SITE', 'conflictType' => 'joomla_user', 'joomlaUserId' => (int) $joomlaUser['id']];
        }
        return ['unique' => true, 'error' => null, 'conflictType' => null];
    }

    public static function canRequestCode(int $chatId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['email_code_sent_at', 'email_verification_attempts', 'email_verification_expires'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $row = $db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            return ['allowed' => true, 'error' => null, 'waitSeconds' => 0];
        }
        $now = new Date();
        if ((int) $row['email_verification_attempts'] >= self::MAX_ATTEMPTS) {
            $expires = $row['email_verification_expires'] ? new Date($row['email_verification_expires']) : null;
            if ($expires && $expires > $now) {
                $waitSeconds = $expires->toUnix() - $now->toUnix();
                return ['allowed' => false, 'error' => 'TOO_MANY_ATTEMPTS', 'waitSeconds' => $waitSeconds];
            }
            self::resetAttempts($chatId);
        }
        if (!empty($row['email_code_sent_at'])) {
            $lastSent = new Date($row['email_code_sent_at']);
            $elapsed = $now->toUnix() - $lastSent->toUnix();
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                return ['allowed' => false, 'error' => 'RATE_LIMIT', 'waitSeconds' => self::RESEND_COOLDOWN_SECONDS - $elapsed];
            }
        }
        return ['allowed' => true, 'error' => null, 'waitSeconds' => 0];
    }

    public static function saveCode(int $chatId, string $email, string $code): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $now = (new Date())->toSql();
        $expires = (new Date())->modify('+' . self::CODE_EXPIRES_MINUTES . ' minutes')->toSql();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set([$db->quoteName('email') . ' = :email', $db->quoteName('email_verified') . ' = 0', $db->quoteName('email_verification_code') . ' = :code', $db->quoteName('email_verification_expires') . ' = :expires', $db->quoteName('email_code_sent_at') . ' = :sent'])
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':email', $email)->bind(':code', $code)->bind(':expires', $expires)->bind(':sent', $now)->bind(':chat', $chatId, ParameterType::INTEGER);
        try {
            $db->setQuery($query)->execute();
            return $db->getAffectedRows() > 0;
        } catch (\Exception $e) {
            LogHelper::error('Failed to save verification code: ' . $e->getMessage());
            return false;
        }
    }

    public static function verifyCode(int $chatId, string $inputCode): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['email', 'email_verification_code', 'email_verification_expires', 'email_verification_attempts'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $row = $db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            return ['success' => false, 'error' => 'USER_NOT_FOUND', 'attemptsLeft' => 0];
        }
        $attempts = (int) $row['email_verification_attempts'];
        $now = new Date();
        if ($attempts >= self::MAX_ATTEMPTS) {
            $expires = $row['email_verification_expires'] ? new Date($row['email_verification_expires']) : null;
            if ($expires && $expires > $now) {
                return ['success' => false, 'error' => 'TOO_MANY_ATTEMPTS', 'attemptsLeft' => 0];
            }
        }
        if (empty($row['email_verification_code'])) {
            return ['success' => false, 'error' => 'NO_CODE_PENDING', 'attemptsLeft' => self::MAX_ATTEMPTS - $attempts];
        }
        $expires = new Date($row['email_verification_expires']);
        if ($expires < $now) {
            return ['success' => false, 'error' => 'CODE_EXPIRED', 'attemptsLeft' => self::MAX_ATTEMPTS - $attempts];
        }
        $attempts++;
        self::incrementAttempts($chatId, $attempts);
        if ($row['email_verification_code'] !== $inputCode) {
            $attemptsLeft = self::MAX_ATTEMPTS - $attempts;
            if ($attemptsLeft <= 0) {
                self::lockout($chatId);
                return ['success' => false, 'error' => 'TOO_MANY_ATTEMPTS', 'attemptsLeft' => 0];
            }
            return ['success' => false, 'error' => 'INVALID_CODE', 'attemptsLeft' => $attemptsLeft];
        }
        self::markVerified($chatId);
        return ['success' => true, 'error' => null, 'attemptsLeft' => self::MAX_ATTEMPTS];
    }

    public static function markVerified(int $chatId): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Получаем данные пользователя перед обновлением
        $query = $db->getQuery(true)
            ->select(['email', 'user_id', 'phone', 'tg_first_name', 'tg_last_name', 'username'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $userData = $db->setQuery($query)->loadAssoc();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set([$db->quoteName('email_verified') . ' = 1', $db->quoteName('email_verification_code') . ' = NULL', $db->quoteName('email_verification_expires') . ' = NULL', $db->quoteName('email_verification_attempts') . ' = 0'])
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        try {
            $db->setQuery($query)->execute();

            $email = $userData['email'] ?? '';
            $name = trim(($userData['tg_first_name'] ?? '') . ' ' . ($userData['tg_last_name'] ?? ''));
            $existingUserId = (int) ($userData['user_id'] ?? 0);

            // Если Joomla user ещё не привязан — создаём его
            if ($existingUserId === 0 && !empty($email)) {
                $newUserId = self::createJoomlaUser($chatId, $email, $name, $userData);
                if ($newUserId > 0) {
                    LogHelper::info("Created Joomla user {$newUserId} for chat {$chatId}");
                }
            }

            // Подписываем на AcyMailing после успешной верификации
            if (!empty($email)) {
                AcyMailingHelper::subscribeAndUpdateFlag($chatId, $email, $name);
            }

            return true;
        } catch (\Exception $e) {
            LogHelper::error('Failed to mark email verified: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Создаёт Joomla пользователя и привязывает к telegram_users
     * Отправляет сгенерированный пароль на email и в Telegram
     */
    public static function createJoomlaUser(int $chatId, string $email, string $name, array $tgData = []): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Проверяем, не существует ли уже пользователь с таким email
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $email);
        $existingId = (int) $db->setQuery($query, 0, 1)->loadResult();

        if ($existingId > 0) {
            // Пользователь уже существует — просто привязываем
            self::linkTelegramToUser($chatId, $existingId);
            return $existingId;
        }

        // Генерируем пароль
        $password = self::generateSecurePassword();

        // Создаём username из email или tg username
        $username = $tgData['username'] ?? '';
        if (empty($username)) {
            $username = strstr($email, '@', true);
        }
        // Убеждаемся что username уникален
        $username = self::ensureUniqueUsername($username);

        // Имя пользователя
        if (empty($name)) {
            $name = $username;
        }

        try {
            // Создаём пользователя через Joomla API
            $user = new \Joomla\CMS\User\User();
            $user->set('name', $name);
            $user->set('username', $username);
            $user->set('email', $email);
            $user->set('password', $password);
            $user->set('block', 0);
            $user->set('activation', '');
            $user->set('sendEmail', 0);
            $user->set('registerDate', (new Date())->toSql());

            // Получаем группу по умолчанию для новых пользователей
            $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
            $defaultGroup = $params->get('new_usertype', 2); // 2 = Registered
            $user->set('groups', [$defaultGroup]);

            if (!$user->save()) {
                LogHelper::error('Failed to create Joomla user: ' . implode(', ', $user->getErrors()));
                return 0;
            }

            $newUserId = (int) $user->id;

            // Привязываем к telegram_users
            self::linkTelegramToUser($chatId, $newUserId);

            // Отправляем пароль на email
            self::sendPasswordEmail($email, $username, $password, $name);

            // Отправляем пароль в Telegram
            self::sendPasswordTelegram($chatId, $username, $password);

            // Синхронизируем согласие на политику конфиденциальности
            self::syncPrivacyConsent($chatId, $newUserId);

            return $newUserId;
        } catch (\Exception $e) {
            LogHelper::error('Exception creating Joomla user: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Привязывает telegram chat к Joomla user
     */
    private static function linkTelegramToUser(int $chatId, int $userId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set($db->quoteName('user_id') . ' = :uid')
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        // Синхронизируем согласие на политику конфиденциальности
        self::syncPrivacyConsent($chatId, $userId);
    }

    /**
     * Синхронизирует согласие на политику конфиденциальности с com_j_sms_registration
     * Если пользователь принял политику в нашем компоненте (consent_personal_data=1),
     * автоматически проставляем согласие в #__privacy_consents
     *
     * @param int $chatId Telegram chat ID
     * @param int $userId Joomla user ID (0 = получить из telegram_users)
     */
    public static function syncPrivacyConsent(int $chatId, int $userId = 0): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Получаем данные telegram пользователя
        $query = $db->getQuery(true)
            ->select(['consent_personal_data', 'user_id'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $tgUser = $db->setQuery($query)->loadObject();

        if (!$tgUser) {
            return false;
        }

        // Если user_id не передан, берём из telegram_users
        if ($userId <= 0) {
            $userId = (int) ($tgUser->user_id ?? 0);
        }

        if ($userId <= 0) {
            // Нет привязанного Joomla пользователя — нечего синхронизировать
            return false;
        }

        // Проверяем, принял ли пользователь политику в нашем компоненте
        $hasConsent = (int) ($tgUser->consent_personal_data ?? 0) === 1;

        if (!$hasConsent) {
            // Пользователь не принял политику в нашем компоненте
            return false;
        }

        // Проверяем, есть ли уже согласие в #__privacy_consents
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__privacy_consents'))
            ->where($db->quoteName('user_id') . ' = :uid')
            ->where($db->quoteName('subject') . ' = ' . $db->quote('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'))
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':uid', $userId, ParameterType::INTEGER);
        $alreadyConsented = (int) $db->setQuery($query)->loadResult() > 0;

        if ($alreadyConsented) {
            // Согласие уже есть
            return true;
        }

        // Создаём запись о согласии
        try {
            $app = Factory::getApplication();
            $ip = $app->input->server->get('REMOTE_ADDR', '', 'string');
            $userAgent = $app->input->server->get('HTTP_USER_AGENT', 'Telegram WebApp', 'string');

            $consentRecord = (object) [
                'user_id' => $userId,
                'subject' => 'PLG_SYSTEM_PRIVACYCONSENT_SUBJECT',
                'body'    => sprintf('Согласие принято через Telegram бот. IP: %s, User-Agent: %s', $ip, $userAgent),
                'created' => (new Date())->toSql(),
                'state'   => 1,
            ];

            $db->insertObject('#__privacy_consents', $consentRecord);
            LogHelper::info("Privacy consent synced for user {$userId} from chat {$chatId}");
            return true;
        } catch (\Exception $e) {
            LogHelper::error('Failed to sync privacy consent: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Генерирует безопасный пароль
     */
    private static function generateSecurePassword(int $length = 12): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    /**
     * Проверяет уникальность username и добавляет суффикс если нужно
     */
    private static function ensureUniqueUsername(string $username): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        if (empty($baseUsername)) {
            $baseUsername = 'user';
        }

        $checkUsername = $baseUsername;
        $suffix = 1;

        while (true) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('username') . ' = :uname')
                ->bind(':uname', $checkUsername);
            $count = (int) $db->setQuery($query)->loadResult();

            if ($count === 0) {
                return $checkUsername;
            }

            $checkUsername = $baseUsername . $suffix;
            $suffix++;

            if ($suffix > 100) {
                // Fallback: добавляем случайные символы
                return $baseUsername . '_' . bin2hex(random_bytes(4));
            }
        }
    }

    /**
     * Отправляет email с учётными данными
     */
    private static function sendPasswordEmail(string $email, string $username, string $password, string $name): bool
    {
        try {
            $app = Factory::getApplication();
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

            $siteName = $app->get('sitename', 'Cacao.Land');
            $siteUrl = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');

            $subject = Text::sprintf('COM_RADICALMART_TELEGRAM_ACCOUNT_CREATED_SUBJECT', $siteName);

            $body = Text::sprintf(
                'COM_RADICALMART_TELEGRAM_ACCOUNT_CREATED_BODY',
                $name ?: $username,
                $siteName,
                $siteUrl,
                $username,
                $password
            );

            $mailer->addRecipient($email, $name);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);

            return $mailer->Send();
        } catch (\Exception $e) {
            LogHelper::error('Failed to send password email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Отправляет учётные данные в Telegram
     */
    private static function sendPasswordTelegram(int $chatId, string $username, string $password): bool
    {
        try {
            $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_radicalmart_telegram');
            $botToken = $params->get('bot_token', '');

            if (empty($botToken)) {
                return false;
            }

            $siteName = Factory::getApplication()->get('sitename', 'Cacao.Land');
            $siteUrl = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');

            $message = Text::sprintf(
                'COM_RADICALMART_TELEGRAM_ACCOUNT_CREATED_TG',
                $siteName,
                $siteUrl,
                $username,
                $password
            );

            $client = new \Joomla\Component\RadicalMartTelegram\Site\Service\TelegramClient($botToken);
            $client->sendMessage($chatId, $message);

            return true;
        } catch (\Exception $e) {
            LogHelper::error('Failed to send password to Telegram: ' . $e->getMessage());
            return false;
        }
    }

    private static function incrementAttempts(int $chatId, int $newCount): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set($db->quoteName('email_verification_attempts') . ' = :count')
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':count', $newCount, ParameterType::INTEGER)
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    private static function resetAttempts(int $chatId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set($db->quoteName('email_verification_attempts') . ' = 0')
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    private static function lockout(int $chatId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $expires = (new Date())->modify('+' . self::LOCKOUT_MINUTES . ' minutes')->toSql();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set([$db->quoteName('email_verification_expires') . ' = :expires', $db->quoteName('email_verification_code') . ' = NULL'])
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':expires', $expires)
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    public static function sendVerificationEmail(string $email, string $code, int $chatId): bool
    {
        try {
            $app = Factory::getApplication();
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $config = $app->getConfig();
            $siteName = $config->get('sitename', 'Cacao.Land');

            // Generate link token
            $token = self::generateToken($chatId);
            $verifyUrl = \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_radicalmart_telegram&task=api.verifyEmailLink&token=' . urlencode($token);

            $subject = Text::sprintf('COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_SUBJECT', $siteName);
            $body = Text::sprintf(
                'COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_BODY_LINK',
                $code,
                self::CODE_EXPIRES_MINUTES,
                $verifyUrl
            );

            // Fallback to old format if new key doesn't exist
            if ($body === 'COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_BODY_LINK') {
                $body = Text::sprintf('COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_BODY', $code, self::CODE_EXPIRES_MINUTES);
                $body .= "\n\n" . Text::_('COM_RADICALMART_TELEGRAM_EMAIL_OR_CLICK_LINK') . "\n" . $verifyUrl;
            }

            $mailer->addRecipient($email);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $result = $mailer->send();
            LogHelper::info('Verification email sent to ' . $email . ', result: ' . ($result ? 'OK' : 'FAIL'));
            return (bool) $result;
        } catch (\Exception $e) {
            LogHelper::error('Failed to send verification email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify email by token (from link click)
     */
    public static function verifyByToken(string $token): array
    {
        $parsed = self::parseToken($token);
        if (!$parsed) {
            return ['success' => false, 'error' => 'INVALID_TOKEN', 'chatId' => null];
        }

        $chatId = $parsed['chatId'];
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['email', 'email_verification_code', 'email_verification_expires', 'email_verification_attempts'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);

        $row = $db->setQuery($query, 0, 1)->loadAssoc();

        if (!$row) {
            return ['success' => false, 'error' => 'USER_NOT_FOUND', 'chatId' => $chatId];
        }

        $now = new Date();
        $attempts = (int) $row['email_verification_attempts'];

        // Check if already verified
        if (empty($row['email_verification_code'])) {
            // Check if email is already verified
            $emailData = self::getEmailData($chatId);
            if ($emailData && (int) $emailData['email_verified'] === 1) {
                return ['success' => true, 'error' => null, 'chatId' => $chatId, 'alreadyVerified' => true];
            }
            return ['success' => false, 'error' => 'NO_CODE_PENDING', 'chatId' => $chatId];
        }

        // Check if locked out
        if ($attempts >= self::MAX_ATTEMPTS) {
            $expires = $row['email_verification_expires'] ? new Date($row['email_verification_expires']) : null;
            if ($expires && $expires > $now) {
                return ['success' => false, 'error' => 'TOO_MANY_ATTEMPTS', 'chatId' => $chatId];
            }
        }

        // Check expiration
        $expires = new Date($row['email_verification_expires']);
        if ($expires < $now) {
            return ['success' => false, 'error' => 'CODE_EXPIRED', 'chatId' => $chatId];
        }

        // Token is valid based on chatId match and code existence - mark as verified
        self::markVerified($chatId);

        return ['success' => true, 'error' => null, 'chatId' => $chatId, 'email' => $row['email']];
    }

    public static function getEmailData(int $chatId): ?array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['email', 'email_verified', 'acymailing_subscribed'])
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        return $db->setQuery($query, 0, 1)->loadAssoc();
    }

    public static function updateEmail(int $chatId, ?string $email, bool $force = false): array
    {
        $email = $email ? trim($email) : null;
        if ($email !== null) {
            $validation = self::validateFormat($email);
            if (!$validation['valid']) {
                return ['success' => false, 'changed' => false, 'error' => $validation['error']];
            }
            $uniqueness = self::checkUniqueness($email, $chatId);
            if (!$uniqueness['unique']) {
                return ['success' => false, 'changed' => false, 'error' => $uniqueness['error']];
            }
        }
        $current = self::getEmailData($chatId);
        $currentEmail = $current['email'] ?? null;
        $changed = ($email !== $currentEmail) || $force;
        if (!$changed) {
            return ['success' => true, 'changed' => false, 'error' => null];
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_users'))
            ->set([$db->quoteName('email') . ' = ' . ($email ? $db->quote($email) : 'NULL'), $db->quoteName('email_verified') . ' = 0', $db->quoteName('email_verification_code') . ' = NULL', $db->quoteName('email_verification_expires') . ' = NULL', $db->quoteName('email_verification_attempts') . ' = 0', $db->quoteName('acymailing_subscribed') . ' = 0'])
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId, ParameterType::INTEGER);
        try {
            $db->setQuery($query)->execute();
            return ['success' => true, 'changed' => true, 'error' => null];
        } catch (\Exception $e) {
            LogHelper::error('Failed to update email: ' . $e->getMessage());
            return ['success' => false, 'changed' => false, 'error' => 'DB_ERROR'];
        }
    }
}
