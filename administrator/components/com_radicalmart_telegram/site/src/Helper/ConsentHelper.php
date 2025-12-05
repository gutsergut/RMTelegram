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
}
