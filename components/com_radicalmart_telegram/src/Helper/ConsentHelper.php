<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Site Helper
 * @author      Sergey Tolkachyov
 * @copyright   Copyright (C) 2025 Sergey Tolkachyov. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       5.0.1
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/**
 * Consent Helper for GDPR/ФЗ-152 compliance
 *
 * @since  5.0.1
 */
class ConsentHelper
{
	/**
	 * Check if user has given consent for personal data processing
	 *
	 * @param   int  $chatId  Telegram chat ID
	 *
	 * @return  bool
	 *
	 * @since   5.0.1
	 */
	public static function hasPersonalDataConsent(int $chatId): bool
	{
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select('consent_personal_data')
				->from($db->quoteName('#__radicalmart_telegram_users'))
				->where($db->quoteName('chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			return (int) $db->setQuery($query, 0, 1)->loadResult() === 1;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Check if user has given consent for marketing communications
	 *
	 * @param   int  $chatId  Telegram chat ID
	 *
	 * @return  bool
	 *
	 * @since   5.0.1
	 */
	public static function hasMarketingConsent(int $chatId): bool
	{
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select('consent_marketing')
				->from($db->quoteName('#__radicalmart_telegram_users'))
				->where($db->quoteName('chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			return (int) $db->setQuery($query, 0, 1)->loadResult() === 1;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Save consent with timestamp
	 *
	 * @param   int     $chatId       Telegram chat ID
	 * @param   string  $consentType  Type: 'personal_data', 'marketing', 'terms'
	 * @param   bool    $value        Consent value (true = given, false = revoked)
	 *
	 * @return  bool
	 *
	 * @since   5.0.1
	 */
	public static function saveConsent(int $chatId, string $consentType, bool $value = true): bool
	{
		$allowedTypes = ['personal_data', 'marketing', 'terms'];

		if (!in_array($consentType, $allowedTypes)) {
			return false;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$now = (new Date())->toSql();

			$field = 'consent_' . $consentType;
			$timestampField = $field . '_at';

			// Check if user exists
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__radicalmart_telegram_users'))
				->where($db->quoteName('chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			$userId = (int) $db->setQuery($query, 0, 1)->loadResult();

			$intValue = $value ? 1 : 0;
			$timestamp = $value ? $now : null;

			if ($userId > 0) {
				// Update existing user
				$query = $db->getQuery(true)
					->update($db->quoteName('#__radicalmart_telegram_users'))
					->set($db->quoteName($field) . ' = ' . $intValue)
					->set($db->quoteName($timestampField) . ' = ' . ($timestamp ? $db->quote($timestamp) : 'NULL'))
					->where($db->quoteName('id') . ' = :id')
					->bind(':id', $userId, ParameterType::INTEGER);

				$db->setQuery($query)->execute();
			} else {
				// Insert new user
				$obj = (object) [
					'chat_id' => $chatId,
					$field => $intValue,
					$timestampField => $timestamp,
					'created' => $now,
				];
				$db->insertObject('#__radicalmart_telegram_users', $obj);
			}

			// Синхронизируем согласие с com_j_sms_registration (#__privacy_consents)
			// когда пользователь принимает политику personal_data
			if ($consentType === 'personal_data' && $value) {
				EmailVerificationHelper::syncPrivacyConsent($chatId, 0);
			}

			// Синхронизируем согласие на маркетинг с AcyMailing
			if ($consentType === 'marketing') {
				self::syncAcyMailingSubscription($chatId, $value);
			}

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Get all consent statuses for a user
	 *
	 * @param   int  $chatId  Telegram chat ID
	 *
	 * @return  array  Array with keys: personal_data, marketing, terms (bool values)
	 *
	 * @since   5.0.1
	 */
	public static function getConsents(int $chatId): array
	{
		$defaults = [
			'personal_data' => false,
			'marketing' => false,
			'terms' => false,
		];

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select([
					'consent_personal_data',
					'consent_marketing',
					'consent_terms'
				])
				->from($db->quoteName('#__radicalmart_telegram_users'))
				->where($db->quoteName('chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			$result = $db->setQuery($query, 0, 1)->loadAssoc();

			if (!$result) {
				return $defaults;
			}

			return [
				'personal_data' => (int) $result['consent_personal_data'] === 1,
				'marketing' => (int) $result['consent_marketing'] === 1,
				'terms' => (int) $result['consent_terms'] === 1,
			];
		} catch (\Exception $e) {
			return $defaults;
		}
	}

	/**
	 * Get URL of a legal document from component settings
	 *
	 * @param   string  $type  Document type: 'privacy', 'consent', 'terms', 'marketing'
	 *
	 * @return  string  Full URL to the article or empty string if not configured
	 *
	 * @since   5.0.1
	 */
	public static function getDocumentUrl(string $type): string
	{
		$params = ComponentHelper::getParams('com_radicalmart_telegram');

		// Map type to config field name
		$fieldMap = [
			'privacy' => 'article_privacy_policy',
			'consent' => 'article_consent_personal_data',
			'terms' => 'article_terms_of_service',
			'marketing' => 'article_consent_marketing',
		];

		if (!isset($fieldMap[$type])) {
			return '';
		}

		$articleId = (int) $params->get($fieldMap[$type], 0);

		if ($articleId <= 0) {
			return '';
		}

		// Build full URL to article
		try {
			$url = Route::link('site', 'index.php?option=com_content&view=article&id=' . $articleId, false, Route::TLS_IGNORE, true);
			return $url;
		} catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * Get all legal document URLs
	 *
	 * @return  array  Associative array with keys: privacy, consent, terms, marketing
	 *
	 * @since   5.0.1
	 */
	public static function getAllDocumentUrls(): array
	{
		return [
			'privacy' => self::getDocumentUrl('privacy'),
			'consent' => self::getDocumentUrl('consent'),
			'terms' => self::getDocumentUrl('terms'),
			'marketing' => self::getDocumentUrl('marketing'),
		];
	}

	/**
	 * Sync marketing consent with AcyMailing subscription
	 *
	 * @param   int   $chatId  Telegram chat ID
	 * @param   bool  $subscribe  True to subscribe, false to unsubscribe
	 *
	 * @return  bool
	 *
	 * @since   5.0.2
	 */
	protected static function syncAcyMailingSubscription(int $chatId, bool $subscribe): bool
	{
		try {
			// Check if AcyMailing integration is enabled
			if (!AcyMailingHelper::isEnabled()) {
				return false;
			}

			// Get user email from telegram_users or linked Joomla user
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select(['u.email AS tg_email', 'u.first_name', 'u.username', 'u.user_id', 'ju.email AS joomla_email', 'ju.name AS joomla_name'])
				->from($db->quoteName('#__radicalmart_telegram_users', 'u'))
				->join('LEFT', $db->quoteName('#__users', 'ju') . ' ON ju.id = u.user_id')
				->where($db->quoteName('u.chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			$user = $db->setQuery($query, 0, 1)->loadObject();

			if (!$user) {
				LogHelper::debug('AcyMailing sync: no user found for chat ' . $chatId);
				return false;
			}

			// Prefer Joomla email, then telegram email
			$email = !empty($user->joomla_email) ? $user->joomla_email : (!empty($user->tg_email) ? $user->tg_email : '');

			if (empty($email)) {
				LogHelper::debug('AcyMailing sync: no email for chat ' . $chatId);
				return false;
			}

			// Get name
			$name = !empty($user->joomla_name) ? $user->joomla_name : (!empty($user->first_name) ? $user->first_name : $user->username);

			if ($subscribe) {
				$result = AcyMailingHelper::subscribe($email, $name ?: '');
				if ($result) {
					// Update acymailing_subscribed flag in our table
					self::updateAcyMailingFlag($chatId, true);
				}
				return $result;
			} else {
				$result = AcyMailingHelper::unsubscribe($email);
				if ($result) {
					self::updateAcyMailingFlag($chatId, false);
				}
				return $result;
			}
		} catch (\Exception $e) {
			LogHelper::error('AcyMailing sync error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update acymailing_subscribed flag in telegram_users table
	 *
	 * @param   int   $chatId      Telegram chat ID
	 * @param   bool  $subscribed  Subscription status
	 *
	 * @return  void
	 *
	 * @since   5.0.2
	 */
	protected static function updateAcyMailingFlag(int $chatId, bool $subscribed): void
	{
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$value = $subscribed ? 1 : 0;
			$query = $db->getQuery(true)
				->update($db->quoteName('#__radicalmart_telegram_users'))
				->set($db->quoteName('acymailing_subscribed') . ' = ' . $value)
				->where($db->quoteName('chat_id') . ' = :chat')
				->bind(':chat', $chatId, ParameterType::INTEGER);

			$db->setQuery($query)->execute();
		} catch (\Exception $e) {
			LogHelper::error('AcyMailing flag update error: ' . $e->getMessage());
		}
	}
}
