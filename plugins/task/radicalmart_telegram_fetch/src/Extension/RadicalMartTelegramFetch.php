<?php
/*
 * @package     plg_task_radicalmart_telegram_fetch
 */

namespace Joomla\Plugin\Task\RadicalmartTelegramFetch\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class RadicalMartTelegramFetch extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait {
        advertiseRoutines as traitAdvertiseRoutines;
    }

    protected $autoloadLanguage = true;

    protected const TASKS_MAP = [
        'radicalmart_telegram.fetch' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_FETCH',
        ],
        // Backward compatibility with legacy routine id (pre-refactor)
        'plg_task_radicalmart_telegram_fetch_apiship' => [
            'langConstPrefix' => 'PLG_TASK_RADICALMART_TELEGRAM_FETCH',
        ],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'runFetch',
        ];
    }

    // Ensure language is loaded before advertising routines so TITLE/DESC resolve.
    public function advertiseRoutines($event): void
    {
        // Init logger early to trace plugin load via Scheduler UI
        Log::addLogger(
            ['text_file' => 'com_radicalmart.telegram.php'],
            Log::ALL,
            ['com_radicalmart.telegram']
        );
        Log::add('advertiseRoutines invoked (task options list)', Log::INFO, 'com_radicalmart.telegram');
        $this->loadLanguage('plg_task_radicalmart_telegram_fetch');
        $this->traitAdvertiseRoutines($event);
    }

    public function runFetch(ExecuteTaskEvent $event): void
    {
        $routineId = $event->getRoutineId();
        // Early trace of event firing regardless of routine match
        Log::addLogger(
            ['text_file' => 'com_radicalmart.telegram.php'],
            Log::ALL,
            ['com_radicalmart.telegram']
        );
        Log::add('onExecuteTask received: routineId=' . $routineId, Log::INFO, 'com_radicalmart.telegram');
        if (!\array_key_exists($routineId, self::TASKS_MAP)) {
            Log::add('RoutineId not in TASKS_MAP, skipping', Log::WARNING, 'com_radicalmart.telegram');
            return;
        }

        $this->startRoutine($event);
        $this->loadLanguage(); // Defensive

        // Logger already initialized above (id matched) but ensure category active
        $startTs = microtime(true);
        Log::add(
            'Routine start: ' . $routineId,
            Log::INFO,
            'com_radicalmart.telegram'
        );

        // Use unified helper from the component
        $helperPath = JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/src/Helper/ApiShipFetchHelper.php';
        if (!is_file($helperPath)) {
            $msg = 'Helper path missing: ' . $helperPath;
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }
        try {
            require_once $helperPath;
        } catch (\Throwable $e) {
            $msg = 'Include failed: ' . $e->getMessage();
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }
        $exists = class_exists('Joomla\\Component\\RadicalMartTelegram\\Administrator\\Helper\\ApiShipFetchHelper');
        Log::add('Helper class exists? ' . ($exists ? 'yes' : 'no'), Log::INFO, 'com_radicalmart.telegram');
        if (!$exists) {
            $msg = 'Helper class not found after include';
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }

        $params = ComponentHelper::getParams('com_radicalmart_telegram');

        // Ensure plugin params registry exists in CLI context
        $pluginParams = $this->params instanceof Registry ? $this->params : null;
        if ($pluginParams === null) {
            try {
                $plg = PluginHelper::getPlugin('task', 'radicalmart_telegram_fetch');
                $pluginParams = new Registry($plg->params ?? '');
            } catch (\Throwable $e) {
                $pluginParams = new Registry();
            }
        }

        $providersParam = (string) (
            $pluginParams->get('providers')
            ?: $params->get('apiship_providers', 'yataxi,cdek,x5')
        );
        $providersList = array_filter(array_map('trim', explode(',', $providersParam)));
        $token = (string) $params->get('apiship_api_key', '');
        Log::add(
            'Preflight: providers=' . implode(',', $providersList)
            . '; tokenLen=' . strlen($token),
            Log::INFO,
            'com_radicalmart.telegram'
        );
        // Row count before
        $db = null;
        try {
            $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
        } catch (\Throwable $e) {
            Log::add('DB driver init error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
        }
        try {
            $before = $db
                ? (int) $db->setQuery(
                    'SELECT COUNT(*) FROM ' . $db->quoteName('#__radicalmart_apiship_points')
                )->loadResult()
                : 0;
            if ($db) {
                Log::add('Row count before fetch: ' . $before, Log::INFO, 'com_radicalmart.telegram');
            }
        } catch (\Throwable $e) {
            Log::add('Row count pre error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
            $before = 0;
        }
        if (empty($providersList)) {
            $msg = 'Weekly fetch aborted: empty providers list';
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }

        try {
            $result = \Joomla\Component\RadicalMartTelegram\Administrator\Helper\ApiShipFetchHelper::fetchAllPoints(
                $providersList
            );
        } catch (\Throwable $e) {
            $msg = 'Fetch exception: ' . $e->getMessage();
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }

        if (!($result['success'] ?? false)) {
            $msg = 'Weekly fetch failed: ' . ($result['message'] ?? 'unknown error');
            Log::add($msg, Log::ERROR, 'com_radicalmart.telegram');
            $this->logTask($msg, 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }

        // Row count after
        try {
            if (!$db) {
                $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
            }
            $after = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName('#__radicalmart_apiship_points')
            )->loadResult();
        } catch (\Throwable $e) {
            $after = $before;
        }
        $delta = $after - $before;
        $duration = round((microtime(true) - $startTs), 3);
        $msg = 'Weekly fetch OK: total=' . ($result['total'] ?? 0)
            . ', providers=' . count($providersList)
            . ', delta=' . $delta
            . ', duration=' . $duration . 's';
        Log::add($msg, Log::INFO, 'com_radicalmart.telegram');
        $this->logTask($msg);
        $this->endRoutine($event, Status::OK);
    }
}
