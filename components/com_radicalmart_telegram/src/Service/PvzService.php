<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис ПВЗ - getPvzList(), markPvz(), incrementInactiveCount(), resetInactiveCount()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;

class PvzService
{
    /**
     * Get PVZ list via ApiShipIntegrationHelper
     */
    public function getPvzList(string $bbox, string $providers = '', int $limit = 1000): array
    {
        return ApiShipIntegrationHelper::getPvzList($bbox, $providers, $limit);
    }

    /**
     * Increment inactive count for a PVZ point
     * Uses chat_id to prevent multiple increments from same user
     */
    public function incrementInactiveCount(string $extId, string $provider, int $chatId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Check if this chat already reported this PVZ (within 24 hours)
        $scope = 'pvz_inactive';
        $nonce = $extId . '_' . $provider;

        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__radicalmart_telegram_nonces'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->where($db->quoteName('scope') . ' = :scope')
            ->where($db->quoteName('nonce') . ' = :nonce')
            ->where($db->quoteName('created') . ' > DATE_SUB(NOW(), INTERVAL 24 HOUR)')
            ->bind(':chat', $chatId)
            ->bind(':scope', $scope)
            ->bind(':nonce', $nonce);

        if ((int) $db->setQuery($q)->loadResult() > 0) {
            return; // Already reported by this user
        }

        // Record this report
        $obj = (object)[
            'chat_id' => $chatId,
            'scope' => $scope,
            'nonce' => $nonce,
            'created' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__radicalmart_telegram_nonces', $obj);

        // Increment inactive_count
        $q2 = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_apiship_points'))
            ->set($db->quoteName('inactive_count') . ' = ' . $db->quoteName('inactive_count') . ' + 1')
            ->where($db->quoteName('ext_id') . ' = :ext')
            ->where($db->quoteName('provider') . ' = :prov')
            ->bind(':ext', $extId)
            ->bind(':prov', $provider);
        $db->setQuery($q2)->execute();

        Log::add("[PvzService] Incremented inactive_count for $provider:$extId by chat $chatId", Log::DEBUG, 'com_radicalmart.telegram');
    }

    /**
     * Reset inactive count for a PVZ point (when tariff is found)
     */
    public function resetInactiveCount(string $extId, string $provider): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $q = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_apiship_points'))
            ->set($db->quoteName('inactive_count') . ' = 0')
            ->where($db->quoteName('ext_id') . ' = :ext')
            ->where($db->quoteName('provider') . ' = :prov')
            ->bind(':ext', $extId)
            ->bind(':prov', $provider);
        $db->setQuery($q)->execute();
    }

    /**
     * Get point details from DB
     */
    public function getPoints(array $extIds): array
    {
        if (empty($extIds)) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $quotedIds = array_map(function($id) use ($db) {
            return $db->quote($id);
        }, $extIds);

        $q = $db->getQuery(true)
            ->select(['id', 'provider', 'ext_id', 'title', 'address', 'lat', 'lon', 'inactive_count'])
            ->from($db->quoteName('#__radicalmart_apiship_points'))
            ->where($db->quoteName('ext_id') . ' IN (' . implode(',', $quotedIds) . ')')
            ->where($db->quoteName('inactive_count') . ' < 10'); // Skip permanently inactive

        return $db->setQuery($q)->loadAssocList('ext_id') ?: [];
    }

    /**
     * Mark PVZ as active or inactive
     * @deprecated Use incrementInactiveCount/resetInactiveCount instead
     */
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
