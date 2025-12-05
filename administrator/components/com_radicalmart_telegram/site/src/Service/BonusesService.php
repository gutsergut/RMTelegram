<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Сервис бонусов - applyPoints(), applyPromo(), removePromo()
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;
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

        // Use SessionStore for persistent storage by chat_id
        $sessionStore = new SessionStore();
        $checkoutData = $sessionStore->getCheckoutData($chatId);

        if ($amount <= 0) {
            $checkoutData['bonuses'] = ['points' => 0];
            $sessionStore->setCheckoutData($chatId, $checkoutData);
            $this->syncToJoomlaSession($checkoutData);
            return ['applied' => 0, 'available' => $available];
        }
        if ($amount > $available) {
            throw new \RuntimeException(Text::sprintf('COM_RADICALMART_TELEGRAM_ERR_INSUFFICIENT_POINTS', $available));
        }

        $checkoutData['bonuses'] = ['points' => $amount];
        $sessionStore->setCheckoutData($chatId, $checkoutData);
        $this->syncToJoomlaSession($checkoutData);

        return ['applied' => $amount, 'available' => $available - $amount];
    }

    public function applyPromo(int $chatId, string $code): array
    {
        $cartService = new CartService();
        $cart = $cartService->getCart($chatId);
        if (!$cart || empty($cart->id)) {
            throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'));
        }
        $validationResult = $this->validatePromoCode($code, $cart);
        if (!$validationResult['valid']) {
            throw new \RuntimeException($validationResult['error']);
        }

        // Get code ID for RadicalMart bonuses plugin compatibility
        $codeData = CodesHelper::find($code);
        $codeId = $codeData ? (int) $codeData->id : 0;

        // Use SessionStore for persistent storage by chat_id
        $sessionStore = new SessionStore();
        $checkoutData = $sessionStore->getCheckoutData($chatId);
        $checkoutData['code'] = $code;
        $checkoutData['code_id'] = $codeId;

        // CRITICAL: Store plugins.bonuses in SessionStore for persistence across requests
        // RadicalMart bonuses plugin expects: {"plugins":{"bonuses":{"codes":[5],"points":"0"}}}
        // Without this, discount won't be calculated when order is created!
        if (!isset($checkoutData['plugins'])) {
            $checkoutData['plugins'] = [];
        }
        if (!isset($checkoutData['plugins']['bonuses'])) {
            $checkoutData['plugins']['bonuses'] = [];
        }
        $checkoutData['plugins']['bonuses']['codes'] = $codeId > 0 ? [$codeId] : [];
        $checkoutData['plugins']['bonuses']['points'] = $checkoutData['plugins']['bonuses']['points'] ?? '0';
        $checkoutData['plugins']['bonuses']['recalculate'] = 1;

        $sessionStore->setCheckoutData($chatId, $checkoutData);
        $this->syncToJoomlaSession($checkoutData);

        LogHelper::debug('[BonusesService::applyPromo] Applied code=' . $code . ' id=' . $codeId . ' chatId=' . $chatId . ' plugins.bonuses=' . json_encode($checkoutData['plugins']['bonuses'] ?? []));

        return [
            'code' => $code,
            'code_id' => $codeId,
            'discount' => $validationResult['discount'] ?? '',
            'discount_string' => $validationResult['discount_string'] ?? ''
        ];
    }

    public function removePromo(int $chatId): array
    {
        // Use SessionStore for persistent storage by chat_id
        $sessionStore = new SessionStore();
        $checkoutData = $sessionStore->getCheckoutData($chatId);
        unset($checkoutData['code']);
        $sessionStore->setCheckoutData($chatId, $checkoutData);
        $this->syncToJoomlaSession($checkoutData);

        return ['removed' => true];
    }

    public function getBonusesData(int $chatId): array
    {
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

        // Use SessionStore for persistent storage by chat_id
        $sessionStore = new SessionStore();
        $checkoutData = $sessionStore->getCheckoutData($chatId);
        $appliedPoints = (float) ($checkoutData['bonuses']['points'] ?? 0);
        $appliedCode = (string) ($checkoutData['code'] ?? '');

        return [
            'available_points' => $points,
            'applied_points' => $appliedPoints,
            'promo_code' => $appliedCode
        ];
    }

    /**
     * Sync checkout data to Joomla session for RadicalMart compatibility
     */
    private function syncToJoomlaSession(array $checkoutData): void
    {
        $app = Factory::getApplication();
        $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);

        if (isset($checkoutData['bonuses'])) {
            $sessionData['bonuses'] = $checkoutData['bonuses'];
        }

        // Sync promo code to both locations for compatibility:
        // 1. 'code' - simple key for our API (string code name)
        // 2. 'plugins.bonuses.codes' - RadicalMart format: ARRAY of code IDs (integers)
        // 3. 'plugins.bonuses.recalculate' - flag to trigger discount recalculation
        if (isset($checkoutData['code']) && !empty($checkoutData['code'])) {
            $sessionData['code'] = $checkoutData['code'];

            // Get code ID - either from checkoutData or fetch it
            $codeId = 0;
            if (!empty($checkoutData['code_id'])) {
                $codeId = (int) $checkoutData['code_id'];
            } elseif (class_exists(CodesHelper::class)) {
                $codeData = CodesHelper::find($checkoutData['code']);
                $codeId = $codeData ? (int) $codeData->id : 0;
            }

            // Set in RadicalMart plugins format - ARRAY of code IDs
            if (!isset($sessionData['plugins'])) {
                $sessionData['plugins'] = [];
            }
            if (!isset($sessionData['plugins']['bonuses'])) {
                $sessionData['plugins']['bonuses'] = [];
            }

            // RadicalMart bonuses plugin expects array of code IDs
            $sessionData['plugins']['bonuses']['codes'] = $codeId > 0 ? [$codeId] : [];
            $sessionData['plugins']['bonuses']['recalculate'] = 1;
            $sessionData['plugins']['bonuses']['recalculate_discounts'] = 1;

            // CRITICAL: bonuses plugin requires 'points' field to be present for discount calculation
            // Even if points are 0, the field must exist: {"points":"0","codes":[5]}
            // Without 'points' field, calculateProductsDiscounts() won't apply promo code discount!
            if (!isset($sessionData['plugins']['bonuses']['points'])) {
                $sessionData['plugins']['bonuses']['points'] = '0';
            }

            LogHelper::debug('[BonusesService::syncToJoomlaSession] Set plugins.bonuses.codes=' . json_encode($sessionData['plugins']['bonuses']['codes']));
        } elseif (array_key_exists('code', $checkoutData) && empty($checkoutData['code'])) {
            unset($sessionData['code']);
            if (isset($sessionData['plugins']['bonuses']['codes'])) {
                unset($sessionData['plugins']['bonuses']['codes']);
            }
            if (isset($sessionData['plugins']['bonuses']['recalculate'])) {
                unset($sessionData['plugins']['bonuses']['recalculate']);
            }
        }

        $app->setUserState('com_radicalmart.checkout.data', $sessionData);
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

            // Use find() method to get code by string value
            $codeData = CodesHelper::find($code);

            if (!$codeData || empty($codeData->id)) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_INVALID')];
            }

            // Note: radicalmart_bonuses_codes table doesn't have 'enabled' field
            // Code is active if not expired (expires field)
            $now = Factory::getDate()->toSql();

            // Check expiration (expires field, not date_end)
            if (!empty($codeData->expires) && $codeData->expires !== '0000-00-00 00:00:00' && $codeData->expires < $now) {
                return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_EXPIRED')];
            }

            $discountString = '';
            if (!empty($codeData->discount_string)) {
                $discountString = $codeData->discount_string;
            } elseif (!empty($codeData->discount)) {
                // Check if discount contains % or is numeric
                $discount = $codeData->discount;
                if (is_numeric($discount)) {
                    $discountString = $discount . '%';
                } else {
                    $discountString = $discount;
                }
            }
            return [
                'valid' => true,
                'discount' => $codeData->discount ?? '',
                'discount_string' => $discountString
            ];
        } catch (\Throwable $e) {
            LogHelper::error('BonusesService::validatePromoCode error: ' . $e->getMessage() . ' | trace: ' . $e->getTraceAsString());
            return ['valid' => false, 'error' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_VALIDATION_FAILED')];
        }
    }
}


