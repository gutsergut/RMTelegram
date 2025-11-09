<?php
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
			$result['debug'] = $debug;
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
}
