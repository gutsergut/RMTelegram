<?php
// phpcs:ignoreFile
/*
 * @package     com_radicalmart_telegram
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;
use Joomla\Component\RadicalMartTelegram\Administrator\Helper\ApiShipFetchHelper;

/**
 * API Controller для административных задач
 *
 * @since 1.0.0
 */
class ApiController extends BaseController
{
	/**
	 * Запуск обновления базы ПВЗ из ApiShip
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0.0
	 */
	public function apishipfetch()
	{
		$app = Factory::getApplication();

		// Инициализируем логирование
		\Joomla\CMS\Log\Log::addLogger(
			[
				'text_file' => 'com_radicalmart_telegram.php',
				'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'
			],
			\Joomla\CMS\Log\Log::ALL,
			['com_radicalmart_telegram']
		);

		\Joomla\CMS\Log\Log::add('ApiController::apishipfetch started', \Joomla\CMS\Log\Log::INFO, 'com_radicalmart_telegram');

		// Проверяем токен
		try {
			$this->checkToken();
			\Joomla\CMS\Log\Log::add('CSRF token check passed', \Joomla\CMS\Log\Log::INFO, 'com_radicalmart_telegram');
		} catch (\Exception $e) {
			\Joomla\CMS\Log\Log::add('CSRF token check failed: ' . $e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_radicalmart_telegram');
			throw $e;
		}

		try {
			\Joomla\CMS\Log\Log::add('Running ApiShip fetch', \Joomla\CMS\Log\Log::INFO, 'com_radicalmart_telegram');

			// Запускаем обновление через helper
			$result = ApiShipFetchHelper::fetchAllPoints();

			\Joomla\CMS\Log\Log::add(
				'Fetch finished: success=' . ($result['success'] ? 'true' : 'false') . ', total=' . $result['total'],
				\Joomla\CMS\Log\Log::INFO,
				'com_radicalmart_telegram'
			);

			if ($result['success']) {
				$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_APISHIP_FETCH_SUCCESS', $result['message']), 'success');
			} else {
				$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_APISHIP_FETCH_ERROR', $result['message']), 'error');
			}
		} catch (\Throwable $e) {
			\Joomla\CMS\Log\Log::add(
				'ApiShipFetch error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
				\Joomla\CMS\Log\Log::ERROR,
				'com_radicalmart_telegram'
			);
			$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_APISHIP_FETCH_ERROR', $e->getMessage()), 'error');
		}

		// Редирект обратно на страницу статуса
		$this->setRedirect(Route::_('index.php?option=com_radicalmart_telegram&view=status', false));
	}

	/**
	 * Пошаговое обновление ПВЗ с прогрессом (AJAX)
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0.0
	 */
	public function apishipfetchStep()
	{
		$debug = [];
		$debug[] = 'apishipfetchStep called';

		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$debug[] = 'Headers set';

		$app = Factory::getApplication();
		$input = $app->input;
		$debug[] = 'Got app and input';

		// Проверяем токен
		try {
			$this->checkToken();
			$debug[] = 'Token OK';
		} catch (\Exception $e) {
			$debug[] = 'Token FAIL: ' . $e->getMessage();
			echo json_encode([
				'success' => false,
				'error' => 'CSRF token validation failed',
				'debug' => $debug
			]);
			jexit();
		}

		// (Вложенное объявление метода apishipfetchJson было ошибочно; метод вынесен наружу)

		// Получаем параметры шага
		$step = $input->getInt('step', 0);
		$provider = $input->getString('provider', '');
		$offset = $input->getInt('offset', 0);
		$batchSize = $input->getInt('batchSize', 500);
		$debug[] = "Params: provider=$provider, offset=$offset, batchSize=$batchSize";

		try {
			$debug[] = 'Calling fetchPointsStep...';
			$result = ApiShipFetchHelper::fetchPointsStep($provider, $offset, $batchSize);
			$debug[] = 'fetchPointsStep returned';
			// Аккуратно объединяем debug из хелпера и локальный
			if (isset($result['debug']) && is_array($result['debug'])) {
				$result['debug'] = array_merge($result['debug'], $debug);
			} else {
				$result['debug'] = $debug;
			}
			// Подсказка, если helper не вернул debug, а fetched>0 и inserted=0
			if (($result['fetched'] ?? 0) > 0 && ($result['inserted'] ?? 0) === 0 && empty($result['debug'])) {
				$result['debug'][] = 'No helper debug present; ensure helper is updated';
			}
			echo json_encode($result);
		} catch (\Throwable $e) {
			$debug[] = 'ERROR: ' . $e->getMessage();
			$debug[] = 'File: ' . $e->getFile() . ':' . $e->getLine();
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
				'step' => $step,
				'provider' => $provider,
				'offset' => $offset,
				'debug' => $debug,
				'trace' => explode("\n", $e->getTraceAsString())
			]);
		}

		jexit();
	}

	/**
	 * Скачать полный JSON для одного провайдера (например x5) и сохранить во временный cache.
	 * Возвращает summary: metaTotal, rowsCount, distinctExtIds, file, firstKeys.
	 * @since 1.0.0
	 */
	public function apishipfetchJson(): void
	{
		$debug = [];
		$debug[] = 'apishipfetchJson called';
		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$app = Factory::getApplication();
		$input = $app->input;

		// CSRF
		try { $this->checkToken(); $debug[] = 'Token OK'; } catch (\Exception $e) {
			echo json_encode(['success'=>false,'error'=>'CSRF token validation failed','debug'=>$debug]);
			jexit();
		}

		$provider = $input->getString('provider', 'x5');
		$limit = 500;
		$params = ComponentHelper::getParams('com_radicalmart_telegram');
		$token = (string) $params->get('apiship_api_key', '');
		$operations = [2,3];
		$success = false;
		$rows = [];
		$metaTotal = 0;
		$error = '';
		$offset = 0;
		$filePath = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/cache/apiship_' . $provider . '.json';

		try {
			if ($token === '') { throw new \RuntimeException('Missing ApiShip token'); }
			try { $metaTotal = ApiShipHelper::getPointsTotal($token, [$provider], $operations); $debug[]='Meta total reported: '.$metaTotal; }
			catch (\Throwable $e){ $debug[]='Meta total error: '.$e->getMessage(); }

			while (true) {
				$chunk = [];
				if (method_exists(ApiShipHelper::class, 'getPointsRegistry')) {
					$registry = ApiShipHelper::getPointsRegistry($token, [$provider], $operations, $offset, $limit);
					$chunk = $registry->get('rows', []);
				} else {
					$chunk = ApiShipHelper::getPoints($token, [$provider], $operations, $offset, $limit);
					$debug[] = 'getPointsRegistry() missing, fallback to getPoints()';
				}
				$count = is_array($chunk) ? count($chunk) : 0;
				$debug[] = 'Page offset=' . $offset . ' got=' . $count;
				if ($count === 0) { $debug[]='Break: empty page'; break; }
				// Хэш для детектора повторяющихся страниц (берём первые до 50 id/код)
				$pageIds = [];
				foreach ($chunk as $r) { if (is_array($r)) { $pageIds[] = (string)($r['id'] ?? ($r['extId'] ?? '')); if (count($pageIds) >= 50) break; } }
				$hash = md5(implode('|',$pageIds));
				if (!isset($pageHashes)) { $pageHashes = []; $repeatChain = 0; }
				if (!empty($pageIds)) {
					$prevHash = end($pageHashes) ?: null;
					$pageHashes[] = $hash;
					if ($hash === $prevHash) { $repeatChain++; } else { $repeatChain = 1; }
					if ($repeatChain >= 3) { $debug[] = 'Duplicate page pattern detected (repeatChain=' . $repeatChain . ', hash=' . $hash . ')'; }
				}
				foreach ($chunk as $row) { $rows[] = $row; }
				$offset += $limit;
				if ($count < $limit) { $debug[]='Break: short page (<limit)'; break; }
				if ($metaTotal > 0 && $offset >= $metaTotal) { $debug[]='Break: reached metaTotal'; break; }
				if (isset($repeatChain) && $repeatChain >= 10) { $debug[]='Safety break: excessive repeating pages (repeatChain=' . $repeatChain . ')'; break; }
				if ($offset > 50000) { $debug[]='Safety break offset>50000'; break; }
			}

			$dir = dirname($filePath);
			if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
				throw new \RuntimeException('Cannot create cache dir: ' . $dir);
			}
			$distinctIds = [];
			$seenPerId = [];
			$dedupRows = []; // Де-дубленный массив (только первое вхождение каждого id)
			foreach ($rows as $r) {
				// API может возвращать объекты, приводим к массиву
				if (is_object($r)) { $r = get_object_vars($r); }
				if (is_array($r)) {
					$curId = null;
					if (isset($r['id'])) { $curId = $r['id']; }
					elseif (isset($r['extId'])) { $curId = $r['extId']; }
					if ($curId !== null) {
						$distinctIds[$curId] = true;
						$seenPerId[$curId] = isset($seenPerId[$curId]) ? ($seenPerId[$curId] + 1) : 1;
						// Сохраняем только первое вхождение
						if ($seenPerId[$curId] === 1) {
							$dedupRows[] = $r;
						}
					}
				}
			}
			$first = $rows[0] ?? [];
			$firstKeys = is_array($first) ? array_keys($first) : [];
			$distinctCount = count($distinctIds);
			$rowsCount = count($rows);
			$distinctRatio = ($rowsCount > 0) ? ($distinctCount / $rowsCount) : 0;
			if ($distinctRatio < 0.05 && $rowsCount >= 2000) {
				$debug[] = 'ANOMALY: very low distinct ratio (' . $distinctRatio . ') rows=' . $rowsCount . ' distinct=' . $distinctCount;
			}
			$topRepeat = [];
			arsort($seenPerId);
			$topRepeat = array_slice($seenPerId, 0, 5, true);
			$payload = [
				'provider' => $provider,
				'meta_total' => $metaTotal,
				'rows_count' => $rowsCount,
				'distinct_ids' => $distinctCount,
				'distinct_ratio' => $distinctRatio,
				'top_repeat_ids' => $topRepeat,
				'first_keys' => $firstKeys,
				'fetched_at' => gmdate('c'),
				'rows' => $dedupRows, // Сохраняем де-дубленный массив
				'page_repeat_chain' => isset($repeatChain) ? $repeatChain : 0,
			];
			$debug[] = 'De-duplicated rows: ' . count($dedupRows) . ' (from ' . $rowsCount . ' total)';
			file_put_contents($filePath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$success = true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			$debug[] = 'ERROR: ' . $error;
		}

		$response = [
			'success' => $success,
			'provider' => $provider,
			'metaTotal' => $metaTotal,
			'rowsCount' => count($rows),
			'distinctExtIds' => isset($payload['distinct_ids']) ? $payload['distinct_ids'] : 0,
			'file' => $success ? basename($filePath) : null,
			'debug' => $debug,
			'error' => $error,
		];
		echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		jexit();
	}

	/**
	 * Получить информацию о провайдерах для обновления (AJAX)
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.0.0
	 */
	public function apishipfetchInit()
	{
		$debug = [];
		$debug[] = 'apishipfetchInit called';

		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$debug[] = 'Headers set';

		$app = Factory::getApplication();
		$debug[] = 'Got app';

		// Проверяем токен
		try {
			$this->checkToken();
			$debug[] = 'Token OK';
		} catch (\Exception $e) {
			$debug[] = 'Token FAIL: ' . $e->getMessage();
			echo json_encode([
				'success' => false,
				'error' => 'CSRF token validation failed',
				'debug' => $debug
			]);
			jexit();
		}

		try {
			$debug[] = 'Calling getProvidersInfo...';
			$result = ApiShipFetchHelper::getProvidersInfo();
			$debug[] = 'getProvidersInfo returned';
			// Если есть префетч JSON для x5 — используем его rows_count как total
			$cacheFile = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/cache/apiship_x5.json';
			if (is_file($cacheFile)) {
				$json = json_decode(@file_get_contents($cacheFile), true);
				if (is_array($json) && !empty($json['rows_count'])) {
					foreach ($result['providers'] as &$p) {
						if (($p['code'] ?? '') === 'x5') {
							$p['total'] = (int) $json['rows_count'];
							$p['source'] = 'json';
							$debug[] = 'x5 total overridden from JSON: ' . $p['total'];
							break;
						}
					}
					unset($p);
				}
			}
			$result['debug'] = $debug;
			echo json_encode($result);
		} catch (\Throwable $e) {
			$debug[] = 'ERROR: ' . $e->getMessage();
			$debug[] = 'File: ' . $e->getFile() . ':' . $e->getLine();
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
				'debug' => $debug,
				'trace' => explode("\n", $e->getTraceAsString())
			]);
		}

		jexit();
	}

	/**
	 * Диагностика базы точек ПВЗ (дубли, счётчики)
	 * @return void
	 */
	public function apishipdbCheck()
	{
		$debug = [];
		$debug[] = 'apishipdbCheck called';
		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');

		$app = Factory::getApplication();
		try {
			$this->checkToken();
			$debug[] = 'Token OK';
		} catch (\Exception $e) {
			$debug[] = 'Token FAIL: ' . $e->getMessage();
			echo json_encode(['success' => false, 'error' => 'CSRF token validation failed', 'debug' => $debug]);
			jexit();
		}

		$provider = $app->input->getString('provider', null);
		try {
			$result = ApiShipFetchHelper::getDbStats($provider ?: null);
			$result['debug'][] = 'apishipdbCheck finished';
			echo json_encode($result);
		} catch (\Throwable $e) {
			$debug[] = 'ERROR: ' . $e->getMessage();
			$debug[] = 'FILE: ' . $e->getFile() . ':' . $e->getLine();
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
				'debug' => $debug,
				'trace' => explode("\n", $e->getTraceAsString())
			]);
		}
		jexit();
	}

	/**
	 * Анализ ранее сохранённого JSON (cache) для провайдера.
	 * Возвращает агрегаты: rowsCount, distinctIds, coordsOk, id stats, firstKeys.
	 */
	public function apishipjsonAnalyze(): void
	{
		$debug = [];
		$debug[] = 'apishipjsonAnalyze called';
		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$app = Factory::getApplication();
		try { $this->checkToken(); $debug[]='Token OK'; } catch (\Exception $e) { echo json_encode(['success'=>false,'error'=>'CSRF token validation failed','debug'=>$debug]); jexit(); }
		$provider = $app->input->getString('provider', 'x5');
		$filePath = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/cache/apiship_' . $provider . '.json';
		if (!is_file($filePath)) { echo json_encode(['success'=>false,'error'=>'JSON cache not found: '.basename($filePath),'debug'=>$debug]); jexit(); }
		$data = json_decode(@file_get_contents($filePath), true);
		if (!is_array($data)) { echo json_encode(['success'=>false,'error'=>'Invalid JSON structure','debug'=>$debug]); jexit(); }
		$rows = $data['rows'] ?? [];
		$distinct = [];
		$coordsOk = 0;
		$numericIds = true; $minId = PHP_INT_MAX; $maxId = 0;
		foreach ($rows as $r) {
			// API может возвращать объекты, приводим к массиву
			if (is_object($r)) { $r = get_object_vars($r); }
			if (!is_array($r)) { continue; }
			$id = $r['id'] ?? ($r['extId'] ?? ($r['code'] ?? ($r['externalId'] ?? null)));
			if ($id !== null) {
				$distinct[(string)$id] = true;
				if (ctype_digit((string)$id)) { $v=(int)$id; if ($v<$minId) $minId=$v; if ($v>$maxId) $maxId=$v; }
				else { $numericIds = false; }
			}
			$lat = $r['lat'] ?? ($r['latitude'] ?? ($r['location']['lat'] ?? ($r['location']['latitude'] ?? null)));
			$lon = $r['lng'] ?? ($r['lon'] ?? ($r['longitude'] ?? ($r['location']['lng'] ?? ($r['location']['lon'] ?? ($r['location']['longitude'] ?? null)))));
			if ($lat !== null && $lon !== null) $coordsOk++;
		}
		$first = $rows[0] ?? [];
		$firstKeys = is_array($first) ? array_keys($first) : [];
		$response = [
			'success' => true,
			'provider' => $provider,
			'file' => basename($filePath),
			'rowsCount' => (int) ($data['rows_count'] ?? count($rows)),
			'distinctIds' => count($distinct),
			'duplicates' => ((int) ($data['rows_count'] ?? count($rows))) - count($distinct),
			'coordsWithLatLon' => $coordsOk,
			'idNumericAll' => $numericIds,
			'idMin' => ($minId === PHP_INT_MAX ? null : $minId),
			'idMax' => ($maxId === 0 ? null : $maxId),
			'metaTotal' => (int) ($data['meta_total'] ?? 0),
			'firstKeys' => $firstKeys,
			'debug' => $debug,
		];
		echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		jexit();
	}

	/**
	 * Импортировать ПВЗ из локального файла (NDJSON/JSON) на сервере.
	 * Параметры: provider, file (опционально). По умолчанию для x5 ищет
	 * administrator/components/com_radicalmart_telegram/cache/apiship_x5.ndjson
	 */
	public function apishipimportFile(): void
	{
		$debug = [];
		$debug[] = 'apishipimportFile called';
		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$app = Factory::getApplication();
		try { $this->checkToken(); $debug[]='Token OK'; } catch (\Exception $e) { echo json_encode(['success'=>false,'error'=>'CSRF token validation failed','debug'=>$debug]); jexit(); }
		$provider = $app->input->getString('provider', 'x5');
		$file = $app->input->getString('file', '');
		$attempted = [];
		$cacheDir = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/cache';
		if ($file !== '') {
			// Если пользователь передал только имя файла без пути – достроим путь до cache
			if (strpos($file, '/') === false && strpos($file, '\\') === false) {
				$possible = $cacheDir . '/' . ltrim($file, '/');
				$attempted[] = $possible;
				if (is_file($possible)) { $file = $possible; }
			}
			$attempted[] = $file;
		}
		if ($file === '') {
			// По умолчанию пытаемся NDJSON, затем JSON (NDJSON приоритетнее)
			$base = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/cache/apiship_' . $provider;
			$try = [$base . '.ndjson', $base . '.json'];
			foreach ($try as $p) { $attempted[] = $p; if (is_file($p)) { $file = $p; $debug[] = 'Auto-selected file: ' . basename($p); break; } }
		}
		if ($file === '' || !is_file($file)) {
			$debug[] = 'Import file not found. Attempted: ' . implode(', ', $attempted);
			echo json_encode([
				'success'=>false,
				'error'=>'Import file not found',
				'provider'=>$provider,
				'expectedPatterns'=>['apiship_' . $provider . '.ndjson','apiship_' . $provider . '.json'],
				'attemptedPaths'=>$attempted,
				'cacheDir'=>$cacheDir,
				'debug'=>$debug
			]);
			jexit();
		}
		try {
			$result = ApiShipFetchHelper::importFromFile($provider, $file, 1000);
			$result['debug'][] = 'apishipimportFile finished';
			echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		} catch (\Throwable $e) {
			echo json_encode(['success'=>false,'error'=>$e->getMessage(),'provider'=>$provider,'debug'=>$debug]);
		}
		jexit();
	}

	/**
	 * Диагностический инструмент для отправки одного прямого HTTP запроса и просмотра сырого ответа.
	 * @since 1.0.0
	 */
	public function apishipdiagRequest(): void
	{
		$debug = [];
		$debug[] = 'apishipdiagRequest called';
		@header('Content-Type: application/json; charset=utf-8');
		@header('Cache-Control: no-cache, must-revalidate');
		$app = Factory::getApplication();
		try { $this->checkToken('get'); $debug[]='Token OK'; } catch (\Exception $e) { echo json_encode(['success'=>false,'error'=>'CSRF token validation failed','debug'=>$debug]); jexit(); }

		$url = $app->input->getString('url', '');
		if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\/api\.apiship\.ru\//', $url)) {
			echo json_encode(['success'=>false,'error'=>'Invalid or not allowed URL. Must be an api.apiship.ru URL.','debug'=>$debug]);
			jexit();
		}

		$responsePayload = [
			'success' => false,
			'requestUrl' => $url,
			'response' => null,
			'error' => null,
			'debug' => $debug,
		];

		try {
			$http = \Joomla\CMS\Http\HttpFactory::getHttp();
			$headers = [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma' => 'no-cache',
				'Expires' => '0',
				'X-Cache-Bypass' => 'true',
				'X-Diag-Tool-Request' => 'true'
			];
			$debug[] = 'Sending GET request to: ' . $url;
			$response = $http->get($url, $headers);

			$responsePayload['success'] = true;
			$responsePayload['response'] = [
				'code' => $response->code,
				'headers' => (string) $response->headers,
				'body_truncated' => mb_substr($response->body, 0, 2000) . '... (truncated)',
			];

		} catch (\Throwable $e) {
			$responsePayload['error'] = $e->getMessage();
			$debug[] = 'ERROR: ' . $e->getMessage();
		}

		$responsePayload['debug'] = $debug;
		echo json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		jexit();
	}

	/**
	 * Reset inactive_count for all PVZ points
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function resetInactivePvz()
	{
		$app = Factory::getApplication();

		try {
			$this->checkToken('get');
		} catch (\Exception $e) {
			$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_RESET_INACTIVE_PVZ_ERROR', 'Invalid token'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_radicalmart_telegram&view=status', false));
			return;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			// Count how many will be reset
			$q = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__radicalmart_apiship_points'))
				->where($db->quoteName('inactive_count') . ' > 0');
			$count = (int) $db->setQuery($q)->loadResult();

			// Reset all counters
			$q2 = $db->getQuery(true)
				->update($db->quoteName('#__radicalmart_apiship_points'))
				->set($db->quoteName('inactive_count') . ' = 0')
				->where($db->quoteName('inactive_count') . ' > 0');
			$db->setQuery($q2)->execute();

			// Also clean up related nonces
			$q3 = $db->getQuery(true)
				->delete($db->quoteName('#__radicalmart_telegram_nonces'))
				->where($db->quoteName('scope') . ' = ' . $db->quote('pvz_inactive'));
			$db->setQuery($q3)->execute();

			$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_RESET_INACTIVE_PVZ_SUCCESS', $count), 'success');

		} catch (\Throwable $e) {
			$app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_RESET_INACTIVE_PVZ_ERROR', $e->getMessage()), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_radicalmart_telegram&view=status', false));
	}

	/**
	 * Get inactive PVZ statistics (AJAX)
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function inactivePvzStats()
	{
		header('Content-Type: application/json; charset=utf-8');

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			// Count PVZ with inactive_count >= 10 (permanently hidden)
			$q1 = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__radicalmart_apiship_points'))
				->where($db->quoteName('inactive_count') . ' >= 10');
			$permanentlyInactive = (int) $db->setQuery($q1)->loadResult();

			// Count PVZ with 0 < inactive_count < 10 (temporarily flagged)
			$q2 = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__radicalmart_apiship_points'))
				->where($db->quoteName('inactive_count') . ' > 0')
				->where($db->quoteName('inactive_count') . ' < 10');
			$temporarilyFlagged = (int) $db->setQuery($q2)->loadResult();

			// Total PVZ count
			$q3 = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__radicalmart_apiship_points'));
			$total = (int) $db->setQuery($q3)->loadResult();

			echo json_encode([
				'success' => true,
				'data' => [
					'total' => $total,
					'permanently_inactive' => $permanentlyInactive,
					'temporarily_flagged' => $temporarilyFlagged,
					'active' => $total - $permanentlyInactive,
				]
			], JSON_UNESCAPED_UNICODE);

		} catch (\Throwable $e) {
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage()
			], JSON_UNESCAPED_UNICODE);
		}

		jexit();
	}
}
