<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\RadicalMartTelegram\Site\Service\CatalogService;
use Joomla\Component\RadicalMartTelegram\Site\Service\CartService;
use Joomla\Component\RadicalMart\Site\Model\CheckoutModel;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RMUserHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMartTelegram\Site\Service\TelegramClient;
use Joomla\CMS\Language\Text;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\CodesHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as AdminOrderModel;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Extension\ApiShip;
use Joomla\Component\RadicalMartTelegram\Site\Controller\Concern\ApiSecurityTrait;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;

class ApiController extends BaseController
{
    use ApiSecurityTrait;

    /**
     * Apply promo code to cart/order
     * Called via AJAX from checkout: task=api.applyPromo
     */
    public function applyPromo(): void
    {
        $app = Factory::getApplication();

        try {
            $this->guardInitData();

            $code = trim($app->input->getString('code', ''));
            $chatId = $app->input->getInt('chat', 0);

            if (empty($code)) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_REQUIRED')]);
                $app->close();
                return;
            }

            // Get user from TelegramUserHelper (promo may work for guests too)
            $tgUser = \Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper::getCurrentUser();
            $userId = $tgUser['user_id'] ?? 0;

            // Get customer_id if user is logged in
            // In RadicalMart, customer_id equals user_id directly
            $customerId = ($userId > 0) ? (int) $userId : 0;

            // Check if CodesHelper is available
            if (!class_exists(CodesHelper::class)) {
                echo new JsonResponse(['success' => false, 'message' => 'Bonuses component not available']);
                $app->close();
                return;
            }

            // Validate promo code via CodesHelper::find()
            $codeData = CodesHelper::find($code, 'code');

            // Code not found in database
            if (empty($codeData) || $codeData === false) {
                echo new JsonResponse([
                    'success' => false,
                    'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_NOT_FOUND'),
                    'debug' => 'Code not found in database'
                ]);
                $app->close();
                return;
            }

            // Check expiration
            if (!empty($codeData->expires) && $codeData->expires !== '0000-00-00 00:00:00') {
                $now = new \DateTime();
                $expires = new \DateTime($codeData->expires);
                if ($now > $expires) {
                    echo new JsonResponse([
                        'success' => false,
                        'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_EXPIRED'),
                        'debug' => 'Code expired on ' . $codeData->expires
                    ]);
                    $app->close();
                    return;
                }
            }

            // Check registered_only
            if (!empty($codeData->registered_only) && $customerId <= 0) {
                echo new JsonResponse([
                    'success' => false,
                    'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_REGISTERED_ONLY'),
                    'debug' => 'Code requires registration'
                ]);
                $app->close();
                return;
            }

            // Check customers_limit (one-time use codes)
            if (!empty($codeData->customers_limit) && (int) $codeData->customers_limit > 0) {
                $customers = $codeData->customers ?? [];
                if (!is_array($customers)) {
                    $customers = !empty($customers) ? array_filter(array_map('intval', explode(',', $customers))) : [];
                }
                // If limit reached and current customer not in list
                if (count($customers) >= (int) $codeData->customers_limit && !in_array($customerId, $customers)) {
                    echo new JsonResponse([
                        'success' => false,
                        'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_LIMIT_REACHED'),
                        'debug' => 'Customers limit reached: ' . count($customers) . '/' . $codeData->customers_limit
                    ]);
                    $app->close();
                    return;
                }
            }

            // Check currency match (if code has currency restriction)
            if (!empty($codeData->currency) && $codeData->currency !== 'RUB') {
                echo new JsonResponse([
                    'success' => false,
                    'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_CURRENCY_MISMATCH'),
                    'debug' => 'Code currency: ' . $codeData->currency . ', expected: RUB'
                ]);
                $app->close();
                return;
            }

            // Bind customer to code if logged in (add to customers array in DB)
            if ($customerId > 0) {
                $customers = $codeData->customers ?? [];
                if (!is_array($customers)) {
                    $customers = !empty($customers) ? array_filter(array_map('intval', explode(',', $customers))) : [];
                }
                if (!in_array($customerId, $customers)) {
                    $customers[] = $customerId;
                    // Update code in database
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $update = (object) [
                        'id' => $codeData->id,
                        'customers' => implode(',', array_unique(array_filter($customers)))
                    ];
                    $db->updateObject('#__radicalmart_bonuses_codes', $update, 'id');
                }
            }

            // Store code in RadicalMart session (com_radicalmart.checkout.data)
            // This is where RadicalMart Bonuses plugin expects them
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if (!isset($sessionData['plugins'])) {
                $sessionData['plugins'] = [];
            }
            if (!isset($sessionData['plugins']['bonuses'])) {
                $sessionData['plugins']['bonuses'] = [];
            }
            $sessionData['plugins']['bonuses']['codes'] = $code;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Also set cookie for RadicalMart Bonuses
            try {
                CodesHelper::setCookieCode($code);
            } catch (\Throwable $e) {
                // Cookie setting may fail, not critical
            }

            // Parse discount value - it's stored as "20%" or "100" string
            $discountRaw = $codeData->discount ?? '';
            $isPercent = (strpos($discountRaw, '%') !== false);
            $discountValue = (float) preg_replace('/[^0-9.]/', '', $discountRaw);
            $discountType = $isPercent ? 'percent' : 'fixed';

            // Build success message
            $discountText = '';
            if ($discountValue > 0) {
                $discountText = $isPercent ? "{$discountValue}%" : "{$discountValue} ₽";
            }

            echo new JsonResponse([
                'success' => true,
                'message' => Text::_('COM_RADICALMART_TELEGRAM_PROMO_APPLIED'),
                'code' => $code,
                'discount' => $discountValue,
                'discount_type' => $discountType,
                'discount_string' => $discountText,
                'code_id' => $codeData->id ?? 0,
                'debug_discount_raw' => $discountRaw,
                'debug_code_data' => json_encode($codeData)
            ]);

        } catch (\Throwable $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        $app->close();
    }
}
