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

    /**
     * Get checkout data from payload (stored by chat_id, not HTTP session)
     */
    public function getCheckoutData(int $chatId): array
    {
        $row = $this->get($chatId);
        if (!$row || empty($row['payload'])) {
            return [];
        }
        $payload = json_decode((string)$row['payload'], true);
        return is_array($payload) && isset($payload['checkout']) ? (array)$payload['checkout'] : [];
    }

    /**
     * Set checkout data in payload (stored by chat_id, persists across requests)
     */
    public function setCheckoutData(int $chatId, array $checkoutData): void
    {
        $db  = $this->db;
        $now = (new \Joomla\CMS\Date\Date())->toSql();

        // Get current payload
        $row = $this->get($chatId);
        $payload = [];
        if ($row && !empty($row['payload'])) {
            $payload = json_decode((string)$row['payload'], true) ?: [];
        }
        
        // Merge checkout data into payload
        $payload['checkout'] = $checkoutData;
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Try update first
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__radicalmart_telegram_sessions'))
            ->set($db->quoteName('payload') . ' = :payload')
            ->set($db->quoteName('updated_at') . ' = :updated')
            ->where($db->quoteName('chat_id') . ' = :cid')
            ->bind(':payload', $payloadJson)
            ->bind(':updated', $now)
            ->bind(':cid', $chatId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
        if ($db->getAffectedRows() === 0) {
            // Insert new session row
            $obj = (object) [
                'chat_id' => $chatId,
                'state' => 'idle',
                'payload' => $payloadJson,
                'cart_snapshot' => null,
                'expires_at' => null,
                'updated_at' => $now,
                'last_update_id' => 0,
            ];
            $db->insertObject('#__radicalmart_telegram_sessions', $obj);
        }
    }

    /**
     * Merge checkout data (update specific keys without overwriting all)
     */
    public function mergeCheckoutData(int $chatId, array $data): void
    {
        $current = $this->getCheckoutData($chatId);
        $merged = array_replace_recursive($current, $data);
        $this->setCheckoutData($chatId, $merged);
    }

    /**
     * Clear checkout data (after order created)
     */
    public function clearCheckoutData(int $chatId): void
    {
        $this->setCheckoutData($chatId, []);
    }
}
