<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;

class SessionStore
{
    protected $db;

    public function __construct()
    {
        $this->db = Factory::getContainer()->get('DatabaseDriver');
    }

    public function get(int $chatId): ?array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select(['chat_id', 'state', 'payload', 'cart_snapshot', 'expires_at', 'updated_at', 'last_update_id'])
            ->from($db->quoteName('#__radicalmart_telegram_sessions'))
            ->where($db->quoteName('chat_id') . ' = :cid')
            ->bind(':cid', $chatId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();
        return $row ?: null;
    }

    public function getStatePayload(int $chatId): array
    {
        $row = $this->get($chatId) ?: [];
        $state = (string)($row['state'] ?? 'idle');
        $payload = [];
        if (!empty($row['payload'])) {
            $json = json_decode((string)$row['payload'], true);
            if (is_array($json)) $payload = $json;
        }
        return [$state, $payload];
    }

    public function isDuplicate(int $chatId, int $updateId): bool
    {
        $row = $this->get($chatId);
        if (!$row) {
            return false;
        }

        $last = (int) ($row['last_update_id'] ?? 0);
        return $updateId <= $last;
    }

    public function setLastUpdate(int $chatId, int $updateId): void
    {
        $db = $this->db;
        $now = (new \Joomla\CMS\Date\Date())->toSql();

        // Try update
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_sessions'))
            ->set($db->quoteName('last_update_id') . ' = :uid')
            ->set($db->quoteName('updated_at') . ' = :updated')
            ->where($db->quoteName('chat_id') . ' = :cid')
            ->bind(':uid', $updateId, ParameterType::INTEGER)
            ->bind(':updated', $now)
            ->bind(':cid', $chatId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
        if ($db->getAffectedRows() === 0) {
            // Insert new row
            $obj = (object) [
                'chat_id' => $chatId,
                'state' => 'idle',
                'payload' => null,
                'cart_snapshot' => null,
                'expires_at' => null,
                'updated_at' => $now,
                'last_update_id' => $updateId,
            ];
            $db->insertObject('#__radicalmart_telegram_sessions', $obj);
        }
    }

    public function setState(int $chatId, string $state, ?array $payload = null): void
    {
        $db  = $this->db;
        $now = (new \Joomla\CMS\Date\Date())->toSql();

        $payloadJson = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

        // Try update first
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_sessions'))
            ->set($db->quoteName('state') . ' = :state')
            ->set($db->quoteName('payload') . ' = :payload')
            ->set($db->quoteName('updated_at') . ' = :updated')
            ->where($db->quoteName('chat_id') . ' = :cid')
            ->bind(':state', $state)
            ->bind(':payload', $payloadJson)
            ->bind(':updated', $now)
            ->bind(':cid', $chatId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
        if ($db->getAffectedRows() === 0) {
            // Insert
            $obj = (object) [
                'chat_id' => $chatId,
                'state' => $state,
                'payload' => $payloadJson,
                'cart_snapshot' => null,
                'expires_at' => null,
                'updated_at' => $now,
                'last_update_id' => 0,
            ];
            $db->insertObject('#__radicalmart_telegram_sessions', $obj);
        }
    }
}
