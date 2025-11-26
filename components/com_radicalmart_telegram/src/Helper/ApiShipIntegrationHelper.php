<?php
namespace Joomla\Component\RadicalMartTelegram\Site\Helper;

use Joomla\CMS\Log\Log;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Extension\ApiShip;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Helper\ApiShipHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

class ApiShipIntegrationHelper
{
    public static function calculateTariff(int $shippingId, object $cart, string $pointId, string $provider): array
    {
        // Initialize logger
        Log::addLogger(
            ['text_file' => 'com_radicalmart.telegram.tariff.php'],
            Log::ALL,
            ['com_radicalmart.telegram.tariff']
        );

        $logPrefix = "[calcTariff shippingId=$shippingId, pointId=$pointId, provider=$provider]";
        Log::add("$logPrefix START", Log::DEBUG, 'com_radicalmart.telegram.tariff');

        // Store debug info for response
        $debugInfo = ['start' => true, 'shippingId' => $shippingId, 'pointId' => $pointId, 'provider' => $provider];

        try {
            if (!class_exists(ApiShip::class)) {
                Log::add("$logPrefix ApiShip class not found", Log::WARNING, 'com_radicalmart.telegram.tariff');
                $debugInfo['error'] = 'ApiShip class not found';
                return ['__debug' => $debugInfo];
            }

            $params = ApiShip::getShippingMethodParams($shippingId);
            $token  = $params->get('token');
            if (empty($token)) {
                Log::add("$logPrefix Token is empty", Log::WARNING, 'com_radicalmart.telegram.tariff');
                $debugInfo['error'] = 'Token is empty';
                return ['__debug' => $debugInfo];
            }
            Log::add("$logPrefix Token found (len=" . strlen($token) . ")", Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['token_len'] = strlen($token);

            $senders = $params->get('sender', []);
            $senderKeys = array_keys((array)$senders);
            Log::add("$logPrefix Senders config keys: " . implode(', ', $senderKeys), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['sender_keys'] = $senderKeys;

            $senderParams = $senders[$provider] ?? null;
            if (!$senderParams) {
                Log::add("$logPrefix Sender params for provider '$provider' NOT FOUND. Available: " . json_encode($senderKeys), Log::WARNING, 'com_radicalmart.telegram.tariff');
                $debugInfo['error'] = "Sender params for provider '$provider' NOT FOUND";
                $debugInfo['available_providers'] = $senderKeys;
                return ['__debug' => $debugInfo];
            }
            Log::add("$logPrefix Sender params found: " . json_encode($senderParams), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['sender_params'] = $senderParams;

            $pickupType = (int)($senderParams['pickup_type'] ?? 1);
            Log::add("$logPrefix pickupType=$pickupType", Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['pickup_type'] = $pickupType;

            // Build requestData in the SAME format as original ApiShip plugin!
            // Key difference: places is array of individual items (one per product unit), NOT places[0]['items']
            $requestData = [
                'deliveryTypes' => [2], // Delivery to point
                'pickupTypes'   => [$pickupType],
                'pointOutId'    => (int)$pointId,
                'places'        => [],
                'codCost'       => 0,
            ];

            if ($pickupType === 2) {
                $requestData['pointInId'] = (int)$senderParams['point'];
                Log::add("$logPrefix pointInId=" . $requestData['pointInId'], Log::DEBUG, 'com_radicalmart.telegram.tariff');
            } else {
                $requestData['from'] = [
                    'addressString' => $senderParams['address'] ?? '',
                    'countryCode'   => $senderParams['country'] ?? 'RU',
                ];
                Log::add("$logPrefix from=" . json_encode($requestData['from']), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            }
            $debugInfo['request_from_or_pointIn'] = $requestData['from'] ?? $requestData['pointInId'] ?? 'none';

            // Products - SAME structure as original plugin: one place per product unit
            $productCount = 0;
            $totalCost = 0;
            foreach ($cart->products as $product) {
                $shippingParams = new Registry($product->shipping ?? []);

                // Get dimensions
                $weight = (float)$shippingParams->get('weight', 0);
                $width  = (float)$shippingParams->get('width', 0);
                $height = (float)$shippingParams->get('height', 0);
                $length = (float)$shippingParams->get('length', 0);

                $wUnit = $shippingParams->get('weight_unit', 'g');
                $dUnit = $shippingParams->get('dimensions_units', 'cm');

                // Convert to grams
                if ($wUnit === 'kg') $weight = $weight * 1000;

                // Convert to cm
                if ($dUnit === 'mm') { $width /= 10; $height /= 10; $length /= 10; }
                if ($dUnit === 'm')  { $width *= 100; $height *= 100; $length *= 100; }

                // Round and ensure minimum 1
                $weight = (int)max(1, round($weight));
                $width  = (int)max(1, round($width));
                $height = (int)max(1, round($height));
                $length = (int)max(1, round($length));

                // Get quantity
                $productQty = is_array($product->quantity) ? (int)($product->quantity['value'] ?? 1) : (int)$product->quantity;
                if ($productQty < 1) $productQty = 1;

                // Get price for codCost calculation
                $productPrice = 0;
                if (isset($product->order) && isset($product->order['sum_final'])) {
                    $productPrice = (float)$product->order['sum_final'];
                } elseif (is_array($product->price)) {
                    $productPrice = (float)($product->price['final'] ?? $product->price['value'] ?? 0) * $productQty;
                } else {
                    $productPrice = (float)$product->price * $productQty;
                }
                $totalCost += $productPrice;

                // Add one place per product unit (like original plugin does)
                $item = [
                    'weight' => $weight,
                    'width'  => $width,
                    'height' => $height,
                    'length' => $length,
                ];

                for ($i = 1; $i <= $productQty; $i++) {
                    $requestData['places'][] = $item;
                }
                $productCount++;
            }

            $requestData['codCost'] = (int)round($totalCost);

            Log::add("$logPrefix Products: $productCount, places count: " . count($requestData['places']) . ", codCost: " . $requestData['codCost'], Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['product_count'] = $productCount;
            $debugInfo['places_count'] = count($requestData['places']);
            $debugInfo['cod_cost'] = $requestData['codCost'];
            $debugInfo['request_data'] = $requestData;

            // Call Calculator
            Log::add("$logPrefix Calling ApiShipHelper::calculator with requestData=" . json_encode($requestData, JSON_UNESCAPED_UNICODE), Log::DEBUG, 'com_radicalmart.telegram.tariff');

            $result = ApiShipHelper::calculator($token, $requestData, 'telegram.tariff.calc');

            // Log FULL response for debugging
            $fullResponse = $result->toArray();
            Log::add("$logPrefix FULL API response: " . json_encode($fullResponse, JSON_UNESCAPED_UNICODE), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['full_response'] = $fullResponse;

            Log::add("$logPrefix Calculator response keys: " . implode(', ', array_keys($fullResponse)), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['response_keys'] = array_keys($fullResponse);

            // Parse result
            $deliveryToPoint = $result->get('deliveryToPoint', []);
            Log::add("$logPrefix deliveryToPoint raw: " . json_encode($deliveryToPoint), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['delivery_to_point_raw'] = $deliveryToPoint;
            $debugInfo['delivery_to_point_count'] = is_array($deliveryToPoint) ? count($deliveryToPoint) : 0;

            // Find tariffs for the specific provider we requested
            $tariffs = [];
            if (!empty($deliveryToPoint)) {
                foreach ($deliveryToPoint as $providerResult) {
                    $pk = $providerResult->providerKey ?? '';
                    Log::add("$logPrefix Checking providerKey=$pk vs requested=$provider", Log::DEBUG, 'com_radicalmart.telegram.tariff');
                    if ($pk === $provider && !empty($providerResult->tariffs)) {
                        $tariffs = $providerResult->tariffs;
                        break;
                    }
                }
                // Fallback: if no provider match, try first with tariffs
                if (empty($tariffs) && !empty($deliveryToPoint[0]->tariffs)) {
                    $tariffs = $deliveryToPoint[0]->tariffs;
                    Log::add("$logPrefix Using fallback tariffs from first provider", Log::DEBUG, 'com_radicalmart.telegram.tariff');
                }
            }
            Log::add("$logPrefix Tariffs count: " . count($tariffs), Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['tariffs_count_before_filter'] = count($tariffs);

            // Filter by regexp if needed
            if (!empty($tariffs) && !empty($senderParams['tariffs_regexp'])) {
                $beforeCount = count($tariffs);
                foreach ($tariffs as $k => $t) {
                    if (!preg_match($senderParams['tariffs_regexp'], $t->tariffName)) {
                        unset($tariffs[$k]);
                    }
                }
                $tariffs = array_values($tariffs);
                Log::add("$logPrefix Tariffs filtered by regexp: $beforeCount -> " . count($tariffs), Log::DEBUG, 'com_radicalmart.telegram.tariff');
                $debugInfo['tariffs_after_filter'] = count($tariffs);
            }

            Log::add("$logPrefix END - returning " . count($tariffs) . " tariffs", Log::DEBUG, 'com_radicalmart.telegram.tariff');
            $debugInfo['final_tariffs_count'] = count($tariffs);

            // Return with debug info appended
            return ['tariffs' => $tariffs, '__debug' => $debugInfo];

        } catch (\Throwable $e) {
            Log::add('ApiShip calc error: ' . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
            $debugInfo['error'] = $e->getMessage();
            return ['__debug' => $debugInfo];
            return [];
        }
    }

    public static function getPvzList(string $bbox, string $providersStr, int $limit): array
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Try to ensure safe isolation level
        try {
            $db->setQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED')->execute();
        } catch (\Throwable $e) { /* ignore */ }

        $params = $app->getParams('com_radicalmart_telegram');
        $allowedDefault = array_filter(array_map('trim', explode(',', (string) $params->get('apiship_providers', 'yataxi,cdek,x5'))));
        $providersIn = array_filter(array_map('trim', explode(',', $providersStr)));
        // sanitize providers against allowed list
        $providers = !empty($providersIn) ? array_values(array_intersect($providersIn, $allowedDefault)) : $allowedDefault;
        // clamp limit
        $limit = max(1, min((int) $limit, 2000));
        $coords = array_map('floatval', explode(',', $bbox));
        $hasB  = (count($coords) === 4);
        $minLon= $hasB ? min($coords[0], $coords[2]) : null;
        $maxLon= $hasB ? max($coords[0], $coords[2]) : null;
        $minLat= $hasB ? min($coords[1], $coords[3]) : null;
        $maxLat= $hasB ? max($coords[1], $coords[3]) : null;

        // Try cache
        $items = null;
        $cacheEnabled = (int) $params->get('pvz_cache_enabled', 1) === 1;
        $ttlSeconds   = (int) $params->get('pvz_cache_ttl', 60);
        $precision    = max(0, min(4, (int) $params->get('pvz_cache_precision', 2)));
        $cacheDir     = JPATH_ROOT . '/media/com_radicalmart_telegram/apiship/cache';

        $key = null;

        if ($cacheEnabled && $ttlSeconds > 0 && $hasB) {
            $step = pow(10, -$precision);
            $norm = function($v,$s){ return number_format(round($v / $s) * $s, 6, '.', ''); };
            $nb = [ $norm($minLon,$step), $norm($minLat,$step), $norm($maxLon,$step), $norm($maxLat,$step) ];
            $provKey = $providers; sort($provKey); $provKey = implode(',', $provKey);
            $key = sha1($provKey . '|' . implode(',', $nb) . '|' . (string)$limit);
            $file = $cacheDir . '/pvz-' . $key . '.json';
            if (File::exists($file)) {
                $raw = @file_get_contents($file);
                if ($raw) {
                    $json = json_decode($raw, true);
                    if (is_array($json) && isset($json['expires']) && (time() < (int)$json['expires']) && isset($json['items']) && is_array($json['items'])) {
                        $items = $json['items'];
                    }
                }
            }
        }

        $where = [];
        if (!empty($providers)) {
            $where[] = $db->quoteName('provider') . ' IN (' . implode(',', array_map([$db, 'quote'], $providers)) . ')';
        }
        if ($hasB) {
            // Use simple lat/lon filtering
            $where[] = $db->quoteName('lat') . ' BETWEEN ' . $minLat . ' AND ' . $maxLat;
            $where[] = $db->quoteName('lon') . ' BETWEEN ' . $minLon . ' AND ' . $maxLon;
        } else {
            // No bbox provided — return empty set
            return [];
        }

        if ($items === null) {
            $showPostamats = (int) $params->get('show_postamats', 1) === 1;

            $query = $db->getQuery(true)
                ->select([$db->quoteName('provider'), $db->quoteName('ext_id'), $db->quoteName('title'), $db->quoteName('address'), $db->quoteName('lat'), $db->quoteName('lon'), $db->quoteName('pvz_type')])
                ->from($db->quoteName('#__radicalmart_apiship_points'))
                ->where($db->quoteName('operation') . ' = ' . $db->quote('giveout'))
                ->order($db->quoteName('provider') . ' ASC');

            if (!$showPostamats) {
                $query->where($db->quoteName('pvz_type') . ' = ' . $db->quote('1'));
            }

            if (!empty($where)) { $query->where(implode(' AND ', $where)); }
            $rows = [];
            try {
                $db->setQuery($query, 0, $limit);
                $rows = $db->loadAssocList();
            } catch (\Throwable $ex) {
                // Specific MySQL error under READ UNCOMMITTED
                $msg = (string) $ex->getMessage();
                if (stripos($msg, 'Update locks cannot be acquired during a READ UNCOMMITTED transaction') !== false) {
                    try { $db->setQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED')->execute(); } catch (\Throwable $e2) { /* ignore */ }
                    try { $db->setQuery($query, 0, $limit); $rows = $db->loadAssocList(); }
                    catch (\Throwable $e3) { $rows = []; }
                } else {
                    throw $ex;
                }
            }
            $items = array_map(function($r){
                // Map pvz_type to human readable name
                $typeCode = (string) ($r['pvz_type'] ?? '');
                $typeLabel = 'Пункт выдачи';
                if ($typeCode === '2') {
                    $typeLabel = 'Постамат';
                } elseif ($typeCode === '1') {
                    $typeLabel = 'Пункт выдачи';
                }
                // Map provider to human readable name
                $providerNames = [
                    'cdek' => 'СДЭК',
                    'x5' => 'X5 (Пятёрочка)',
                    'yataxi' => 'Яндекс Доставка',
                    'boxberry' => 'Boxberry',
                    'dpd' => 'DPD',
                    'pickpoint' => 'PickPoint',
                    'ozon' => 'Ozon',
                ];
                $providerLabel = $providerNames[$r['provider']] ?? $r['provider'];

                return [
                    'id' => $r['ext_id'],
                    'provider' => $r['provider'],
                    'provider_name' => $providerLabel,
                    'title' => $r['title'],
                    'address' => $r['address'],
                    'lat' => (float)$r['lat'],
                    'lon' => (float)$r['lon'],
                    'pvz_type' => $typeCode,
                    'pvz_type_name' => $typeLabel,
                ];
            }, $rows ?: []);

            // write cache
            if ($cacheEnabled && $ttlSeconds > 0) {
                if (!Folder::exists($cacheDir)) { Folder::create($cacheDir); }
                if (!isset($key)) {
                    $provKey = $providers; sort($provKey); $provKey = implode(',', $provKey);
                    $key = sha1($provKey . '|' . implode(',', [$minLon,$minLat,$maxLon,$maxLat]) . '|' . (string)$limit);
                }
                $file = $cacheDir . '/pvz-' . $key . '.json';
                $payload = json_encode(['expires' => time() + $ttlSeconds, 'items' => $items], JSON_UNESCAPED_UNICODE);
                @file_put_contents($file, $payload);
            }
        }

        return $items;
    }
}
