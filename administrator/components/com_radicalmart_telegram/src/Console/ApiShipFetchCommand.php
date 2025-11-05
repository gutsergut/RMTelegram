<?php
/*
 * Console command: com_radicalmart_telegram:apiship:fetch
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiShipFetchCommand extends AbstractCommand
{
    protected static $defaultName = 'com_radicalmart_telegram:apiship:fetch';
    protected static $defaultDescription = 'Fetch ApiShip pickup points into DB (weekly full update).';

    protected function configure(): void
    {
        $this->addOption('providers', null, InputOption::VALUE_REQUIRED, 'Comma-separated providers list (e.g. yataxi,cdek,x5)');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $app     = Factory::getApplication();
        $params  = $app->getParams('com_radicalmart_telegram');
        $token   = (string) $params->get('apiship_api_key', '');
        $list    = (string) ($input->getOption('providers') ?: $params->get('apiship_providers', 'yataxi,cdek,x5'));
        $providers = array_filter(array_map('trim', explode(',', $list)));
        if ($token === '' || empty($providers)) {
            $output->writeln('<error>Missing token or providers</error>');
            return 1;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $operation = ['giveout'];
        $totalAll = 0; $inserted = 0; $updated = 0;
        foreach ($providers as $prov) {
            $total = ApiShipHelper::getPointsTotal($token, [$prov], $operation);
            $totalAll += $total;
            $output->writeln("Provider {$prov}: total {$total}");
            $limit = 500; $offset = 0;
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
        }

        Log::add('ApiShip fetch done: total=' . $totalAll . ', providers=' . implode(',', $providers), Log::INFO, 'com_radicalmart.telegram');
        $output->writeln('<info>Completed</info>');
        return 0;
    }
}

