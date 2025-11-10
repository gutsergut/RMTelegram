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
use Joomla\CMS\Http\Http;
use Joomla\Registry\Registry;

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
						// Координаты могут приходить в разных полях: (lat,lng) или (latitude,longitude) или location.{latitude,longitude}
						if (isset($row['lat']) || isset($row['lng'])) {
							$lat = isset($row['lat']) ? (float) $row['lat'] : null;
							$lon = isset($row['lng']) ? (float) $row['lng'] : null;
						} elseif (isset($row['latitude']) || isset($row['longitude'])) {
							$lat = isset($row['latitude']) ? (float) $row['latitude'] : null;
							$lon = isset($row['longitude']) ? (float) $row['longitude'] : null;
						} else {
							$lat = isset($row['location']['latitude']) ? (float) $row['location']['latitude'] : null;
							$lon = isset($row['location']['longitude']) ? (float) $row['location']['longitude'] : null;
						}

						if ($lat === null || $lon === null || $extId === '')
						{
							continue;
						}

						$meta = json_encode($row, JSON_UNESCAPED_UNICODE);

						$pvzType = (string) ($row['type'] ?? '');
						$values[] = "("
							. $db->quote($prov) . ","
							. $db->quote($extId) . ","
							. $db->quote($title) . ","
							. $db->quote($address) . ","
							. (string)$lat . ","
							. (string)$lon . ","
							. $db->quote('giveout') . ","
							. $db->quote($pvzType) . ","
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
							. $db->quoteName('operation') . ", " . $db->quoteName('pvz_type') . ", " . $db->quoteName('point') . ", "
							. $db->quoteName('meta') . ", " . $db->quoteName('updated_at') . ")
							VALUES " . implode(", ", $values) . "
							ON DUPLICATE KEY UPDATE
								" . $db->quoteName('title') . " = VALUES(" . $db->quoteName('title') . "),
								" . $db->quoteName('address') . " = VALUES(" . $db->quoteName('address') . "),
								" . $db->quoteName('lat') . " = VALUES(" . $db->quoteName('lat') . "),
								" . $db->quoteName('lon') . " = VALUES(" . $db->quoteName('lon') . "),
								" . $db->quoteName('pvz_type') . " = VALUES(" . $db->quoteName('pvz_type') . "),
								" . $db->quoteName('point') . " = VALUES(" . $db->quoteName('point') . "),
								" . $db->quoteName('meta') . " = VALUES(" . $db->quoteName('meta') . "),
								" . $db->quoteName('updated_at') . " = VALUES(" . $db->quoteName('updated_at') . ")";

						try {
							$db->setQuery($sql)->execute();

							Log::add(
								"ApiShip provider '{$prov}': inserted " . count($values) . " points at offset {$offset}",
								Log::INFO,
								'com_radicalmart_telegram'
							);
						} catch (\Exception $e) {
							Log::add(
								"ApiShip provider '{$prov}': INSERT ERROR at offset {$offset}: " . $e->getMessage(),
								Log::ERROR,
								'com_radicalmart_telegram'
							);
							throw $e;
						}
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
		$debug = [];
		$debug[] = 'getProvidersInfo called';

		$params = ComponentHelper::getParams('com_radicalmart_telegram');
		$token = $params->get('apiship_api_key');
		$providersList = array_filter(explode(',', $params->get('apiship_providers', 'yataxi,cdek,x5')));
		$debug[] = 'Got token: ' . substr($token, 0, 10) . '..., providers: ' . implode(',', $providersList);

		if (empty($token) || empty($providersList)) {
			$debug[] = 'Token or providers empty!';
			throw new \Exception('Missing API token or providers list');
		}

		$operation = [2, 3];
		$providers = [];
		$debug[] = 'Getting totals for each provider...';

		foreach ($providersList as $prov) {
			$debug[] = "Calling getPointsTotal for $prov...";
			$total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
			$debug[] = "$prov total: $total";
			$providers[] = [
				'code' => $prov,
				'name' => $prov,
				'total' => $total
			];
		}

		$debug[] = 'Returning ' . count($providers) . ' providers';
		return [
			'success' => true,
			'providers' => $providers,
			'batchSize' => 500,
			'debug' => $debug
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
		$debug = [];
		$debug[] = "fetchPointsStep called: provider=$provider, offset=$offset, batchSize=$batchSize";
		// Внешний лог для быстрой диагностики шагов
		Log::add(
			"[PVZ] Step begin provider=$provider mode=init offset=$offset batch=$batchSize",
			Log::INFO,
			'com_radicalmart_telegram'
		);

		$app = \Joomla\CMS\Factory::getApplication();
		$session = $app->getSession();
		$cursorKey = 'rm_apiship_cursor_lastId_' . $provider;
		$totalKey = 'rm_apiship_total_' . $provider;
		$lastHashKey = 'rm_apiship_last_hash_' . $provider;
		$repeatCountKey = 'rm_apiship_repeat_count_' . $provider;
		$forceCursorKey = 'rm_apiship_force_cursor_' . $provider;
		$dirKey = 'rm_apiship_cursor_dir_' . $provider; // 'asc'|'desc'
		$lastFirstKey = 'rm_apiship_last_first_id_' . $provider;
		$lastLastKey  = 'rm_apiship_last_last_id_' . $provider;
		$ascProbeKey  = 'rm_apiship_asc_probe_' . $provider; // track asc probe attempt
		if ($offset === 0) {
			// Сброс курсора в начале провайдера
			$session->set($cursorKey, 0);
			$session->set($lastHashKey, '');
			$session->set($repeatCountKey, 0);
			$session->set($forceCursorKey, 0);
			$session->set($dirKey, '');
			$session->set($lastFirstKey, 0);
			$session->set($lastLastKey, 0);
			$session->set($ascProbeKey, 0);
			// Сброс состояния sweep-фазы для x5 (во избежание преждевременного завершения при остатках сессии)
			if ($provider === 'x5') {
				$session->set('rm_apiship_x5_sweep_phase_' . $provider, '');
				$session->set('rm_apiship_x5_sweep_offset_' . $provider, 0);
				$session->set('rm_apiship_x5_sweep_empty_count_' . $provider, 0);
				$debug[] = 'x5 sweep state reset at offset=0';
			}
			$debug[] = 'Cursor reset for provider';
		}

		$params = ComponentHelper::getParams('com_radicalmart_telegram');
		$token = $params->get('apiship_api_key');
		$debug[] = 'Got params and token: ' . substr($token, 0, 10) . '...';

		if (empty($token)) {
			$debug[] = 'Token is empty!';
			throw new \Exception('Missing API token');
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$operation = [2, 3];
		$debug[] = 'Got DB connection';

		// Получаем и кэшируем общее количество точек провайдера (для корректного remaining/completed)
		$providerTotal = (int) ($session->get($totalKey, 0) ?: 0);
		if ($offset === 0 || $providerTotal <= 0) {
			try {
				$providerTotal = (int) ApiShipHelper::getPointsTotal($token, [$provider], $operation);
				$session->set($totalKey, $providerTotal);
				$debug[] = 'Provider total fetched and cached: ' . $providerTotal;
			} catch (\Throwable $e) {
				$debug[] = 'Provider total fetch failed: ' . $e->getMessage();
			}
		} else {
			$debug[] = 'Provider total from session: ' . $providerTotal;
		}

		// Получаем флаг принудительного cursor
		$forceCursor = (int) ($session->get($forceCursorKey, 0) ?: 0);

		// Получаем чанк точек
		// Попытка обойти проблемы пагинации offset: при больших offset переключаемся на keyset-пагинацию (id > lastId)
		// Провайдер x5: по умолчанию cursor отключён, НО можем принудительно включить при обнаружении повторов страниц
		// Sweep fallback для x5: если ранее мы зафиксировали переход в sweep-фазу, отключаем cursor полностью
		$sweepPhaseKey = 'rm_apiship_x5_sweep_phase_' . $provider; // '', 'offset'
		$sweepOffsetKey = 'rm_apiship_x5_sweep_offset_' . $provider; // текущий offset в sweep
		$sweepEmptyCountKey = 'rm_apiship_x5_sweep_empty_count_' . $provider; // подряд пустых чанков
		$sweepPhase = (string) ($session->get($sweepPhaseKey, '') ?: '');
		if ($provider === 'x5' && $sweepPhase === 'offset') {
			$debug[] = 'x5 sweep phase active: offset mode enforced';
		}
		// x5: ПОЛНОСТЬЮ отключаем курсор — API не поддерживает фильтр id<N/id>N
		// Даже при повторах offset используем только offset с кэшбастером
		$useCursor = ($offset >= 1000) && ($provider !== 'x5') && !($provider === 'x5' && $sweepPhase === 'offset');
		if ($provider === 'x5' && $offset >= 1000) {
			if ($forceCursor === 1) {
				$debug[] = 'Provider x5: cursor disabled (API incompatible), using offset with cache buster';
				// Сбрасываем forceCursor для x5, чтобы не зациклиться
				$session->set($forceCursorKey, 0);
			} else {
				$debug[] = 'Provider x5: cursor mode disabled, using offset only due to provider-specific limitation';
			}
		}
		$mode = $useCursor ? 'cursor' : 'offset';
		$remaining = null;
		$completedReason = '';
		if ($useCursor) {
			// If cursor is forced due to repeat anomaly and direction not set yet — start with ASC probe
			if ((int)($session->get($forceCursorKey, 0) ?: 0) === 1) {
				$curDirTmp = (string) ($session->get($dirKey, '') ?: '');
				if ($curDirTmp === '') {
					$session->set($dirKey, 'asc');
					$session->set($ascProbeKey, 1);
					$debug[] = 'Asc-probe engaged: set initial cursor direction to asc';
				}
			}
			// Берём lastId из сессии текущего запуска; это корректнее, чем глобальный MAX из БД
			$db = Factory::getContainer()->get('DatabaseDriver');
			$lastId = (int) ($session->get($cursorKey, 0) ?: 0);
			$dir = (string) ($session->get($dirKey, '') ?: '');
			$debug[] = 'Cursor lastId from session: ' . $lastId;
			$filterString = 'providerKey=[' . $provider . '];availableOperation=[2,3]';
			if ($lastId > 0) {
				$op = ($dir === 'desc') ? '<' : '>';
				$filterString .= ';id' . $op . $lastId;
				$debug[] = 'Cursor operator=' . $op . ' (dir=' . ($dir ?: 'n/a') . ')';
			}
			$requestUrl = ApiShipHelper::$apiUrl . '/lists/points?limit=' . $batchSize . '&offset=0&filter=' . urlencode($filterString);
			$debug[] = 'Calling ApiShip points (cursor mode)... URL=' . $requestUrl . ' (lastId=' . $lastId . ')';
			// Прямой HTTP-запрос (чтобы не зависеть от версии плагина)
			$http = new Http([ 'transport.curl' => [ CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0 ] ]);
			$headers = [ 'Content-Type' => 'application/json', 'authorization' => $token ];
			try {
				$response = $http->get($requestUrl, $headers);
				$reg = new Registry($response->body);
				$meta = $reg->get('meta', new \stdClass());
				$debug[] = 'ApiShip meta: total=' . ((isset($meta->total)) ? $meta->total : 'n/a')
					. ', limit=' . ((isset($meta->limit)) ? $meta->limit : 'n/a')
					. ', offset=' . ((isset($meta->offset)) ? $meta->offset : 'n/a');
				$remaining = isset($meta->total) ? (int) $meta->total : null;
				$chunk = $reg->get('rows', []);
				// Расширенный лог сырого ответа API (усечённый)
				$bodyLen = is_string($response->body) ? strlen($response->body) : 0;
				$snippet = is_string($response->body) ? substr($response->body, 0, 1024) : '';
				$status = property_exists($response, 'code') ? (int) $response->code : 0;
				Log::add(
					"[PVZ] ApiShip cursor GET status=$status len=$bodyLen metaTotal=" . (isset($meta->total)?$meta->total:'n/a') .
					"; snippet=" . $snippet,
					Log::INFO,
					'com_radicalmart_telegram'
				);
			} catch (\Throwable $e) {
				$debug[] = 'Cursor HTTP error: ' . $e->getMessage();
				// Фоллбек на стандартный метод
				$filterString = ApiShipHelper::convertFilterConditionsToString([
					['key' => 'providerKey', 'operator' => '=', 'value' => [$provider]],
					['key' => 'availableOperation', 'operator' => '=', 'value' => $operation],
				]);
				$requestUrl = ApiShipHelper::$apiUrl . '/lists/points?limit=' . $batchSize . '&offset=' . $offset . '&filter=' . $filterString;
				$debug[] = 'FALLBACK URL=' . $requestUrl;
				$chunk = ApiShipHelper::getPoints($token, [$provider], $operation, $offset, $batchSize);
				$meta = (object) ['total' => -1, 'limit' => $batchSize, 'offset' => $offset];
			}
		} else {
			// Обычный режим с offset
			$rawHttpBodySnippet = '';
			$rawHttpStatus = null;
			$rawHttpLen = 0;
			$filterString = ApiShipHelper::convertFilterConditionsToString([
				[
					'key'      => 'providerKey',
					'operator' => '=',
					'value'    => [$provider]
				],
				[
					'key'      => 'availableOperation',
					'operator' => '=',
					'value'    => $operation
				],
			]);
			// Кэшбастер для x5: добавляем timestamp в URL чтобы избежать CDN/proxy кэша
			$cacheBuster = ($provider === 'x5') ? '&_t=' . time() : '';
			$requestUrl = ApiShipHelper::$apiUrl . '/lists/points?limit=' . $batchSize . '&offset=' . $offset . '&filter=' . urlencode($filterString) . $cacheBuster;
			$debug[] = 'Calling ApiShipHelper::getPoints... URL=' . $requestUrl;
			$sweepPhase = ($provider === 'x5') ? (string) ($session->get('rm_apiship_x5_sweep_phase_' . $provider, '') ?: '') : '';
			// Для x5 в sweep offset—исполняем прямой HTTP GET ради полного тела
			if ($provider === 'x5' && $sweepPhase === 'offset') {
				try {
					$http = new Http([ 'transport.curl' => [ CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0 ] ]);
					$headers = [ 'Content-Type' => 'application/json', 'authorization' => $token ];
					$response = $http->get($requestUrl, $headers);
					$rawHttpStatus = property_exists($response, 'code') ? (int) $response->code : 0;
					$rawHttpBodySnippet = is_string($response->body) ? substr($response->body, 0, 2048) : '';
					$rawHttpLen = is_string($response->body) ? strlen($response->body) : 0;
					Log::add('[PVZ] x5 sweep raw GET status=' . $rawHttpStatus . ' len=' . $rawHttpLen . ' snippet=' . $rawHttpBodySnippet, Log::INFO, 'com_radicalmart_telegram');
				} catch (\Throwable $e) {
					Log::add('[PVZ] x5 sweep raw GET error=' . $e->getMessage(), Log::ERROR, 'com_radicalmart_telegram');
				}
			}
			if (method_exists(ApiShipHelper::class, 'getPointsRegistry')) {
				// Для x5 используем прямой HTTP-запрос с кэшбастером
				if ($provider === 'x5') {
					try {
						$http = new Http([ 'transport.curl' => [ CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0 ] ]);
						$cacheBuster = '&_cb=' . time() . mt_rand(1000,9999);
						$urlWithCache = $requestUrl . $cacheBuster;
						// Дополнительные заголовки для обхода CDN/proxy кэша
						$headers = [
							'Content-Type' => 'application/json',
							'authorization' => $token,
							'Cache-Control' => 'no-cache, no-store, must-revalidate',
							'Pragma' => 'no-cache',
							'Expires' => '0',
							'X-Cache-Bypass' => time() . '-' . mt_rand(100000, 999999)
						];
						$debug[] = 'x5: direct HTTP GET with cache buster + anti-cache headers: ' . $urlWithCache;
						$response = $http->get($urlWithCache, $headers);
						$rawStatus = property_exists($response, 'code') ? (int) $response->code : 0;
						$rawBody = is_string($response->body) ? $response->body : '';
						$debug[] = 'x5: HTTP status=' . $rawStatus . ', body length=' . strlen($rawBody);
						$registry = new Registry($rawBody);
						$meta = $registry->get('meta', new \stdClass());
						$chunk = $registry->get('rows', []);
						$debug[] = 'x5: parsed meta total=' . (isset($meta->total) ? $meta->total : 'n/a') . ', rows=' . count($chunk);
					} catch (\Throwable $e) {
						$debug[] = 'x5: HTTP error: ' . $e->getMessage() . ', falling back to ApiShipHelper';
						$registry = ApiShipHelper::getPointsRegistry($token, [$provider], $operation, $offset, $batchSize);
						$meta = $registry->get('meta', new \stdClass());
						$chunk = $registry->get('rows', []);
					}
				} else {
					$registry = ApiShipHelper::getPointsRegistry($token, [$provider], $operation, $offset, $batchSize);
					$meta = $registry->get('meta', new \stdClass());
					$chunk = $registry->get('rows', []);
				}
				$debug[] = 'ApiShip meta: total=' . ((isset($meta->total)) ? $meta->total : 'n/a')
					. ', limit=' . ((isset($meta->limit)) ? $meta->limit : 'n/a')
					. ', offset=' . ((isset($meta->offset)) ? $meta->offset : 'n/a');
				$remaining = isset($meta->total) ? max(0, (int) $meta->total - $offset - $batchSize) : null;
			} else {
				// Fallback: getPointsRegistry не найден - используем ПРЯМОЙ HTTP запрос
				$debug[] = 'WARNING: getPointsRegistry() not found, using direct HTTP request';
				try {
					$http = new Http([ 'transport.curl' => [ CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0 ] ]);
					$cacheBuster = '&_cb=' . time() . mt_rand(1000,9999);
					$urlWithCache = $requestUrl . $cacheBuster;
					$headers = [
						'Content-Type' => 'application/json',
						'authorization' => $token,
						'Cache-Control' => 'no-cache, no-store, must-revalidate',
						'Pragma' => 'no-cache',
						'Expires' => '0'
					];
					$debug[] = 'Direct HTTP GET (no getPointsRegistry): ' . $urlWithCache;
					$response = $http->get($urlWithCache, $headers);
					$rawStatus = property_exists($response, 'code') ? (int) $response->code : 0;
					$rawBody = is_string($response->body) ? $response->body : '';
					$debug[] = 'HTTP status=' . $rawStatus . ', body length=' . strlen($rawBody);
					$registry = new Registry($rawBody);
					$meta = $registry->get('meta', new \stdClass());
					$chunk = $registry->get('rows', []);
					$debug[] = 'Parsed meta total=' . (isset($meta->total) ? $meta->total : 'n/a') . ', rows=' . count($chunk);
					$remaining = isset($meta->total) ? max(0, (int) $meta->total - $offset - $batchSize) : null;
				} catch (\Throwable $e) {
					$debug[] = 'Direct HTTP error: ' . $e->getMessage() . ', final fallback to getPoints()';
					$chunk = ApiShipHelper::getPoints($token, [$provider], $operation, $offset, $batchSize);
					$meta = (object) ['total' => -1, 'limit' => $batchSize, 'offset' => $offset];
					$debug[] = 'ApiShip meta (synthetic): total=-1, limit=' . $batchSize . ', offset=' . $offset;
				}
			}
		}
		$debug[] = 'getPoints returned ' . count($chunk) . ' items';
		Log::add(
			"[PVZ] Step fetch result provider=$provider mode=$mode items=" . count($chunk),
			Log::INFO,
			'com_radicalmart_telegram'
		);
		$firstIds = [];
		$allExtIdsInChunk = []; // Для подсчёта distinct внутри чанка
		foreach ($chunk as $ci => $crow) {
			if (is_object($crow)) { $crow = get_object_vars($crow); }
			$extId = (string) ($crow['id'] ?? ($crow['externalId'] ?? ($crow['code'] ?? '')));
			if ($extId !== '') {
				$allExtIdsInChunk[$extId] = true;
			}
			if ($ci < 5) {
				$firstIds[] = $extId;
			}
		}
		$distinctInChunk = count($allExtIdsInChunk);
		if ($firstIds) {
			$debug[] = 'First ext_ids sample: ' . implode(',', $firstIds);
		}
		$debug[] = 'Distinct IDs in current chunk: ' . $distinctInChunk . ' / ' . count($chunk);

		// Обновим cursor lastId и определим направление сортировки (ТОЛЬКО в cursor-режиме)
		if ($useCursor) {
			$idsOrdered = [];
			$firstRawNum = null; $lastRawNum = null;
			$chunkCount = count($chunk);
			foreach ($chunk as $idx => $crow2) {
				if (is_object($crow2)) { $crow2 = get_object_vars($crow2); }
				$cidRaw = (string) ($crow2['id'] ?? ($crow2['externalId'] ?? ($crow2['code'] ?? '')));
				$cidNum = (int) preg_replace('/[^0-9]/', '', $cidRaw);
				if ($cidNum > 0) { $idsOrdered[] = $cidNum; }
				if ($idx === 0) { $firstRawNum = $cidNum; }
				if ($idx === $chunkCount - 1) { $lastRawNum = $cidNum; }
			}
			if ($idsOrdered) {
				// Получили непустой чанк — сбрасываем счётчик последовательных пустых шагов в desc
				$session->set('rm_apiship_cursor_desc_empty_count_' . $provider, 0);
				if ($firstRawNum !== null && $lastRawNum !== null) {
					$calcDir = ($firstRawNum > $lastRawNum) ? 'desc' : 'asc';
					if ($dir === '') { $session->set($dirKey, $calcDir); $dir = $calcDir; $debug[] = 'Cursor direction detected: ' . $dir . ' (first=' . $firstRawNum . ', last=' . $lastRawNum . ')'; }
				}
				// Сохраним крайние id чанка в сессии для возможного flip направления при пустом ответе
				$session->set($lastFirstKey, (int) $firstRawNum);
				$session->set($lastLastKey, (int) $lastRawNum);
				if ($dir === 'desc') {
					// В убывающем порядке следующий запрос должен использовать id<минимум текущей страницы
					$nextAnchor = ($lastRawNum !== null) ? $lastRawNum : min($idsOrdered);
					$session->set($cursorKey, $nextAnchor);
					$debug[] = 'Cursor lastId updated (desc, next id<): ' . $nextAnchor;
				} else {
					// По возрастанию — используем максимум текущей страницы
					$nextAnchor = max($idsOrdered);
					$session->set($cursorKey, $nextAnchor);
					$debug[] = 'Cursor lastId updated (asc, next id>): ' . $nextAnchor;
				}
			} else {
				$debug[] = 'Cursor update skipped: no numeric ids extracted';
			}
		}

		// Подготовка hash текущего чанка (по первым 50 ext_id для устойчивости)
		$hashIds = [];
		foreach ($chunk as $iHash => $hrow) {
			if ($iHash >= 50) { break; }
			if (is_object($hrow)) { $hrow = get_object_vars($hrow); }
			$hashIds[] = (string) ($hrow['id'] ?? ($hrow['externalId'] ?? ($hrow['code'] ?? '')));
		}
		$currentHash = md5(implode('|', $hashIds));
		$prevHash = (string) ($session->get($lastHashKey, '') ?: '');
		$repeatCount = (int) ($session->get($repeatCountKey, 0) ?: 0);
		$anomaly = false; $anomalyReason = '';
		if ($prevHash !== '' && $prevHash === $currentHash && !$useCursor) {
			$repeatCount++;
			$debug[] = 'Detected repeated chunk hash (' . $currentHash . '), repeatCount=' . $repeatCount;
			$session->set($repeatCountKey, $repeatCount);
			if ($provider === 'x5' && $repeatCount >= 3) {
				$session->set($forceCursorKey, 1);
				$session->set($dirKey, 'asc');
				$session->set($ascProbeKey, 1);
				$anomaly = true; $anomalyReason = 'sweep-repeating-page';
				$debug[] = 'Repeat threshold (>=3) reached -> force cursor next step (asc probe)';
			}
		} else {
			if ($currentHash !== $prevHash) { $repeatCount = 0; $session->set($repeatCountKey, 0); }
		}
		$session->set($lastHashKey, $currentHash);

		if (empty($chunk)) {
			$debug[] = 'Chunk is empty';
			// Доп. лог текущего состояния курсора и направления
			$curDir = $useCursor ? ($session->get($dirKey, '') ?: '') : '';
			$curAnchor = $useCursor ? (int) ($session->get($cursorKey, 0) ?: 0) : 0;
			Log::add(
				"[PVZ] Empty chunk state provider=$provider mode=$mode dir=$curDir anchor=$curAnchor offset=$offset",
				Log::WARNING,
				'com_radicalmart_telegram'
			);
			// Переопределяем remaining исходя из providerTotal
			if (isset($providerTotal) && $providerTotal > 0) {
				$remainingCalc = max(0, $providerTotal - $offset);
				$debug[] = 'Remaining recalculated (empty chunk): ' . $remainingCalc;
			} else {
				$remainingCalc = $remaining ?? null;
			}
			$completedLocal = false;
			if ($useCursor) {
				// В cursor-режиме по умолчанию пустой чанк = конец, но если ещё есть остаток по нашему total — пробуем развернуть направление
				$completedReason = 'cursor-empty';
				$completedLocal = ($remainingCalc === 0 || ($remaining !== null && (int)$remaining === 0));
				if ($completedLocal && isset($providerTotal) && $providerTotal > 0 && $remainingCalc > 0) {
					$dir = (string) ($session->get($dirKey, '') ?: '');
					$lastFirstNum = (int) ($session->get($lastFirstKey, 0) ?: 0);
					$anchor = (int) ($session->get($cursorKey, 0) ?: 0);
					if ($dir === 'asc' && $lastFirstNum > 0) {
						// Переключаемся на убывание и ставим якорь на начало предыдущего чанка
						$session->set($dirKey, 'desc');
						$session->set($cursorKey, $lastFirstNum);
						$completedLocal = false;
						$completedReason = 'cursor-flip-desc';
						$anomaly = true; $anomalyReason = 'cursor-empty-flip-desc';
						$debug[] = 'Cursor empty but remaining>0 -> flip direction to desc, anchor=' . $lastFirstNum;
					} elseif ($dir === 'desc' && $anchor > 0) {
						// Адаптивный шаг назад при последовательных пустых ответах:
						// 1..9 пустых: -1; 10..49: -10; 50..199: -100; 200+: -1000
						$descEmptyKey = 'rm_apiship_cursor_desc_empty_count_' . $provider;
						$emptyCount = (int) ($session->get($descEmptyKey, 0) ?: 0) + 1;
						$session->set($descEmptyKey, $emptyCount);
						$step = 1;
						if ($emptyCount >= 200) { $step = 1000; }
						elseif ($emptyCount >= 50) { $step = 100; }
						elseif ($emptyCount >= 10) { $step = 10; }

						// Доп. логика для x5: ранний jump и принудительный sweep fallback при длинной серии пустых чанков
						if ($provider === 'x5') {
							// Ранний прыжок уже при >=20 пустых, чтобы быстрее пройти разреженные диапазоны
							if ($emptyCount >= 20) {
								$percentJump = max(1000, (int) floor($anchor * 0.10));
								if ($emptyCount >= 40) { $percentJump = max($percentJump, (int) floor($anchor * 0.20)); }
								if ($percentJump > $step) {
									$debug[] = 'x5 adaptive jump (emptyCount=' . $emptyCount . '): replacing step ' . $step . ' with jump ' . $percentJump;
									$step = $percentJump;
									$anomaly = true; $anomalyReason = 'x5-desc-jump';
								}
							}
							// Если пусто слишком долго (>=30) — переключаемся на sweep offset прямо сейчас
							if ($emptyCount >= 30) {
								$debug[] = 'x5 excessive empties (' . $emptyCount . ') -> activating sweep fallback (offset scan)';
								Log::add('[PVZ] x5 sweep fallback activation (excessive empties)', Log::INFO, 'com_radicalmart_telegram');
								$session->set($sweepPhaseKey, 'offset');
								$session->set($sweepOffsetKey, 0);
								$session->set($sweepEmptyCountKey, 0);
								// Сбрасываем курсор и принудительный флаг для следующего шага
								$session->set($cursorKey, 0);
								$session->set($forceCursorKey, 0);
								$completedLocal = false;
								$completedReason = 'x5-sweep-start';
								$anomaly = true; $anomalyReason = 'x5-desc-excessive-empty-sweep';
							}
						}
						$stepAnchor = max(0, $anchor - $step);
						$session->set($cursorKey, $stepAnchor);
						$completedLocal = false;
						$completedReason = 'cursor-desc-step';
						$anomaly = true; $anomalyReason = 'cursor-empty-desc-step';
						$debug[] = 'Cursor empty in desc but remaining>0 -> step anchor from ' . $anchor . ' by -' . $step . ' to ' . $stepAnchor . ' (emptyCount=' . $emptyCount . ')';
						if ($stepAnchor <= 0) {
							// Вместо мгновенного завершения для x5 попробуем запустить sweep fallback, если providerTotal ещё далёк
							if ($provider === 'x5' && isset($providerTotal) && $providerTotal > 0 && $remainingCalc > 0) {
								$debug[] = 'Desc anchor drained to 0 for x5 with remaining>0 -> activating sweep fallback (offset scan)';
								Log::add('[PVZ] x5 sweep fallback activation (desc drained anchor)', Log::INFO, 'com_radicalmart_telegram');
								$session->set($sweepPhaseKey, 'offset');
								$session->set($sweepOffsetKey, 0);
								$session->set($sweepEmptyCountKey, 0);
								// Принудительно отключаем cursor для следующего шага
								$session->set($cursorKey, 0);
								$completedLocal = false;
								$completedReason = 'x5-sweep-start';
								$anomaly = true; $anomalyReason = 'x5-desc-drained-sweep-start';
							} else {
								$completedLocal = true;
								$completedReason = 'cursor-desc-drained';
								$anomaly = true; $anomalyReason = 'desc-anchor-drained-before-total';
								$debug[] = 'Desc anchor drained to 0 without receiving rows — completing to avoid infinite loop';
							}
						}
					}
				}
			} else {
				// offset-режим: завершаем только если достигли или превысили providerTotal
				if (isset($providerTotal) && $providerTotal > 0) {
					$completedLocal = ($offset >= $providerTotal);
					if ($completedLocal) { $completedReason = 'offset-empty-end'; }
				} else {
					$completedLocal = true; // fallback
					$completedReason = 'offset-empty-unknown-total';
				}
			}
			if (!$completedLocal && isset($providerTotal) && $providerTotal > 0) {
				$anomaly = true; $anomalyReason = 'empty-chunk-before-total';
				$debug[] = 'ANOMALY: empty chunk before reaching providerTotal';
			}
			// Короткая сводка в лог перед возвратом пустого шага
			Log::add(
				"[PVZ] Step empty summary provider=$provider mode=$mode remaining=" . ($remainingCalc ?? -1) .
				" completed=" . ($completedLocal?'1':'0') . " reason=$completedReason",
				Log::INFO,
				'com_radicalmart_telegram'
			);
			// Поля sweep
			$sweepPhase = ($provider === 'x5') ? (string) ($session->get($sweepPhaseKey, '') ?: '') : '';
			$sweepOffsetCurrent = ($sweepPhase === 'offset') ? (int) ($session->get($sweepOffsetKey, 0) ?: 0) : 0;
			// Dynamic total adjust based on DB distinct if anomaly/repeats detected
			$adjustedTotal = null; $dbDistinct = null; $ratioDistinct = null;
			try {
				if ($provider === 'x5' && $repeatCount >= 3 && $providerTotal > 0) {
					$dbDistinct = (int) $db->setQuery(
						'DELETE FROM ' . $db->quoteName('#__radicalmart_apiship_points') . ' WHERE 1=2' // placeholder to ensure $db var used
					)->execute();
				}
			} catch (\Throwable $e) { /* noop */ }
			// Proper distinct fetch (separate try to avoid mixing previous placeholder)
			try {
				if ($provider === 'x5' && $repeatCount >= 3 && $providerTotal > 0) {
					$qDistinct = 'SELECT COUNT(DISTINCT ' . $db->quoteName('ext_id') . ') FROM ' . $db->quoteName('#__radicalmart_apiship_points') .
						' WHERE ' . $db->quoteName('provider') . ' = ' . $db->quote($provider);
					$dbDistinct = (int) $db->setQuery($qDistinct)->loadResult();
					$ratioDistinct = ($providerTotal > 0) ? ($dbDistinct / $providerTotal) : null;
					if ($ratioDistinct !== null && $ratioDistinct < 0.2) {
						$adjustedTotal = $providerTotal; // keep providerTotal, but adjust remaining from dbDistinct
						$remainingFromDistinct = max(0, $providerTotal - $dbDistinct);
						if ($remainingCalc === null) { $remainingCalc = $remainingFromDistinct; }
						else { $remainingCalc = min((int)$remainingCalc, $remainingFromDistinct); }
						$debug[] = 'Adjusted remaining via DB distinct: distinct=' . $dbDistinct . ' of total=' . $providerTotal . ' ratio=' . $ratioDistinct;
					}
				}
			} catch (\Throwable $e) { $debug[] = 'Distinct adjust failed: ' . $e->getMessage(); }

			return [
				'success' => true,
				'provider' => $provider,
				'offset' => $offset,
				'fetched' => 0,
				'inserted' => 0,
				'completed' => $completedLocal,
				'mode' => $mode,
				'remaining' => $remainingCalc,
				'completedReason' => $completedReason,
				'anomaly' => $anomaly ?? false,
				'anomalyReason' => $anomalyReason ?? '',
				'dir' => $useCursor ? ($session->get($dirKey, '') ?: '') : '',
				'cursorLastId' => $useCursor ? (int) ($session->get($cursorKey, 0) ?: 0) : 0,
				'sweepPhase' => $sweepPhase,
				'sweepOffsetCurrent' => $sweepOffsetCurrent,
				'sweepRepeatCount' => (int) ($session->get($repeatCountKey, 0) ?: 0),
				'pageRepeatChain' => (int) ($session->get('rm_apiship_page_repeat_chain_' . $provider, 0) ?: 0),
				'ascProbeAttempted' => ((int) ($session->get($ascProbeKey, 0) ?: 0)) === 1,
				'ascProbeResult' => '',
				'adjustedTotal' => $adjustedTotal,
				'ratioDistinct' => $ratioDistinct,
				'dbDistinct' => $dbDistinct,
				'debug' => $debug
			];
		}

		// Batch INSERT (нативно под Joomla 5 + устойчивый парс координат)
		$values = [];
		$extIdsForCheck = [];
		$now = (new \Joomla\CMS\Date\Date())->toSql();
		$sampleDumped = false;
		$skippedEmptyExtId = 0;
		$skippedNoCoords = 0;
		$x5FirstRowsSummary = [];

		foreach ($chunk as $row) {
			if (is_object($row)) {
				$row = get_object_vars($row);
			}

			// Дампим ПОЛНУЮ структуру первой записи для диагностики
			if (!$sampleDumped) {
				$debug[] = '=== FIRST ROW DUMP ===';
				$debug[] = 'Keys: ' . implode(', ', array_keys($row));
				if (isset($row['location'])) {
					$loc = $row['location'];
					if (is_object($loc)) {
						$debug[] = 'location (object) keys: ' . implode(', ', array_keys(get_object_vars($loc)));
					} elseif (is_array($loc)) {
						$debug[] = 'location (array) keys: ' . implode(', ', array_keys($loc));
					} else {
						$debug[] = 'location type: ' . gettype($loc);
					}
				} else {
					$debug[] = 'location: NOT SET';
				}
				$debug[] = 'id=' . var_export($row['id'] ?? null, true);
				$debug[] = 'latitude=' . var_export($row['latitude'] ?? null, true);
				$debug[] = 'longitude=' . var_export($row['longitude'] ?? null, true);
				$debug[] = '=== END DUMP ===';
				$sampleDumped = true;
			}

			$extId   = (string) ($row['id'] ?? ($row['externalId'] ?? ($row['code'] ?? '')));
			$title   = (string) ($row['title'] ?? ($row['name'] ?? ''));
			$address = (string) ($row['address'] ?? '');

			// ApiShip использует lat/lng, а не latitude/longitude
			$lat = isset($row['lat']) ? (float) $row['lat'] : (isset($row['latitude']) ? (float) $row['latitude'] : null);
			$lon = isset($row['lng']) ? (float) $row['lng'] : (isset($row['lon']) ? (float) $row['lon'] : (isset($row['longitude']) ? (float) $row['longitude'] : null));

			if (($lat === null || $lon === null) && isset($row['location'])) {
				$loc = $row['location'];
				if (is_object($loc)) {
					$lat = $lat ?? (isset($loc->lat) ? (float) $loc->lat : (isset($loc->latitude) ? (float) $loc->latitude : null));
					$lon = $lon ?? (isset($loc->lng) ? (float) $loc->lng : (isset($loc->lon) ? (float) $loc->lon : (isset($loc->longitude) ? (float) $loc->longitude : null)));
				} elseif (is_array($loc)) {
					$lat = $lat ?? (isset($loc['lat']) ? (float) $loc['lat'] : (isset($loc['latitude']) ? (float) $loc['latitude'] : null));
					$lon = $lon ?? (isset($loc['lng']) ? (float) $loc['lng'] : (isset($loc['lon']) ? (float) $loc['lon'] : (isset($loc['longitude']) ? (float) $loc['longitude'] : null)));
				}
			}

			$skipReason = '';
			if ($extId === '') { $skippedEmptyExtId++; $skipReason = 'empty-extId'; }
			if ($lat === null || $lon === null) { $skippedNoCoords++; $skipReason = ($skipReason ? $skipReason . '+' : '') . 'no-coords'; }
			// Сбор мини-дампа первых 5 строк для x5 sweep
			if ($provider === 'x5' && count($x5FirstRowsSummary) < 5) {
				$x5FirstRowsSummary[] = 'row' . count($x5FirstRowsSummary) . ': id=' . $extId . ' lat=' . var_export($lat, true) . ' lon=' . var_export($lon, true) . ($skipReason ? ' skip=' . $skipReason : '');
			}
			if ($skipReason !== '') { continue; }

			$meta = json_encode($row, JSON_UNESCAPED_UNICODE);

			$values[] =
				$db->quote($provider) . ","
				. $db->quote($extId) . ","
				. $db->quote($title) . ","
				. $db->quote($address) . ","
				. (string) $lat . ","
				. (string) $lon . ","
				. $db->quote('giveout') . ","
				. $db->quote((string)($row['type'] ?? '')) . ","
				. "POINT(" . (string) $lon . "," . (string) $lat . "),"
				. $db->quote($meta) . ","
				. $db->quote($now);

			$extIdsForCheck[] = $extId;
		}
		if ($provider === 'x5' && $x5FirstRowsSummary) {
			$debug[] = 'x5 first rows summary: ' . implode(' | ', $x5FirstRowsSummary);
		}

		$inserted = 0;
		$updated = 0;
		$debug[] = 'Prepared ' . count($values) . ' values for INSERT';
		Log::add(
			"[PVZ] Prepare insert provider=$provider values=" . count($values) .
			" skippedEmptyExtId=$skippedEmptyExtId skippedNoCoords=$skippedNoCoords",
			Log::INFO,
			'com_radicalmart_telegram'
		);
		if ($skippedEmptyExtId > 0 || $skippedNoCoords > 0) {
			$debug[] = 'Skipped: empty extId=' . $skippedEmptyExtId . ', no coords=' . $skippedNoCoords;
		}

		// Проверка наличия уникального индекса (provider, ext_id)
		try {
			$tableName = $db->getPrefix() . 'radicalmart_apiship_points';
			$idxSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "
				. $db->quote($tableName) . " AND INDEX_NAME = 'uniq_provider_ext'";
			$hasUnique = (int) $db->setQuery($idxSql)->loadResult();
			if (!$hasUnique) {
				$debug[] = 'WARNING: Unique index uniq_provider_ext missing on table ' . $tableName . ' (provider, ext_id).';
			}
		} catch (\Throwable $e) {
			$debug[] = 'Index check failed: ' . $e->getMessage();
		}

		if (!empty($values)) {
			$debug[] = 'Building INSERT query (Query Builder)...';
			$columns = [
				$db->quoteName('provider'),
				$db->quoteName('ext_id'),
				$db->quoteName('title'),
				$db->quoteName('address'),
				$db->quoteName('lat'),
				$db->quoteName('lon'),
				$db->quoteName('operation'),
				$db->quoteName('pvz_type'),
				$db->quoteName('point'),
				$db->quoteName('meta'),
				$db->quoteName('updated_at'),
			];

			$query = $db->getQuery(true)
				->insert($db->quoteName('#__radicalmart_apiship_points'))
				->columns($columns);

			foreach ($values as $v) {
				$query->values($v);
			}

			$onDup = ' ON DUPLICATE KEY UPDATE '
				. $db->quoteName('title') . ' = VALUES(' . $db->quoteName('title') . '), '
				. $db->quoteName('address') . ' = VALUES(' . $db->quoteName('address') . '), '
				. $db->quoteName('lat') . ' = VALUES(' . $db->quoteName('lat') . '), '
				. $db->quoteName('lon') . ' = VALUES(' . $db->quoteName('lon') . '), '
				. $db->quoteName('pvz_type') . ' = VALUES(' . $db->quoteName('pvz_type') . '), '
				. $db->quoteName('point') . ' = VALUES(' . $db->quoteName('point') . '), '
				. $db->quoteName('meta') . ' = VALUES(' . $db->quoteName('meta') . '), '
				. $db->quoteName('updated_at') . ' = VALUES(' . $db->quoteName('updated_at') . ')';

			$sql = (string) $query . $onDup;
			$debug[] = 'SQL built, length: ' . strlen($sql);
			Log::add('[PVZ] SQL built length=' . strlen($sql) . ' rows=' . count($values), Log::INFO, 'com_radicalmart_telegram');

			// Предварительно оценим, сколько записей уже существует (для статистики updated)
			try {
				if (!empty($extIdsForCheck)) {
					$inList = implode(',', array_map([$db, 'quote'], $extIdsForCheck));
					$existsSql = 'SELECT ' . $db->quoteName('ext_id')
						. ' FROM ' . $db->quoteName('#__radicalmart_apiship_points')
						. ' WHERE ' . $db->quoteName('provider') . ' = ' . $db->quote($provider)
						. ' AND ' . $db->quoteName('ext_id') . ' IN (' . $inList . ')';
					$existing = (array) $db->setQuery($existsSql)->loadColumn();
					$updated = count($existing);
				}
			} catch (\Throwable $e) {
				$debug[] = 'Existing check failed: ' . $e->getMessage();
			}

			try {
				$debug[] = 'Executing INSERT in transaction...';
				$db->transactionStart();
				$db->setQuery($sql)->execute();
				$db->transactionCommit();
				$inserted = max(0, count($values) - $updated);
				$debug[] = "INSERT successful, new=$inserted, updated=$updated";
				Log::add("[PVZ] Insert OK provider=$provider new=$inserted updated=$updated", Log::INFO, 'com_radicalmart_telegram');
			} catch (\Exception $e) {
				$debug[] = 'INSERT FAILED: ' . $e->getMessage();
				Log::add('[PVZ] Insert FAILED provider=' . $provider . ' error=' . $e->getMessage(), Log::ERROR, 'com_radicalmart_telegram');
				// Safe rollback without relying on transactionDepth (not available in mysqli driver)
				try {
					$db->transactionRollback();
				} catch (\Throwable $er) {
					$debug[] = 'Rollback error: ' . $er->getMessage();
				}

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
		} else {
			$debug[] = 'No values to insert (all rows skipped)';
		}

		$debug[] = 'Returning result';
		Log::add(
			"[PVZ] Step end provider=$provider mode=$mode fetched=" . count($chunk) .
			" inserted=$inserted updated=$updated completed=" . ($completedLocal?'1':'0') .
			" reason=$completedReason remaining=" . ($remainingCalc ?? -1),
			Log::INFO,
			'com_radicalmart_telegram'
		);
		$hash = ($extIdsForCheck) ? md5(implode('|', $extIdsForCheck)) : '';
		// Расчёт remaining и completed на основе providerTotal (для offset) и meta (для cursor)
		$nextOffset = $offset + $batchSize;
		$debug[] = 'nextOffset (offset + batchSize) = ' . $nextOffset;
		if (isset($providerTotal) && $providerTotal > 0) {
			$remainingCalc = max(0, $providerTotal - ($offset + count($chunk)));
			$debug[] = 'Remaining recalculated (non-empty chunk): ' . $remainingCalc . ' (providerTotal=' . $providerTotal . ')';
		} else {
			$remainingCalc = $remaining;
		}
		$completedLocal = false;
		if ($useCursor) {
			// Cursor режим: завершаем когда meta.total==0 или chunk < batchSize (признак конца выборки)
			if ($remaining !== null && (int)$remaining === 0) {
				$completedLocal = true; $completedReason = 'cursor-meta-zero';
			} elseif (count($chunk) < $batchSize) {
				$completedLocal = true; $completedReason = 'cursor-short-chunk';
			}
		} else {
			if (isset($providerTotal) && $providerTotal > 0) {
				$completedLocal = ($offset + count($chunk) >= $providerTotal);
				if ($completedLocal) { $completedReason = 'offset-total-reached'; }
			} else {
				$completedLocal = (count($chunk) < $batchSize); // fallback
				if ($completedLocal) { $completedReason = 'offset-short-fallback'; }
			}
		}
		// Если мы в sweep-фазе для x5 — корректируем completed логику и remaining
		$sweepPhase = ($provider === 'x5') ? (string) ($session->get($sweepPhaseKey, '') ?: '') : '';
		$sweepOffsetCurrent = 0;
		if ($provider === 'x5' && $sweepPhase === 'offset') {
			// Текущий накопительный прогресс sweep (копим фактическое число обработанных строк)
			$sweepOffsetCurrent = (int) ($session->get($sweepOffsetKey, 0) ?: 0);
			$advancedBy = (int) count($chunk);
			if ($advancedBy > 0) {
				$sweepOffsetCurrent += $advancedBy; // накапливаем, а не прыгаем сразу к offset+advancedBy
				$session->set($sweepOffsetKey, $sweepOffsetCurrent);
				$debug[] = 'Sweep offset advanced by +' . $advancedBy . ' => ' . $sweepOffsetCurrent;
			} else {
				$debug[] = 'Sweep offset unchanged (empty chunk) current=' . $sweepOffsetCurrent;
			}
			// Пересчёт remaining относительно providerTotal и sweepOffsetCurrent
			if (isset($providerTotal) && $providerTotal > 0) {
				$remainingCalc = max(0, $providerTotal - $sweepOffsetCurrent);
				$debug[] = 'Remaining recalculated (sweep): ' . $remainingCalc;
			}
			// Завершение sweep: достигли или превысили providerTotal (учитываем накопление)
			if (isset($providerTotal) && $providerTotal > 0 && $sweepOffsetCurrent >= $providerTotal) {
				$completedLocal = true; $completedReason = 'x5-sweep-complete';
				$debug[] = 'Sweep complete: accumulated sweepOffsetCurrent >= providerTotal';
			}
			// Контроль пустых чанков подряд
			if (count($chunk) === 0) {
				$emptyCnt = (int) ($session->get($sweepEmptyCountKey, 0) ?: 0) + 1;
				$session->set($sweepEmptyCountKey, $emptyCnt);
				$debug[] = 'Sweep empty chunk count=' . $emptyCnt;
				if ($emptyCnt >= 10) {
					$completedLocal = true; $completedReason = 'x5-sweep-exhausted';
					$debug[] = 'Sweep exhausted: too many consecutive empty chunks';
					Log::add('[PVZ] x5 sweep exhausted (10 empty chunks)', Log::INFO, 'com_radicalmart_telegram');
				}
			} else {
				$session->set($sweepEmptyCountKey, 0);
			}
		}
		if (!$completedLocal && count($chunk) < $batchSize && isset($providerTotal) && $providerTotal > ($offset + count($chunk))) {
			$debug[] = 'ANOMALY: short chunk (<batchSize) but remaining > 0 -> will continue';
		}

		// Доп. защита: для x5 в sweep-фазе не считаем шаг завершённым, если накопленный прогресс меньше total
		if ($provider === 'x5') {
			$sweepPhase = (string) ($session->get($sweepPhaseKey, '') ?: '');
			$sweepOffsetCurrent = ($sweepPhase === 'offset') ? (int) ($session->get($sweepOffsetKey, 0) ?: 0) : 0;
			if ($sweepPhase === 'offset' && $completedLocal && isset($providerTotal) && $providerTotal > 0 && $sweepOffsetCurrent < $providerTotal) {
				$debug[] = 'x5 safeguard: preventing premature completion in sweep (progress=' . $sweepOffsetCurrent . ' < total=' . $providerTotal . ')';
				$completedLocal = false; $completedReason = '';
			}
		}
		// Дополнительная аномалия: достигли completed по логике, но в БД может быть сильный недобор — UI сможет сверить через dbCheck
		$ascProbeAttempted = ((int) ($session->get($ascProbeKey, 0) ?: 0)) === 1;
		$ascProbeResult = '';
		if ($ascProbeAttempted && $useCursor) {
			if (count($chunk) > 0) { $ascProbeResult = 'ok'; }
			else {
				$curDirNow = (string) ($session->get($dirKey, '') ?: '');
				$ascProbeResult = ($curDirNow === 'desc') ? 'flip-to-desc' : 'empty';
			}
		}
		return [
			'success' => true,
			'provider' => $provider,
			'offset' => $offset,
			'fetched' => count($chunk),
			'inserted' => $inserted,
			'updated' => $updated,
			'completed' => $completedLocal,
			'hash' => $hash,
			'mode' => $mode,
			'remaining' => $remainingCalc,
			'completedReason' => $completedReason,
			'anomaly' => $anomaly ?? false,
			'anomalyReason' => $anomalyReason ?? '',
			'dir' => $useCursor ? ($session->get($dirKey, '') ?: '') : '',
			'cursorLastId' => $useCursor ? (int) ($session->get($cursorKey, 0) ?: 0) : 0,
			'sweepPhase' => $sweepPhase,
			'sweepOffsetCurrent' => $sweepOffsetCurrent,
			'sweepRepeatCount' => (int) ($session->get($repeatCountKey, 0) ?: 0),
			'pageRepeatChain' => (int) ($session->get('rm_apiship_page_repeat_chain_' . $provider, 0) ?: 0),
			'ascProbeAttempted' => $ascProbeAttempted,
			'ascProbeResult' => $ascProbeResult,
			'distinctInChunk' => $distinctInChunk ?? 0,
			'chunkIdsHash' => substr($currentHash ?? '', 0, 8),
			'debug' => $debug
		];
	}

	/**
	 * Диагностика базы точек ПВЗ: счётчики по провайдерам, дубли по ext_id, наличие индекса, пустые координаты
	 *
	 * @param string|null $provider Если задан, фильтруем по провайдеру
	 * @return array
	 */
	public static function getDbStats(?string $provider = null): array
	{
		$debug = [];
		$debug[] = 'getDbStats called: provider=' . ($provider ?? 'ALL');
		$db = Factory::getContainer()->get('DatabaseDriver');
		$table = $db->quoteName('#__radicalmart_apiship_points');

		$stats = [
			'success' => true,
			'hasUniqueIndex' => null,
			'providers' => [],
			'debug' => &$debug,
		];

		// Проверка уникального индекса
		try {
			$tableName = $db->getPrefix() . 'radicalmart_apiship_points';
			$idxSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "
				. $db->quote($tableName) . " AND INDEX_NAME = 'uniq_provider_ext'";
			$hasUnique = (int) $db->setQuery($idxSql)->loadResult();
			$stats['hasUniqueIndex'] = (bool) $hasUnique;
			if (!$hasUnique) { $debug[] = 'WARN: Unique index uniq_provider_ext is missing'; }
		} catch (\Throwable $e) {
			$debug[] = 'Index check failed: ' . $e->getMessage();
			$stats['hasUniqueIndex'] = null;
		}

		// Базовые счётчики по провайдерам
		$where = '';
		if ($provider !== null && $provider !== '') {
			$where = ' WHERE ' . $db->quoteName('provider') . ' = ' . $db->quote($provider);
		}
		try {
			$totalSql = 'SELECT ' . $db->quoteName('provider') . ', COUNT(*) AS cnt, '
				. 'COUNT(DISTINCT ' . $db->quoteName('ext_id') . ') AS distinct_ext, '
				. 'SUM(CASE WHEN ' . $db->quoteName('lat') . ' IS NULL OR ' . $db->quoteName('lon') . ' IS NULL THEN 1 ELSE 0 END) AS null_coords, '
				. 'MAX(' . $db->quoteName('updated_at') . ') AS last_updated '
				. 'FROM ' . $table . $where . ' GROUP BY ' . $db->quoteName('provider');
			$rows = (array) $db->setQuery($totalSql)->loadAssocList();
			foreach ($rows as $r) {
				$prov = (string) $r['provider'];
				$stats['providers'][$prov] = [
					'total' => (int) $r['cnt'],
					'distinctExt' => (int) $r['distinct_ext'],
					'nullCoords' => (int) $r['null_coords'],
					'lastUpdated' => (string) $r['last_updated'],
					'duplicatesByExt' => [],
				];
			}
			if (empty($rows)) {
				$debug[] = 'No rows found for stats';
			}
		} catch (\Throwable $e) {
			$debug[] = 'Totals query failed: ' . $e->getMessage();
		}

		// Дубли по ext_id (по провайдеру)
		try {
			$dupSql = 'SELECT ' . $db->quoteName('provider') . ', ' . $db->quoteName('ext_id') . ', COUNT(*) AS c '
				. 'FROM ' . $table . $where . ' GROUP BY ' . $db->quoteName('provider') . ', ' . $db->quoteName('ext_id') . ' HAVING c > 1 '
				. 'ORDER BY c DESC LIMIT 200';
			$dups = (array) $db->setQuery($dupSql)->loadAssocList();
			foreach ($dups as $d) {
				$prov = (string) $d['provider'];
				if (!isset($stats['providers'][$prov])) {
					$stats['providers'][$prov] = [
						'total' => 0,
						'distinctExt' => 0,
						'nullCoords' => 0,
						'lastUpdated' => '',
						'duplicatesByExt' => [],
					];
				}
				$stats['providers'][$prov]['duplicatesByExt'][] = [
					'ext_id' => (string) $d['ext_id'],
					'count' => (int) $d['c'],
				];
			}
			$debug[] = 'Dup check done, found groups: ' . count($dups);
		} catch (\Throwable $e) {
			$debug[] = 'Dup query failed: ' . $e->getMessage();
		}

		return $stats;
	}

	/**
	 * Импорт точек ПВЗ из локального файла (NDJSON или JSON с полем rows)
	 * - Поддерживает крупные файлы за счёт потоковой обработки NDJSON
	 * - Выполняет батчевую вставку с ON DUPLICATE KEY UPDATE
	 *
	 * @param string $provider   Код провайдера, например 'x5'
	 * @param string $filePath   Путь к файлу. NDJSON: по строке-объекту; JSON: { rows: [...] }
	 * @param int    $batchSize  Размер батча вставки
	 * @return array { success, provider, file, processed, inserted, updated, skippedEmptyExtId, skippedNoCoords, message, debug[] }
	 */
	public static function importFromFile(string $provider, string $filePath, int $batchSize = 1000): array
	{
		self::initLogger();
		$debug = [];
		$debug[] = 'importFromFile called provider=' . $provider . ' file=' . $filePath . ' batchSize=' . $batchSize;
		if (!is_file($filePath)) {
			return ['success' => false, 'provider' => $provider, 'file' => basename($filePath), 'processed' => 0, 'inserted' => 0, 'updated' => 0, 'message' => 'File not found', 'debug' => $debug];
		}

		// Добавляем абсолютный путь в debug для диагностики
		$debug[] = 'Absolute file path: ' . realpath($filePath);
		$debug[] = 'File size: ' . filesize($filePath) . ' bytes';

		$db = Factory::getContainer()->get('DatabaseDriver');
		$totalProcessed = 0; $totalInserted = 0; $totalUpdated = 0; $skippedEmptyExtId = 0; $skippedNoCoords = 0; $skippedFileDuplicates = 0;
		// Ассоциативный массив батча: ext_id => SQL values (уникализируем внутри батча)
		$valuesAssoc = []; $extIdsForCheck = [];
		$now = (new \Joomla\CMS\Date\Date())->toSql();
		// Буфер неиспользуемых значений (зарезервировано для будущих оптимизаций)
		$extIdBuf = [];

		$flush = function() use (&$valuesAssoc, &$extIdsForCheck, &$totalInserted, &$totalUpdated, $db, $provider, &$debug) {
			if (empty($valuesAssoc)) { return; }
			try {
				// Предварительно посчитаем updated по существующим ext_id
				$updatedLocal = 0;
				$uniqueExt = array_values(array_unique($extIdsForCheck));
				if (!empty($uniqueExt)) {
					$inList = implode(',', array_map([$db, 'quote'], $uniqueExt));
					$existsSql = 'SELECT ' . $db->quoteName('ext_id') . ' FROM ' . $db->quoteName('#__radicalmart_apiship_points')
						. ' WHERE ' . $db->quoteName('provider') . ' = ' . $db->quote($provider)
						. ' AND ' . $db->quoteName('ext_id') . ' IN (' . $inList . ')';
					$existing = (array) $db->setQuery($existsSql)->loadColumn();
					$updatedLocal = count($existing);
				}

				$columns = [
					$db->quoteName('provider'),
					$db->quoteName('ext_id'),
					$db->quoteName('title'),
					$db->quoteName('address'),
					$db->quoteName('lat'),
					$db->quoteName('lon'),
					$db->quoteName('operation'),
					$db->quoteName('pvz_type'),
					$db->quoteName('point'),
					$db->quoteName('meta'),
					$db->quoteName('updated_at'),
				];
				$query = $db->getQuery(true)->insert($db->quoteName('#__radicalmart_apiship_points'))->columns($columns);
				foreach ($valuesAssoc as $v) { $query->values($v); }
				$onDup = ' ON DUPLICATE KEY UPDATE '
					. $db->quoteName('title') . ' = VALUES(' . $db->quoteName('title') . '), '
					. $db->quoteName('address') . ' = VALUES(' . $db->quoteName('address') . '), '
					. $db->quoteName('lat') . ' = VALUES(' . $db->quoteName('lat') . '), '
					. $db->quoteName('lon') . ' = VALUES(' . $db->quoteName('lon') . '), '
					. $db->quoteName('pvz_type') . ' = VALUES(' . $db->quoteName('pvz_type') . '), '
					. $db->quoteName('point') . ' = VALUES(' . $db->quoteName('point') . '), '
					. $db->quoteName('meta') . ' = VALUES(' . $db->quoteName('meta') . '), '
					. $db->quoteName('updated_at') . ' = VALUES(' . $db->quoteName('updated_at') . ')';
				$sql = (string) $query . $onDup;
				$db->transactionStart();
				$db->setQuery($sql)->execute();
				$db->transactionCommit();
				$totalUpdated += $updatedLocal;
				$uniqueCount = count($valuesAssoc);
				$totalInserted += max(0, $uniqueCount - $updatedLocal);
				$debug[] = 'Flushed batch: uniqueRows=' . $uniqueCount . ' updated=' . $updatedLocal . ' inserted=' . max(0, $uniqueCount - $updatedLocal);
			} catch (\Throwable $e) {
				try { $db->transactionRollback(); } catch (\Throwable $er) { /* ignore */ }
				$debug[] = 'Flush error: ' . $e->getMessage();
				throw $e;
			} finally {
				$valuesAssoc = []; $extIdsForCheck = [];
			}
		};

		$appendValue = function(array $row) use (&$valuesAssoc, &$extIdsForCheck, &$skippedEmptyExtId, &$skippedNoCoords, &$skippedFileDuplicates, $provider, $now, $db) {
			$extId = (string) ($row['id'] ?? ($row['externalId'] ?? ($row['code'] ?? '')));
			$title = (string) ($row['title'] ?? ($row['name'] ?? ''));
			$address = (string) ($row['address'] ?? '');
			$lat = isset($row['lat']) ? (float)$row['lat'] : (isset($row['latitude']) ? (float)$row['latitude'] : null);
			$lon = isset($row['lng']) ? (float)$row['lng'] : (isset($row['lon']) ? (float)$row['lon'] : (isset($row['longitude']) ? (float)$row['longitude'] : null));
			if (($lat === null || $lon === null) && isset($row['location'])) {
				$loc = $row['location'];
				if (is_object($loc)) { $loc = get_object_vars($loc); }
				if (is_array($loc)) {
					$lat = $lat ?? (isset($loc['lat']) ? (float)$loc['lat'] : (isset($loc['latitude']) ? (float)$loc['latitude'] : null));
					$lon = $lon ?? (isset($loc['lng']) ? (float)$loc['lng'] : (isset($loc['lon']) ? (float)$loc['lon'] : (isset($loc['longitude']) ? (float)$loc['longitude'] : null)));
				}
			}
			if ($extId === '') { $skippedEmptyExtId++; return; }
			if ($lat === null || $lon === null) { $skippedNoCoords++; return; }
			$meta = json_encode($row, JSON_UNESCAPED_UNICODE);
			// Геометрия: используем корректный WKT через ST_GeomFromText('POINT(lon lat)',4326) как в fetchAllPoints
			$val =
				$db->quote($provider) . ',' .
				$db->quote($extId) . ',' .
				$db->quote($title) . ',' .
				$db->quote($address) . ',' .
				(string)$lat . ',' . (string)$lon . ',' .
				$db->quote('giveout') . ',' .
				$db->quote((string)($row['type'] ?? '')) . ',' .
				"ST_GeomFromText('POINT(" . (string)$lon . " " . (string)$lat . ")',4326)," .
				$db->quote($meta) . ',' .
				$db->quote($now);
			if (isset($valuesAssoc[$extId])) { $skippedFileDuplicates++; }
			$valuesAssoc[$extId] = $val;
			$extIdsForCheck[] = $extId; // может содержать дубликаты; при flush будет unique
		};

		$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		$debug[] = 'Detected file extension: ' . $ext;
		try {
			if ($ext === 'ndjson' || $ext === 'jsonl') {
				$debug[] = 'Processing as NDJSON (line-delimited JSON)';
				$fh = fopen($filePath, 'rb');
				if ($fh === false) { throw new \RuntimeException('Cannot open file'); }
				while (!feof($fh)) {
					$line = fgets($fh);
					if ($line === false) { break; }
					$line = trim($line);
					if ($line === '') { continue; }
					$row = json_decode($line, true);
					if (!is_array($row)) { continue; }
					$appendValue($row);
					$totalProcessed++;
					if (count($valuesAssoc) >= $batchSize) { $flush(); }
				}
				fclose($fh);
			} else {
				$debug[] = 'Processing as JSON (expecting {rows:[...]} structure)';
				$json = json_decode(@file_get_contents($filePath), true);
				$rows = is_array($json) ? ($json['rows'] ?? []) : [];
				$debug[] = 'JSON rows array count: ' . count($rows);
				foreach ($rows as $r) {
					if (is_object($r)) { $r = get_object_vars($r); }
					if (!is_array($r)) { continue; }
					$appendValue($r);
					$totalProcessed++;
					if (count($valuesAssoc) >= $batchSize) { $flush(); }
				}
			}
			// Финальный flush
			$flush();
		} catch (\Throwable $e) {
			$debug[] = 'Import error: ' . $e->getMessage();
			return [
				'success' => false,
				'provider' => $provider,
				'file' => basename($filePath),
				'processed' => $totalProcessed,
				'inserted' => $totalInserted,
				'updated' => $totalUpdated,
				'skippedEmptyExtId' => $skippedEmptyExtId,
				'skippedNoCoords' => $skippedNoCoords,
				'skippedFileDuplicates' => $skippedFileDuplicates,
				'error' => $e->getMessage(),
				'debug' => $debug,
			];
		}

		// Обновим мета-таблицу
		try {
			$metaNow = (new \Joomla\CMS\Date\Date())->toSql();
			$metaSql = 'INSERT INTO ' . $db->quoteName('#__radicalmart_apiship_meta') . ' (' . $db->quoteName('provider') . ', ' . $db->quoteName('last_fetch') . ', ' . $db->quoteName('last_total') . ') '
				. ' VALUES (' . $db->quote($provider) . ', ' . $db->quote($metaNow) . ', ' . (string)($totalInserted + $totalUpdated) . ')'
				. ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('last_fetch') . ' = VALUES(' . $db->quoteName('last_fetch') . '), ' . $db->quoteName('last_total') . ' = VALUES(' . $db->quoteName('last_total') . ')';
			$db->setQuery($metaSql)->execute();
		} catch (\Throwable $e) { $debug[] = 'Meta update failed: ' . $e->getMessage(); }

		$message = 'Processed=' . $totalProcessed . ' inserted=' . $totalInserted . ' updated=' . $totalUpdated . ' skippedEmptyExtId=' . $skippedEmptyExtId . ' skippedNoCoords=' . $skippedNoCoords . ' skippedFileDuplicates=' . $skippedFileDuplicates;
		Log::add('[PVZ] Import from file done provider=' . $provider . ' ' . $message, Log::INFO, 'com_radicalmart_telegram');
		return [
			'success' => true,
			'provider' => $provider,
			'file' => basename($filePath),
			'processed' => $totalProcessed,
			'inserted' => $totalInserted,
			'updated' => $totalUpdated,
			'skippedEmptyExtId' => $skippedEmptyExtId,
			'skippedNoCoords' => $skippedNoCoords,
			'skippedFileDuplicates' => $skippedFileDuplicates,
			'message' => $message,
			'debug' => $debug,
		];
	}
}
