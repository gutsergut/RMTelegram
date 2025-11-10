<?php
/*
 * @package     plg_task_radicalmart_telegram_sync
 */

namespace Joomla\Plugin\Task\RadicalMartTelegramSync\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Event\SubscriberInterface;

class RadicalMartTelegramSync extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'radicalmart_telegram.sync_pvz' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_SYNC_PVZ',
            'method'          => 'syncPvz',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Синхронизация ПВЗ из ApiShip
     */
    protected function syncPvz(ExecuteTaskEvent $event): int
    {
        Log::add('Task syncPvz started', Log::INFO, 'com_radicalmart.telegram');

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Получаем список провайдеров для обновления
            $q = $db->getQuery(true)
                ->select(['provider', 'total', 'last_fetch'])
                ->from($db->quoteName('#__radicalmart_telegram_pvz_meta'))
                ->where($db->quoteName('enabled') . ' = 1');

            $providers = $db->setQuery($q)->loadObjectList();

            if (empty($providers)) {
                $this->logTask('No enabled providers found');
                return Status::OK;
            }

            $updatedCount = 0;
            $errorCount = 0;

            foreach ($providers as $prov) {
                try {
                    // Запускаем обновление через API endpoint
                    $result = $this->fetchProviderPoints($prov->provider);

                    if ($result['success']) {
                        $updatedCount++;
                        $this->logTask("Provider {$prov->provider}: fetched {$result['fetched']} points");
                    } else {
                        $errorCount++;
                        $this->logTask("Provider {$prov->provider}: ERROR - {$result['error']}", 'warning');
                    }
                } catch (\Throwable $e) {
                    $errorCount++;
                    $this->logTask("Provider {$prov->provider}: EXCEPTION - {$e->getMessage()}", 'error');
                }
            }

            $this->logTask("Sync completed: {$updatedCount} updated, {$errorCount} errors");

            return $errorCount === 0 ? Status::OK : Status::KNOCKOUT;

        } catch (\Throwable $e) {
            $this->logTask('Fatal error: ' . $e->getMessage(), 'error');
            Log::add('Task syncPvz error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            return Status::KNOCKOUT;
        }
    }

    /**
     * Получить точки провайдера через внутренний API
     */
    protected function fetchProviderPoints(string $provider): array
    {
        try {
            // Используем helper из компонента
            $helperPath = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/src/Helper/ApiShipFetchHelper.php';

            if (!file_exists($helperPath)) {
                return ['success' => false, 'error' => 'ApiShipFetchHelper not found'];
            }

            require_once $helperPath;

            $helper = new \Joomla\Component\RadicalMartTelegram\Administrator\Helper\ApiShipFetchHelper();

            // Инициализация
            $init = $helper->initFetch([$provider]);

            if (!$init['success'] || empty($init['providers'])) {
                return ['success' => false, 'error' => 'Init failed'];
            }

            $provData = $init['providers'][0];
            $total = $provData['total'];
            $fetched = 0;

            // Пошаговая загрузка
            while ($fetched < $total) {
                $step = $helper->fetchPointsStep($provider, 500);

                if (!$step['success']) {
                    return ['success' => false, 'error' => $step['error'] ?? 'Unknown error'];
                }

                $fetched += $step['fetched'] ?? 0;

                if (!empty($step['completed'])) {
                    break;
                }

                // Защита от бесконечного цикла
                if ($fetched > $total * 1.5) {
                    break;
                }
            }

            return [
                'success' => true,
                'fetched' => $fetched,
                'total'   => $total,
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
