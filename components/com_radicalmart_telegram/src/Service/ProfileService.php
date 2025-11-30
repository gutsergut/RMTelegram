<?php
/**
 * @package     com_radicalmart_telegram (site)
 *
 * Сервис профиля пользователя - profile(), consents(), setconsent(), legal()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;

class ProfileService
{
    /**
     * Получить профиль пользователя
     *
     * @param int $chatId Telegram chat ID
     * @return array Данные профиля
     */
    public function getProfile(int $chatId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get user_id by chat_id


















































































































































































































}    }        return $clean;        $clean = preg_replace('#<a([^>]+)>#i', '<a$1 target="_blank" rel="noopener">', $clean);        // Add safe target to links        $clean = preg_replace('#on[a-zA-Z]+\s*=\s*["\'][^"\']*["\']#is', '', $clean);        // Remove on* event handlers        $clean = preg_replace('#<style[^>]*?>.*?</style>#is', '', $clean);        $clean = preg_replace('#<iframe[^>]*?>.*?</iframe>#is', '', $clean);        $clean = preg_replace('#<script[^>]*?>.*?</script>#is', '', $html);        // Remove scripts, iframes, styles    {    private function sanitizeHtml(string $html): string    }        ];            'create_mode' => $createMode            'can_create' => $canCreate,            'codes' => $codes,            'info' => $info,        return [        }            // Component may not be installed        } catch (\Throwable $e) {            $canCreate = ($createMode !== false);            $createMode = $refModel->canCreateCode();            $codes = $refModel->getCodes() ?: [];            $info = $refModel->getInfo($userId);            $refModel->setState('user.id', $userId);                ->createModel('Referrals', 'Site', ['ignore_request' => true]);                ->getMVCFactory()            $refModel = $app->bootComponent('com_radicalmart_bonuses')            $app = Factory::getApplication();        try {        $createMode = '';        $canCreate = false;        $codes = [];        $info = [];    {    private function getReferralData(int $userId): array    // ============ Private helpers ============    }        return $this->sanitizeHtml($htmlRaw);        $htmlRaw = (string)($row->introtext ?? '') . (string)($row->fulltext ?? '');        }            return '<p>Документ не найден.</p>';        if (!$row) {        $row = $db->setQuery($q, 0, 1)->loadObject();            ->bind(':id', $articleId);            ->where($db->quoteName('id') . ' = :id')            ->from($db->quoteName('#__content'))            ->select($db->quoteName(['introtext', 'fulltext']))        $q = $db->getQuery(true)        $db = Factory::getContainer()->get('DatabaseDriver');        }            return '<p>Документ не настроен.</p>';        if ($articleId <= 0) {        $articleId = (int) $params->get($map[$type], 0);        $params = $app->getParams('com_radicalmart_telegram');        $app = Factory::getApplication();        }            throw new \InvalidArgumentException('Invalid document type');        if (!isset($map[$type])) {        ];            'marketing' => 'article_consent_marketing'            'terms' => 'article_terms_of_service',            'consent' => 'article_consent_personal_data',            'privacy' => 'article_privacy_policy',        $map = [    {    public function getLegalDocument(string $type): string     */     * @return string HTML документа     * @param string $type Тип документа (privacy, consent, terms, marketing)     *     * Получить юридический документ (HTML)    /**    }        return ConsentHelper::saveConsent($chatId, $type, $value);        }            throw new \InvalidArgumentException('Invalid consent type');        if (!in_array($type, $allowed, true)) {        $allowed = ['personal_data', 'marketing', 'terms'];    {    public function setConsent(int $chatId, string $type, bool $value): bool     */     * @return bool Успех операции     * @param bool $value Значение     * @param string $type Тип согласия (personal_data, marketing, terms)     * @param int $chatId Telegram chat ID     *     * Сохранить согласие пользователя    /**    }        ];            'documents' => $docs            'statuses' => $statuses,        return [        $docs = ConsentHelper::getAllDocumentUrls();        $statuses = ConsentHelper::getConsents(max(0, $chatId));    {    public function getConsents(int $chatId): array     */     * @return array ['statuses' => [...], 'documents' => [...]]     * @param int $chatId Telegram chat ID     *     * Получить статусы согласий пользователя    /**    }        }            return null;            Log::add('ProfileService::createReferralCode error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');        } catch (\Throwable $e) {            return $code;            $code = $refModel->createCode($customCode, $currency) ?: null;            $refModel->setState('user.id', $userId);                ->createModel('Referrals', 'Site', ['ignore_request' => true]);                ->getMVCFactory()            $refModel = $app->bootComponent('com_radicalmart_bonuses')            $app = Factory::getApplication();        try {    {    public function createReferralCode(int $userId, string $currency = '', string $customCode = ''): ?string     */     * @return string|null Созданный код или null     * @param string $customCode     * @param string $currency     * @param int $userId     *     * Создать реферальный код через профиль    /**    }        ];            'create_mode' => $createMode,            'can_create_code' => $canCreate,            }, $codes),                ];                    'expires' => (string) ($c->expires ?? ''),                    'link' => (string) ($c->link ?? ''),                    'enabled' => (bool) ($c->enabled ?? false),                    'discount' => (string) ($c->discount_string ?? ''),                    'code' => (string) ($c->code ?? ''),                    'id' => (int) ($c->id ?? 0),                return [            'referral_codes' => array_map(function($c) {            'referrals_info' => $info,            'points' => $points,            ] : null),                'phone' => (string) ($user->getParam('profile.phonenum') ?: ''),                'email' => (string) $user->email,                'username' => (string) $user->username,                'name' => (string) ($user->name ?: $user->username ?: ''),                'id' => (int) $user->id,            'user' => ($user ? [        return [        }            $createMode = $referralData['create_mode'];            $canCreate = $referralData['can_create'];            $codes = $referralData['codes'];            $info = $referralData['info'];            $referralData = $this->getReferralData($userId);        if ($userId > 0) {        $createMode = '';        $canCreate = false;        $codes = [];        $info = [];        // Get referral info        }            $points = (float) PointsHelper::getCustomerPoints($userId);        if ($userId > 0 && class_exists(PointsHelper::class)) {        $points = 0.0;        // Get points        }            $user = Factory::getUser($userId);        if ($userId > 0) {        $user = null;        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();            ->bind(':chat', $chatId);            ->where($db->quoteName('chat_id') . ' = :chat')            ->from($db->quoteName('#__radicalmart_telegram_users'))            ->select('user_id')        $query = $db->getQuery(true)
