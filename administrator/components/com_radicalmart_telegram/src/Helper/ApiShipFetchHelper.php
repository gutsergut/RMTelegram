<?php
/*
 * @package     com_radicalmart_telegram
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;

/**
 * Helper для обновления базы ПВЗ ApiShip
 *
 * @since 1.0.0
 */
class ApiShipFetchHelper
{
	/**
	 * Инициализация логирования
	 */
	protected static function initLogger(): void
	{
		Log::addLogger(
			[
				'text_file' => 'com_radicalmart_telegram.php',
				'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
			],
			Log::ALL,
			['com_radicalmart_telegram']
		);
	}

	/**
	 * Выполнить полное обновление базы ПВЗ
	 *
	 * @param   array|null  $providersList  Список провайдеров (если null — берём из настроек)
	 *
	 * @return  array  ['success' => bool, 'message' => string, 'total' => int]
	 * @throws  \Exception
	 * @since   1.0.0
	 */
	public static function fetchAllPoints(?array $providersList = null): array
	{
		self::initLogger();
		$params  = ComponentHelper::getParams('com_radicalmart_telegram');
		$token   = (string) $params->get('apiship_api_key', '');

		if ($providersList === null)
		{
			$list = $params->get('apiship_providers', 'yataxi,cdek,x5');
			// Если providers это уже массив - используем его, иначе парсим строку
			if (is_array($list))
			{
				$providersList = array_filter(array_map('trim', $list));
			}
			else
			{
				$providersList = array_filter(array_map('trim', explode(',', (string) $list)));
			}
		}

		if ($token === '' || empty($providersList))
		{
			Log::add(
				'ApiShip fetch failed: missing token or providers. Token length=' . strlen($token) . ', providers=' . count($providersList),
				Log::WARNING,
				'com_radicalmart_telegram'
			);
			return [
				'success' => false,
				'message' => 'Missing API token or providers list',
				'total' => 0
			];
		}

		Log::add(
			'ApiShip fetch started: providers=' . implode(',', $providersList),
			Log::INFO,
			'com_radicalmart_telegram'
		);

		$db = Factory::getContainer()->get('DatabaseDriver');
		// Operations: 2 = pickup, 3 = pickup+return (see plg_radicalmart_shipping_apiship)
		$operation = [2, 3];
		$totalAll = 0;

		try
		{
			foreach ($providersList as $prov)
			{
				try
				{
					$total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
					Log::add(
						"ApiShip provider '{$prov}': total points = {$total}",
						Log::INFO,
						'com_radicalmart_telegram'
					);
					$totalAll += $total;
				}
				catch (\Throwable $e)
				{
					Log::add(
						"ApiShip provider '{$prov}': ERROR getting total - " . $e->getMessage(),
						Log::ERROR,
						'com_radicalmart_telegram'
					);
					continue;
				}

				$limit = 500;
				$offset = 0;

				while ($offset < $total)
				{
					$chunk = ApiShipHelper::getPoints($token, [$prov], $operation, $offset, $limit);
					if (!$chunk)
					{
						Log::add(
							"ApiShip provider '{$prov}': empty chunk at offset {$offset}",
							Log::WARNING,
							'com_radicalmart_telegram'
						);
						break;
					}

					Log::add(
						"ApiShip provider '{$prov}': fetched " . count($chunk) . " points at offset {$offset}",
						Log::INFO,
						'com_radicalmart_telegram'
					);

					// Batch INSERT для производительности
					$values = [];
					$now = (new \Joomla\CMS\Date\Date())->toSql();

					foreach ($chunk as $row)
					{
						// Convert stdClass to array if needed
						$row = (array) $row;

						$extId   = (string) ($row['id'] ?? ($row['externalId'] ?? ''));
						$title   = (string) ($row['title'] ?? ($row['name'] ?? ''));
						$address = (string) ($row['address'] ?? '');
						$lat     = isset($row['latitude']) ? (float)$row['latitude'] : (isset($row['location']['latitude']) ? (float)$row['location']['latitude'] : null);
						$lon     = isset($row['longitude']) ? (float)$row['longitude'] : (isset($row['location']['longitude']) ? (float)$row['location']['longitude'] : null);

						if ($lat === null || $lon === null || $extId === '')
						{
							continue;
						}

						$meta = json_encode($row, JSON_UNESCAPED_UNICODE);

						$values[] = "("
							. $db->quote($prov) . ","
							. $db->quote($extId) . ","
							. $db->quote($title) . ","
							. $db->quote($address) . ","
							. (string)$lat . ","
							. (string)$lon . ","
							. $db->quote('giveout') . ","
							. "ST_GeomFromText('POINT(" . (string)$lon . " " . (string)$lat . ")', 4326),"
							. $db->quote($meta) . ","
							. $db->quote($now)
							. ")";
					}

					// Выполняем batch INSERT если есть данные
					if (!empty($values))
					{
						$sql = "INSERT INTO " . $db->quoteName('#__radicalmart_apiship_points') . "
							(" . $db->quoteName('provider') . ", " . $db->quoteName('ext_id') . ", "
							. $db->quoteName('title') . ", " . $db->quoteName('address') . ", "
							. $db->quoteName('lat') . ", " . $db->quoteName('lon') . ", "
							. $db->quoteName('operation') . ", " . $db->quoteName('point') . ", "
							. $db->quoteName('meta') . ", " . $db->quoteName('updated_at') . ")
							VALUES " . implode(", ", $values) . "
							ON DUPLICATE KEY UPDATE
								" . $db->quoteName('title') . " = VALUES(" . $db->quoteName('title') . "),
								" . $db->quoteName('address') . " = VALUES(" . $db->quoteName('address') . "),
								" . $db->quoteName('lat') . " = VALUES(" . $db->quoteName('lat') . "),
								" . $db->quoteName('lon') . " = VALUES(" . $db->quoteName('lon') . "),
								" . $db->quoteName('point') . " = VALUES(" . $db->quoteName('point') . "),
								" . $db->quoteName('meta') . " = VALUES(" . $db->quoteName('meta') . "),
								" . $db->quoteName('updated_at') . " = VALUES(" . $db->quoteName('updated_at') . ")";

						$db->setQuery($sql)->execute();

						Log::add(
							"ApiShip provider '{$prov}': inserted " . count($values) . " points",
							Log::INFO,
							'com_radicalmart_telegram'
						);
					}					$offset += $limit;
				}

				// Обновляем метаданные о последнем обновлении
				$metaNow = (new \Joomla\CMS\Date\Date())->toSql();
				$metaSql = "INSERT INTO " . $db->quoteName('#__radicalmart_apiship_meta') . "
					(" . $db->quoteName('provider') . ", " . $db->quoteName('last_fetch') . ", "
					. $db->quoteName('last_total') . ")
					VALUES (
						" . $db->quote($prov) . ",
						" . $db->quote($metaNow) . ",
						" . (string)$total . "
					)
					ON DUPLICATE KEY UPDATE
						" . $db->quoteName('last_fetch') . " = VALUES(" . $db->quoteName('last_fetch') . "),
						" . $db->quoteName('last_total') . " = VALUES(" . $db->quoteName('last_total') . ")";

				$db->setQuery($metaSql)->execute();
			}

			Log::add(
				'ApiShip fetch completed: total=' . $totalAll . ', providers=' . implode(',', $providersList),
				Log::INFO,
				'com_radicalmart_telegram'
			);

			return [
				'success' => true,
				'message' => 'Updated ' . $totalAll . ' points from ' . count($providersList) . ' provider(s)',
				'total' => $totalAll
			];
		}
		catch (\Throwable $e)
		{
			Log::add(
				'ApiShip fetch error: ' . $e->getMessage(),
				Log::ERROR,
				'com_radicalmart_telegram'
			);

			return [
				'success' => false,
				'message' => $e->getMessage(),
				'total' => $totalAll
			];
		}
	}

	/**
	 * Получить информацию о провайдерах для пошагового обновления
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.0
	 */
	public static function getProvidersInfo(): array
	{
		$params = ComponentHelper::getParams('com_radicalmart_telegram');
		$token = $params->get('apiship_api_key');
		$providersList = array_filter(explode(',', $params->get('apiship_providers', 'yataxi,cdek,x5')));

		if (empty($token) || empty($providersList)) {
			throw new \Exception('Missing API token or providers list');
		}

		$operation = [2, 3];
		$providers = [];

		foreach ($providersList as $prov) {
			$total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
			$providers[] = [
				'code' => $prov,
				'name' => $prov,
				'total' => $total
			];
		}

		return [
			'success' => true,
			'providers' => $providers,
			'batchSize' => 500
		];
	}

	/**
	 * Загрузить один шаг (batch) точек ПВЗ
	 *
	 * @param string $provider Код провайдера
	 * @param int $offset Смещение
	 * @param int $batchSize Размер батча
	 *
	 * @return array
	 * @throws \Exception
	 * @since 1.0.0
	 */
	public static function fetchPointsStep(string $provider, int $offset, int $batchSize = 500): array
	{
		$params = ComponentHelper::getParams('com_radicalmart_telegram');
		$token = $params->get('apiship_api_key');

		if (empty($token)) {
			throw new \Exception('Missing API token');
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$operation = [2, 3];

		// Получаем чанк точек
		$chunk = ApiShipHelper::getPoints($token, [$provider], $operation, $offset, $batchSize);

		if (empty($chunk)) {
			return [
				'success' => true,
				'provider' => $provider,
				'offset' => $offset,
				'fetched' => 0,
				'inserted' => 0,
				'completed' => true
			];
		}

		// Batch INSERT
		$values = [];
		$now = (new \Joomla\CMS\Date\Date())->toSql();

		foreach ($chunk as $row) {
			// Convert stdClass to array if needed
			$row = (array) $row;

			$extId   = (string) ($row['id'] ?? ($row['externalId'] ?? ''));
			$title   = (string) ($row['title'] ?? ($row['name'] ?? ''));
			$address = (string) ($row['address'] ?? '');
			$lat     = isset($row['latitude']) ? (float)$row['latitude'] : (isset($row['location']['latitude']) ? (float)$row['location']['latitude'] : null);
			$lon     = isset($row['longitude']) ? (float)$row['longitude'] : (isset($row['location']['longitude']) ? (float)$row['location']['longitude'] : null);

			if ($lat === null || $lon === null || $extId === '') {
				continue;
			}

			$meta = json_encode($row, JSON_UNESCAPED_UNICODE);

			$values[] = "("
				. $db->quote($provider) . ","
				. $db->quote($extId) . ","
				. $db->quote($title) . ","
				. $db->quote($address) . ","
				. (string)$lat . ","
				. (string)$lon . ","
				. $db->quote('giveout') . ","
				. "ST_GeomFromText('POINT(" . (string)$lon . " " . (string)$lat . ")', 4326),"
				. $db->quote($meta) . ","
				. $db->quote($now)
				. ")";
		}

		// Выполняем batch INSERT если есть данные
		$inserted = 0;
		if (!empty($values)) {
			$sql = "INSERT INTO " . $db->quoteName('#__radicalmart_apiship_points') . "
				(" . $db->quoteName('provider') . ", " . $db->quoteName('ext_id') . ", "
				. $db->quoteName('title') . ", " . $db->quoteName('address') . ", "
				. $db->quoteName('lat') . ", " . $db->quoteName('lon') . ", "
				. $db->quoteName('operation') . ", " . $db->quoteName('point') . ", "
				. $db->quoteName('meta') . ", " . $db->quoteName('updated_at') . ")
				VALUES " . implode(", ", $values) . "
				ON DUPLICATE KEY UPDATE
					" . $db->quoteName('title') . " = VALUES(" . $db->quoteName('title') . "),
					" . $db->quoteName('address') . " = VALUES(" . $db->quoteName('address') . "),
					" . $db->quoteName('lat') . " = VALUES(" . $db->quoteName('lat') . "),
					" . $db->quoteName('lon') . " = VALUES(" . $db->quoteName('lon') . "),
					" . $db->quoteName('point') . " = VALUES(" . $db->quoteName('point') . "),
					" . $db->quoteName('meta') . " = VALUES(" . $db->quoteName('meta') . "),
					" . $db->quoteName('updated_at') . " = VALUES(" . $db->quoteName('updated_at') . ")";

			try {
				$db->setQuery($sql)->execute();
				$inserted = count($values);

				// Логируем успех
				$params = ComponentHelper::getParams('com_radicalmart_telegram');
				if ($params->get('logs_enabled', 1)) {
					Log::add(
						sprintf('Inserted %d points for provider %s at offset %d', $inserted, $provider, $offset),
						Log::INFO,
						'com_radicalmart_telegram'
					);
				}
			} catch (\Exception $e) {
				// Логируем ошибку
				$params = ComponentHelper::getParams('com_radicalmart_telegram');
				if ($params->get('logs_enabled', 1)) {
					Log::add(
						sprintf('Error inserting points for %s: %s', $provider, $e->getMessage()),
						Log::ERROR,
						'com_radicalmart_telegram'
					);
				}
				throw $e;
			}
		}

		return [
			'success' => true,
			'provider' => $provider,
			'offset' => $offset,
			'fetched' => count($chunk),
			'inserted' => $inserted,
			'completed' => count($chunk) < $batchSize
		];
	}
}
