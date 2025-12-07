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
        
        // Получаем email и имя перед обновлением
        $query = $db->getQuery(true)
            ->select(['email', 'tg_first_name', 'tg_last_name'])
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
            
            // Подписываем на AcyMailing после успешной верификации
            if ($userData && !empty($userData['email'])) {
                $name = trim(($userData['tg_first_name'] ?? '') . ' ' . ($userData['tg_last_name'] ?? ''));
                AcyMailingHelper::subscribeAndUpdateFlag($chatId, $userData['email'], $name);
            }
            
            return true;
        } catch (\Exception $e) {
            LogHelper::error('Failed to mark email verified: ' . $e->getMessage());
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
