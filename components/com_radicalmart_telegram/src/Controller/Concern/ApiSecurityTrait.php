<?php
namespace Joomla\Component\RadicalMartTelegram\Site\Controller\Concern;

use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;

trait ApiSecurityTrait
{
    protected int $tgUserId = 0;
    protected string $tgUsername = '';

    protected function guardInitData(): void
    {
        $app = Factory::getApplication();
        $raw = (string) $app->input->get('tg_init', '', 'raw');
        $params = $app->getParams('com_radicalmart_telegram');
        $strict = (int) $params->get('strict_tg_init', 0) === 1;
        LogHelper::debug('guardInitData: raw=' . (strlen($raw) > 0 ? 'present (' . strlen($raw) . ' bytes)' : 'EMPTY') . ', strict=' . ($strict ? 'YES' : 'NO'));

        if ($raw === '') {
            if ($strict) {
                LogHelper::warning('Missing Telegram initData in strict mode');
                echo new JsonResponse(null, 'initData required', true);
                $app->close();
            }
            return;
        }

        try {
            $botToken = (string) $params->get('bot_token', '');
            if ($botToken === '') {
                LogHelper::warning('Bot token is empty - skip initData verify');
                return;
            }
            LogHelper::debug('Bot token length: ' . strlen($botToken) . ' chars (first 10: ' . substr($botToken, 0, 10) . '...)');

            if (!$this->verifyInitData($raw, $botToken)) {
                if ($strict) {
                    LogHelper::warning('Invalid Telegram initData signature (strict mode - block)');
                    echo new JsonResponse(null, 'Invalid initData', true);
                    $app->close();
                } else {
                    LogHelper::warning('Invalid Telegram initData signature (non-strict - continue as guest)');
                }
            }

            $pairs = [];
            parse_str($raw, $pairs);
            if (!empty($pairs['user'])) {
                $userObj = json_decode((string) $pairs['user'], true);
                if (is_array($userObj) && !empty($userObj['id'])) {
                    $this->tgUserId = (int) $userObj['id'];
                    if (!empty($userObj['username'])) {
                        $this->tgUsername = (string) $userObj['username'];
                    }
                }
            }

            if ($strict) {
                $chat = $this->getChatId();
                if ($chat > 0 && $this->tgUserId > 0) {
                    try {
                        $db = Factory::getContainer()->get('DatabaseDriver');
                        $q = $db->getQuery(true)
                            ->select(['user_id', 'tg_user_id'])
                            ->from($db->quoteName('#__radicalmart_telegram_users'))
                            ->where($db->quoteName('chat_id') . ' = :chat')
                            ->bind(':chat', $chat);
                        $row = $db->setQuery($q, 0, 1)->loadAssoc();
                        if ($row && !empty($row['tg_user_id']) && (int) $row['tg_user_id'] !== $this->tgUserId) {
                            LogHelper::warning('Strict initData mismatch: chat_id not bound to this tg_user_id');
                            echo new JsonResponse(null, 'Unauthorized chat mapping', true);
                            $app->close();
                        }
                    } catch (\Throwable $e) { }
                }
            }
        } catch (\Throwable $e) {
            LogHelper::warning('initData verify error: ' . $e->getMessage());
        }
    }

    protected function guardRateLimit(string $scope, int $maxPerMinute): void
    {
        $app = Factory::getApplication();
        $session = $app->getSession();
        $now = time();
        $key = 'com_radicalmart_telegram.rlm.' . md5($scope);
        $arr = $session->get($key, []);
        if (!is_array($arr)) { $arr = []; }
        $arr = array_values(array_filter($arr, function ($t) use ($now) { return is_int($t) && $t > $now - 60; }));
        if (count($arr) >= $maxPerMinute) {
            echo new JsonResponse(null, 'Too many requests', true);
            $app->close();
        }
        $arr[] = $now;
        $session->set($key, $arr);
    }

    protected function rateKey(): string
    {
        $chat = $this->getChatId();
        if ($this->tgUserId > 0) return 'tg:' . $this->tgUserId;
        if ($chat > 0) return 'chat:' . $chat;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return 'ip:' . $ip;
    }

    protected function guardRateLimitDb(string $scope, int $maxPerMinute): void
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $key = substr($this->rateKey(), 0, 64);
        $now = new \Joomla\CMS\Date\Date();
        $window = new \Joomla\CMS\Date\Date(date('Y-m-d H:i:00', $now->toUnix()));
        $windowSql = $window->toSql();

        try {
            $ins = $db->getQuery(true)
                ->insert($db->quoteName('#__radicalmart_telegram_ratelimits'))
                ->columns([$db->quoteName('scope'), $db->quoteName('rkey'), $db->quoteName('window_start'), $db->quoteName('count')])
                ->values(implode(',', [$db->quote($scope), $db->quote($key), $db->quote($windowSql), '1']))
                ->onDuplicateKeyUpdate([$db->quoteName('count') . ' = ' . $db->quoteName('count') . ' + 1']);
            $db->setQuery($ins)->execute();

            $sel = $db->getQuery(true)
                ->select($db->quoteName('count'))
                ->from($db->quoteName('#__radicalmart_telegram_ratelimits'))
                ->where($db->quoteName('scope') . ' = :s')
                ->where($db->quoteName('rkey') . ' = :k')
                ->where($db->quoteName('window_start') . ' = :w')
                ->bind(':s', $scope)
                ->bind(':k', $key)
                ->bind(':w', $windowSql);
            $cnt = (int) $db->setQuery($sel, 0, 1)->loadResult();

            if ($cnt > $maxPerMinute) {
                echo new JsonResponse(null, 'Too many requests', true);
                $app->close();
            }
        } catch (\Throwable $e) {
            $this->guardRateLimit($scope, $maxPerMinute);
        }
    }

    protected function guardNonce(string $scope): void
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_radicalmart_telegram');
        $strict = (int) $params->get('strict_nonce', 0) === 1;
        $nonce = trim((string) $app->input->get('nonce', '', 'string'));

        if ($nonce === '') {
            if ($strict) {
                echo new JsonResponse(null, 'Nonce required', true);
                $app->close();
            }
            return;
        }

        $chat = $this->getChatId();
        if ($chat <= 0) return;

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $row = (object) [
                'chat_id' => $chat,
                'scope' => substr($scope, 0, 32),
                'nonce' => substr($nonce, 0, 64),
                'created' => (new \Joomla\CMS\Date\Date())->toSql(),
            ];
            $db->insertObject('#__radicalmart_telegram_nonces', $row);
        } catch (\Throwable $e) {
            echo new JsonResponse(null, 'Duplicate request', true);
            $app->close();
        }
    }

    protected function verifyInitData(string $rawInit, string $botToken): bool
    {
        if ($rawInit === '' || $botToken === '' || strlen($rawInit) > 4096) {
            LogHelper::debug('verifyInitData FAIL: empty or too long');
            return false;
        }

        $pairs = [];
        parse_str($rawInit, $pairs);

        if (empty($pairs) || !isset($pairs['hash'])) {
            LogHelper::debug('verifyInitData FAIL: no pairs or no hash');
            return false;
        }

        $receivedHash = (string) $pairs['hash'];
        unset($pairs['hash']);
        $originalKeys = array_keys($pairs);

        if (isset($pairs['signature'])) {
            unset($pairs['signature']);
        }

        foreach ($pairs as $k => $v) {
            if (str_starts_with($k, '_tg_') || str_starts_with($k, 'tg_tech')) {
                unset($pairs[$k]);
            }
        }

        ksort($pairs, SORT_STRING);
        $lines = [];
        foreach ($pairs as $k => $v) {
            if (is_array($v)) {
                LogHelper::debug('verifyInitData FAIL: array value in pairs (key=' . $k . ')');
                return false;
            }
            $lines[] = $k . '=' . (string) $v;
        }
        $dataCheckString = implode("\n", $lines);

        $secretKey = hash_hmac('sha256', 'WebAppData', $botToken, true);
        $calc = hash_hmac('sha256', $dataCheckString, $secretKey);
        $okPrimary = hash_equals(strtolower($calc), strtolower($receivedHash));

        if ($okPrimary) {
            LogHelper::debug('verifyInitData: OK');
            return true;
        }

        LogHelper::warning('verifyInitData mismatch: received=' . substr($receivedHash, 0, 16) . '..., calc=' . substr($calc, 0, 16) . '...');
        return false;
    }

    protected function getChatId(): int
    {
        $app = Factory::getApplication();
        $chat = (int) $app->input->get('chat', 0, 'int');
        if ($chat > 0) return $chat;

        if ($this->tgUserId > 0) {
            LogHelper::debug('getChatId: fallback to tg_user_id=' . $this->tgUserId);
            return (int) $this->tgUserId;
        }

        return 0;
    }
}
