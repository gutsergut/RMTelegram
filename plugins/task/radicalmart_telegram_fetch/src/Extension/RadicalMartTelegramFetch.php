<?php
/*
 * @package     plg_task_radicalmart_telegram_fetch
 */

namespace Joomla\Plugin\Task\Radicalmart_telegram_fetch\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Log\Log;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;

final class RadicalMartTelegramFetch extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected $autoloadLanguage = true;

    protected const TASKS_MAP = [
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

    public function runFetch(ExecuteTaskEvent $event): void
    {
        if (!\array_key_exists($event->getRoutineId(), self::TASKS_MAP)) {
            return;
        }

        $this->startRoutine($event);
        $app   = Factory::getApplication();
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $token = (string) $app->getParams('com_radicalmart_telegram')->get('apiship_api_key', '');
        $list  = (string) ($this->params->get('providers') ?: $app->getParams('com_radicalmart_telegram')->get('apiship_providers', 'yataxi,cdek,x5'));
        $providers = array_filter(array_map('trim', explode(',', $list)));
        if ($token === '' || empty($providers)) {
            $this->logTask('Missing ApiShip token or providers', 'error');
            $this->endRoutine($event, Status::KNOCKOUT);
            return;
        }

        $operation = ['giveout'];
        $limit = 500; $updated = 0; $totalAll = 0;
        foreach ($providers as $prov) {
            $total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
            $totalAll += $total;
            $offset = 0;
            $outDir = JPATH_ROOT . '/media/com_radicalmart_telegram/apiship';
            if (!\Joomla\CMS\Filesystem\Folder::exists($outDir)) { \Joomla\CMS\Filesystem\Folder::create($outDir); }
            $backup = [];
            while ($offset < $total) {
                $chunk = ApiShipHelper::getPoints($token, [$prov], $operation, $offset, $limit);
                if (!$chunk) break;
                foreach ($chunk as $row) {
                    $extId   = (string) ($row['id'] ?? ($row['externalId'] ?? ''));
                    $title   = (string) ($row['title'] ?? ($row['name'] ?? ''));
                    $address = (string) ($row['address'] ?? '');
                    $lat     = isset($row['latitude']) ? (float)$row['latitude'] : (isset($row['location']['latitude']) ? (float)$row['location']['latitude'] : null);
                    $lon     = isset($row['longitude']) ? (float)$row['longitude'] : (isset($row['location']['longitude']) ? (float)$row['location']['longitude'] : null);
                    if ($lat === null || $lon === null || $extId === '') continue;
                    $meta    = json_encode($row, JSON_UNESCAPED_UNICODE);
                    $q = $db->getQuery(true)
                        ->insert($db->quoteName('#__radicalmart_apiship_points'))
                        ->columns([
                            $db->quoteName('provider'), $db->quoteName('ext_id'), $db->quoteName('title'), $db->quoteName('address'),
                            $db->quoteName('lat'), $db->quoteName('lon'), $db->quoteName('operation'), $db->quoteName('point'),
                            $db->quoteName('meta'), $db->quoteName('updated_at')
                        ])
                        ->values(implode(',', [
                            $db->quote($prov), $db->quote($extId), $db->quote($title), $db->quote($address),
                            (string)$lat, (string)$lon, $db->quote('giveout'),
                            "ST_GeomFromText('POINT(" . (string)$lon . " " . (string)$lat . ")', 4326)",
                            $db->quote($meta), $db->quote((new \Joomla\CMS\Date\Date())->toSql())
                        ]))
                        ->onDuplicateKeyUpdate([
                            $db->quoteName('title') . ' = VALUES(' . $db->quoteName('title') . ')',
                            $db->quoteName('address') . ' = VALUES(' . $db->quoteName('address') . ')',
                            $db->quoteName('lat') . ' = VALUES(' . $db->quoteName('lat') . ')',
                            $db->quoteName('lon') . ' = VALUES(' . $db->quoteName('lon') . ')',
                            $db->quoteName('point') . ' = VALUES(' . $db->quoteName('point') . ')',
                            $db->quoteName('meta') . ' = VALUES(' . $db->quoteName('meta') . ')',
                            $db->quoteName('updated_at') . ' = VALUES(' . $db->quoteName('updated_at') . ')',
                        ]);
                    $db->setQuery($q)->execute();
                    $updated++;
                    // Append to backup (minimal)
                    $backup[] = [ 'id' => $extId, 'provider' => $prov, 'title' => $title, 'address' => $address, 'lat' => $lat, 'lon' => $lon ];
                }
                $offset += $limit;
            }
            $mq = $db->getQuery(true)
                ->insert($db->quoteName('#__radicalmart_apiship_meta'))
                ->columns([$db->quoteName('provider'), $db->quoteName('last_fetch'), $db->quoteName('last_total')])
                ->values(implode(',', [ $db->quote($prov), $db->quote((new \Joomla\CMS\Date\Date())->toSql()), (string)$total ]))
                ->onDuplicateKeyUpdate([
                    $db->quoteName('last_fetch') . ' = VALUES(' . $db->quoteName('last_fetch') . ')',
                    $db->quoteName('last_total') . ' = VALUES(' . $db->quoteName('last_total') . ')',
                ]);
            $db->setQuery($mq)->execute();
            // Write JSON backup
            try {
                $file = $outDir . '/' . $prov . '-latest.json';
                @file_put_contents($file, json_encode($backup, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {}
        }

        $msg = 'ApiShip weekly fetch completed: total=' . $totalAll . ', updated=' . $updated;
        Log::add($msg, Log::INFO, 'com_radicalmart.telegram');
        $this->logTask($msg);
        $this->endRoutine($event, Status::OK);
    }
}
