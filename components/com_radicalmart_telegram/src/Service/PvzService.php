<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис ПВЗ - getPvzList(), markPvz()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

class PvzService
{
    public function getPvzList(array $bounds, array $providers = [], int $limit = 500): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $minLat = (float) ($bounds['sw']['lat'] ?? 0);
        $maxLat = (float) ($bounds['ne']['lat'] ?? 0);
        $minLon = (float) ($bounds['sw']['lon'] ?? 0);
        $maxLon = (float) ($bounds['ne']['lon'] ?? 0);
        $query = $db->getQuery(true)
            ->select(['id', 'ext_id', 'provider', 'name', 'address', 'city', 'lat', 'lon', 'schedule', 'is_active'])
            ->from($db->quoteName('#__radicalmart_telegram_pvz'))
            ->where($db->quoteName('lat') . ' BETWEEN :minLat AND :maxLat')
            ->where($db->quoteName('lon') . ' BETWEEN :minLon AND :maxLon')
            ->bind(':minLat', $minLat)
            ->bind(':maxLat', $maxLat)
            ->bind(':minLon', $minLon)
            ->bind(':maxLon', $maxLon);
        if (!empty($providers)) {
            $query->whereIn($db->quoteName('provider'), $providers, \Joomla\Database\ParameterType::STRING);
        }
        $query->order($db->quoteName('is_active') . ' DESC, ' . $db->quoteName('name') . ' ASC');
        $rows = $db->setQuery($query, 0, $limit)->loadObjectList();
        $points = [];
        $inactiveCount = 0;
        foreach ($rows as $row) {
            if (empty($row->is_active)) {
                $inactiveCount++;
            }
            $points[] = [
                'id' => (string) $row->ext_id,
                'provider' => (string) $row->provider,
                'name' => (string) $row->name,
                'address' => (string) $row->address,
                'city' => (string) $row->city,
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'schedule' => (string) $row->schedule,
                'is_active' => !empty($row->is_active)
            ];
        }
        return [
            'points' => $points,
            'total' => count($points),
            'inactive_count' => $inactiveCount
        ];
    }

    public function markPvz(string $provider, string $extId, bool $active): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $activeInt = $active ? 1 : 0;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_pvz'))
            ->set($db->quoteName('is_active') . ' = :active')
            ->where($db->quoteName('provider') . ' = :provider')
            ->where($db->quoteName('ext_id') . ' = :extId')
            ->bind(':active', $activeInt, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':provider', $provider)
            ->bind(':extId', $extId);
        try {
            $db->setQuery($query)->execute();
            return $db->getAffectedRows() > 0;
        } catch (\Throwable $e) {
            Log::add('PvzService::markPvz error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            return false;
        }
    }
}
