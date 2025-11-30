<?php
/**
 * @package     com_radicalmart_telegram (site)
 *
 * Сервис пунктов выдачи (ПВЗ) - pvz(), markpvz()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;

class PvzService
{
    /**
     * Получить список ПВЗ по bounding box
     *
     * @param string $bbox Bounding box: lon1,lat1,lon2,lat2
     * @param string $providers Список провайдеров через запятую
     * @param int $limit Максимальное количество результатов
     * @return array Список ПВЗ
     */
    public function getPvzList(string $bbox, string $providers = '', int $limit = 1000): array
    {
        return ApiShipIntegrationHelper::getPvzList($bbox, $providers, $limit);
    }

    /**
     * Отметить ПВЗ как активный/неактивный
     *
     * @param int $chatId Telegram chat ID (для предотвращения дублирования отметок)
     * @param string $extId Внешний ID ПВЗ
     * @param string $provider Провайдер (x5, cdek, etc)
     * @param bool $active true = активен (сброс счетчика), false = неактивен (инкремент)
     * @return bool Успех операции
     */


































































































}    }        Log::add("[PvzService] Reset inactive_count for $provider:$extId", Log::DEBUG, 'com_radicalmart.telegram');                $db->setQuery($q2)->execute();            ->bind(':nonce', $nonce);            ->bind(':scope', $scope)            ->where($db->quoteName('nonce') . ' = :nonce')            ->where($db->quoteName('scope') . ' = :scope')            ->delete($db->quoteName('#__radicalmart_telegram_nonces'))        $q2 = $db->getQuery(true)                $nonce = $extId . '_' . $provider;        $scope = 'pvz_inactive';        // Clean up nonces for this PVZ                $db->setQuery($q)->execute();            ->bind(':prov', $provider);            ->bind(':ext', $extId)            ->where($db->quoteName('provider') . ' = :prov')            ->where($db->quoteName('ext_id') . ' = :ext')            ->set($db->quoteName('inactive_count') . ' = 0')            ->update($db->quoteName('#__radicalmart_apiship_points'))        $q = $db->getQuery(true)                $db = Factory::getContainer()->get('DatabaseDriver');    {    private function resetInactiveCount(string $extId, string $provider): void     */     * Сбросить счетчик неактивности ПВЗ (когда тариф найден)    /**        }        Log::add("[PvzService] Incremented inactive_count for $provider:$extId by chat $chatId", Log::DEBUG, 'com_radicalmart.telegram');                $db->setQuery($q2)->execute();            ->bind(':prov', $provider);            ->bind(':ext', $extId)            ->where($db->quoteName('provider') . ' = :prov')            ->where($db->quoteName('ext_id') . ' = :ext')            ->set($db->quoteName('inactive_count') . ' = ' . $db->quoteName('inactive_count') . ' + 1')            ->update($db->quoteName('#__radicalmart_apiship_points'))        $q2 = $db->getQuery(true)        // Increment inactive_count                $db->insertObject('#__radicalmart_telegram_nonces', $obj);        ];            'created' => (new \DateTime())->format('Y-m-d H:i:s'),            'nonce' => $nonce,            'scope' => $scope,            'chat_id' => $chatId,        $obj = (object)[        // Record this report                }            return; // Already reported by this user        if ((int) $db->setQuery($q)->loadResult() > 0) {                    ->bind(':nonce', $nonce);            ->bind(':scope', $scope)            ->bind(':chat', $chatId)            ->where($db->quoteName('created') . ' > DATE_SUB(NOW(), INTERVAL 24 HOUR)')            ->where($db->quoteName('nonce') . ' = :nonce')            ->where($db->quoteName('scope') . ' = :scope')            ->where($db->quoteName('chat_id') . ' = :chat')            ->from($db->quoteName('#__radicalmart_telegram_nonces'))            ->select('COUNT(*)')        $q = $db->getQuery(true)                $nonce = $extId . '_' . $provider;        $scope = 'pvz_inactive';        // Check if this chat already reported this PVZ (within 24 hours)                $db = Factory::getContainer()->get('DatabaseDriver');    {    private function incrementInactiveCount(string $extId, string $provider, int $chatId): void     */     * Использует chat_id для предотвращения множественных отметок от одного пользователя     * Инкрементировать счетчик неактивности ПВЗ    /**        // ============ Private helpers ============        }        return true;                }            $this->incrementInactiveCount($extId, $provider, $chatId);        } else {            $this->resetInactiveCount($extId, $provider);        if ($active) {                }            throw new \InvalidArgumentException('Missing ext_id or provider');        if (empty($extId) || empty($provider)) {    {    public function markPvz(int $chatId, string $extId, string $provider, bool $active): bool
