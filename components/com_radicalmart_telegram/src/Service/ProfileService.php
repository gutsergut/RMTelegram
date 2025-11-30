<?php
/**
 * @package     com_radicalmart_telegram (site)
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
    public function getProfile(int $chatId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
        $user = null;
        if ($userId > 0) {
            $user = Factory::getUser($userId);
        }
        $points = 0.0;
        if ($userId > 0 && class_exists(PointsHelper::class)) {
            $points = (float) PointsHelper::getCustomerPoints($userId);
        }
        $info = [];
        $codes = [];
        $canCreate = false;
        $createMode = '';
        if ($userId > 0) {
            $referralData = $this->getReferralData($userId);
            $info = $referralData['info'];
            $codes = $referralData['codes'];
            $canCreate = $referralData['can_create'];
            $createMode = $referralData['create_mode'];
        }
        return [
            'user' => ($user ? [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?: $user->username ?: ''),
                'username' => (string) $user->username,
                'email' => (string) $user->email,
                'phone' => (string) ($user->getParam('profile.phonenum') ?: ''),
            ] : null),
            'points' => $points,
            'referrals_info' => $info,
            'referral_codes' => array_map(function($c) {
                return [
                    'id' => (int) ($c->id ?? 0),
                    'code' => (string) ($c->code ?? ''),
                    'discount' => (string) ($c->discount_string ?? ''),
                    'enabled' => (bool) ($c->enabled ?? false),
                    'link' => (string) ($c->link ?? ''),
                    'expires' => (string) ($c->expires ?? ''),
                ];
            }, $codes),
            'can_create_code' => $canCreate,
            'create_mode' => $createMode,
        ];
    }

    public function createReferralCode(int $userId, string $currency = '', string $customCode = ''): ?string
    {
        try {
            $app = Factory::getApplication();
            $refModel = $app->bootComponent('com_radicalmart_bonuses')
                ->getMVCFactory()
                ->createModel('Referrals', 'Site', ['ignore_request' => true]);
            $refModel->setState('user.id', $userId);
            return $refModel->createCode($customCode, $currency) ?: null;
        } catch (\Throwable $e) {
            Log::add('ProfileService::createReferralCode error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            return null;
        }
    }

    public function getConsents(int $chatId): array
    {
        $statuses = ConsentHelper::getConsents(max(0, $chatId));
        $docs = ConsentHelper::getAllDocumentUrls();
        return ['statuses' => $statuses, 'documents' => $docs];
    }

    public function setConsent(int $chatId, string $type, bool $value): bool
    {
        $allowed = ['personal_data', 'marketing', 'terms'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid consent type');
        }
        return ConsentHelper::saveConsent($chatId, $type, $value);
    }

    public function getLegalDocument(string $type): string
    {
        $map = [
            'privacy' => 'article_privacy_policy',
            'consent' => 'article_consent_personal_data',
            'terms' => 'article_terms_of_service',
            'marketing' => 'article_consent_marketing'
        ];
        if (!isset($map[$type])) {
            throw new \InvalidArgumentException('Invalid document type');
        }
        $app = Factory::getApplication();
        $params = $app->getParams('com_radicalmart_telegram');
        $articleId = (int) $params->get($map[$type], 0);
        if ($articleId <= 0) {
            return '<p>Документ не настроен.</p>';
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $q = $db->getQuery(true)
            ->select($db->quoteName(['introtext', 'fulltext']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $articleId);
        $row = $db->setQuery($q, 0, 1)->loadObject();
        if (!$row) {
            return '<p>Документ не найден.</p>';
        }
        $htmlRaw = (string)($row->introtext ?? '') . (string)($row->fulltext ?? '');
        return $this->sanitizeHtml($htmlRaw);
    }

    private function getReferralData(int $userId): array
    {
        $info = [];
        $codes = [];
        $canCreate = false;
        $createMode = '';
        try {
            $app = Factory::getApplication();
            $refModel = $app->bootComponent('com_radicalmart_bonuses')
                ->getMVCFactory()
                ->createModel('Referrals', 'Site', ['ignore_request' => true]);
            $refModel->setState('user.id', $userId);
            $info = $refModel->getInfo($userId);
            $codes = $refModel->getCodes() ?: [];
            $createMode = $refModel->canCreateCode();
            $canCreate = ($createMode !== false);
        } catch (\Throwable $e) {}
        return ['info' => $info, 'codes' => $codes, 'can_create' => $canCreate, 'create_mode' => $createMode];
    }

    private function sanitizeHtml(string $html): string
    {
        $clean = preg_replace('#<script[^>]*?>.*?</script>#is', '', $html);
        $clean = preg_replace('#<iframe[^>]*?>.*?</iframe>#is', '', $clean);
        $clean = preg_replace('#<style[^>]*?>.*?</style>#is', '', $clean);
        $clean = preg_replace('#on[a-zA-Z]+\s*=\s*["\'][^"\']*["\']#is', '', $clean);
        $clean = preg_replace('#<a([^>]+)>#i', '<a$1 target="_blank" rel="noopener">', $clean);
        return $clean;
    }
}
