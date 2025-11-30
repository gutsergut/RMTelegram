<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис бонусов - applyPoints(), applyPromo(), removePromo()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper;

class BonusesService
{
    public function applyPoints(int $chatId, float $amount): array
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
        if ($userId <= 0) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_USER_NOT_LINKED'));
        }
        $available = (float) PointsHelper::getCustomerPoints($userId);
        if ($amount <= 0) {
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $sessionData['bonuses'] = ['points' => 0];
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);
            return ['applied' => 0, 'available' => $available];
        }
        if ($amount > $available) {
            throw new \RuntimeException(Text::sprintf('COM_RADICALMART_TELEGRAM_ERR_INSUFFICIENT_POINTS', $available));
        }
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        $sessionData['bonuses'] = ['points' => $amount];
        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
        return ['applied' => $amount, 'available' => $available - $amount];
    }

    public function applyPromo(int $chatId, string $code): array
    {
        $app = Factory::getApplication();
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'));
        }
        $validationResult = $this->validatePromoCode($code, $cart);
        if (!$validationResult['valid']) {
            throw new \RuntimeException($validationResult['error']);
        }
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        $sessionData['code'] = $code;
        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
        return [
            'code' => $code,
            'discount' => $validationResult['discount'] ?? '',
            'discount_string' => $validationResult['discount_string'] ?? ''
        ];
    }

    public function removePromo(int $chatId): array
    {
        $app = Factory::getApplication();
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        unset($sessionData['code']);
        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
        return ['removed' => true];
    }

    public function getBonusesData(int $chatId): array
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from($db->quoteName('#__radicalmart_telegram_users'))
            ->where($db->quoteName('chat_id') . ' = :chat')
            ->bind(':chat', $chatId);
        $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
        $points = 0.0;
        if ($userId > 0 && class_exists(PointsHelper::class)) {
            $points = (float) PointsHelper::getCustomerPoints($userId);
        }
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
        $appliedPoints = (float) ($sessionData['bonuses']['points'] ?? 0);
        $appliedCode = (string) ($sessionData['code'] ?? '');
        return [
            'available_points' => $points,
            'applied_points' => $appliedPoints,
            'promo_code' => $appliedCode
        ];
    }

    private function validatePromoCode(string $code, object $cart): array
    {
        if (empty($code)) {
            return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_EMPTY')];
        }
        try {
            if (!class_exists(CodesHelper::class)) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_NOT_AVAILABLE')];
            }
            $codeData = CodesHelper::getCode($code);
            if (!$codeData || empty($codeData->id)) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_INVALID')];
            }
            if (empty($codeData->enabled)) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_DISABLED')];
            }
            $now = Factory::getDate()->toSql();
            if (!empty($codeData->date_start) && $codeData->date_start > $now) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_NOT_STARTED')];
            }
            if (!empty($codeData->date_end) && $codeData->date_end < $now) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_EXPIRED')];
            }
            $discountString = '';
            if (!empty($codeData->discount_string)) {
                $discountString = $codeData->discount_string;
            } elseif (!empty($codeData->discount)) {
                $discountString = $codeData->discount . '%';
            }
            return [
                'valid' => true,
                'discount' => $codeData->discount ?? '',
                'discount_string' => $discountString
            ];
        } catch (\Throwable $e) {
            Log::add('BonusesService::validatePromoCode error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_VALIDATION_FAILED')];
        }
    }
}
