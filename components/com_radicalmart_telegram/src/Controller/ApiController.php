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
use Joomla\Component\RadicalMartTelegram\Site\Helper\LogHelper;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as AdminOrderModel;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;
use Joomla\Plugin\RadicalMartShipping\ApiShip\Extension\ApiShip;
use Joomla\Component\RadicalMartTelegram\Site\Controller\Concern\ApiSecurityTrait;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ApiShipIntegrationHelper;
use Joomla\Component\RadicalMartTelegram\Site\Service\CheckoutService;
use Joomla\Component\RadicalMartTelegram\Site\Service\BonusesService;
use Joomla\Component\RadicalMartTelegram\Site\Service\OrderService;
use Joomla\Component\RadicalMartTelegram\Site\Service\ProfileService;
use Joomla\Component\RadicalMartTelegram\Site\Service\PvzService;
use Joomla\Component\RadicalMartTelegram\Site\Helper\EmailVerificationHelper;

class ApiController extends BaseController
{
    use ApiSecurityTrait;

    public function list(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('list', 60);

        // Debug режим контролируется через LogHelper::isEnabled()
        $debug = LogHelper::isEnabled();

        $page = $app->input->getInt('page', 1);
        $lim  = $app->input->getInt('limit', 12);
        $inStock = $app->input->getInt('in_stock', 0) === 1;
        $sort = trim((string) $app->input->get('sort', '', 'string'));
        $priceFrom = trim((string) $app->input->get('price_from', '', 'string'));
        $priceTo   = trim((string) $app->input->get('price_to', '', 'string'));

        $filters = [];
        if ($inStock) { $filters['in_stock'] = 1; }
        if ($sort !== '') { $filters['sort'] = $sort; }
        if ($priceFrom !== '' || $priceTo !== '') { $filters['price'] = ['from'=>$priceFrom, 'to'=>$priceTo]; }
        // Category filter from buttons
        $categoryId = $app->input->getInt('category_id', 0);
        LogHelper::debug('ApiController.list: RAW category_id=' . $categoryId . ' (from input->getInt)', 'radicalmart_telegram_catalog');
        if ($categoryId > 0) {
            $filters['categories'] = [$categoryId];
            LogHelper::debug('ApiController.list: category_id=' . $categoryId . ' applied to filters', 'radicalmart_telegram_catalog');
        }
        // Field filters: read configured field_ids, load aliases from DB, pick values from request
        try {
            $params = $app->getParams('com_radicalmart_telegram');
            $cfg = $params->get('filters_fields');
            $fields = [];
            // DEBUG: вывод всех параметров запроса
            $allInput = $app->input->getArray();
            LogHelper::debug('ApiController.list: ALL INPUT PARAMS=' . json_encode($allInput, JSON_UNESCAPED_UNICODE), 'radicalmart_telegram_catalog');

            // DEBUG: проверка конфигурации фильтров
            LogHelper::debug('ApiController.list: filters_fields RAW cfg=' . json_encode($cfg, JSON_UNESCAPED_UNICODE) . ' type=' . gettype($cfg) . ' empty=' . (empty($cfg) ? 'YES' : 'NO') . ' isArray=' . (is_array($cfg) ? 'YES' : 'NO'), 'radicalmart_telegram_catalog');

            // Конвертируем объект в массив (Joomla subform возвращает stdClass)
            if (is_object($cfg)) {
                $cfg = get_object_vars($cfg);
                LogHelper::debug('ApiController.list: Converted object to array, new type=' . gettype($cfg), 'radicalmart_telegram_catalog');
            }

            // Сначала загружаем aliases из БД по field_id
            $fieldIdToAlias = [];
            if (!empty($cfg) && is_array($cfg)) {
                LogHelper::debug('ApiController.list: filters_fields config=' . json_encode($cfg, JSON_UNESCAPED_UNICODE), 'radicalmart_telegram_catalog');
                $fieldIds = [];
                foreach ($cfg as $row) {
                    if (is_object($row)) { $row = get_object_vars($row); }
                    if (!is_array($row)) { continue; }
                    if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                    if (!empty($row['field_id'])) { $fieldIds[] = (int)$row['field_id']; }
                }
                LogHelper::debug('ApiController.list: field_ids to load=' . json_encode($fieldIds), 'radicalmart_telegram_catalog');
                if (!empty($fieldIds)) {
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select($db->quoteName(['id','alias']))
                        ->from($db->quoteName('#__radicalmart_fields'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->where($db->quoteName('area') . ' = ' . $db->quote('products'))
                        ->whereIn($db->quoteName('id'), $fieldIds);
                    $rows = (array) $db->setQuery($q)->loadObjectList();
                    foreach ($rows as $r) { $fieldIdToAlias[(int)$r->id] = (string)$r->alias; }
                    LogHelper::debug('ApiController.list: fieldIdToAlias map=' . json_encode($fieldIdToAlias, JSON_UNESCAPED_UNICODE), 'radicalmart_telegram_catalog');
                }
            }
            // Теперь читаем значения из запроса по alias
            if (!empty($cfg) && is_array($cfg)) {
                foreach ($cfg as $row) {
                    if (is_object($row)) { $row = get_object_vars($row); }
                    if (!is_array($row)) { continue; }
                    if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                    $fieldId = !empty($row['field_id']) ? (int)$row['field_id'] : 0;
                    if ($fieldId <= 0 || !isset($fieldIdToAlias[$fieldId])) continue;
                    $alias = $fieldIdToAlias[$fieldId];
                    if ($alias === '') continue;
                    $type = isset($row['type']) ? (string) $row['type'] : 'text';
                    LogHelper::debug('ApiController.list: checking field alias=' . $alias . ' type=' . $type, 'radicalmart_telegram_catalog');
                    if ($type === 'range') {
                        $from = $app->input->getString('field_' . $alias . '_from', '');
                        $to   = $app->input->getString('field_' . $alias . '_to', '');
                        if ($from !== '' || $to !== '') { $fields[$alias] = ['from' => $from, 'to' => $to]; }
                    } else {
                        // Accept both field_alias and field[alias]; support multi (comma separated)
                        $val = $app->input->getString('field_' . $alias, null);
                        if ($val === null) {
                            $arr = $app->input->get('field', [], 'array');
                            if (isset($arr[$alias])) { $val = (string) $arr[$alias]; }
                        }
                        LogHelper::debug('ApiController.list: field_' . $alias . ' value=' . json_encode($val), 'radicalmart_telegram_catalog');
                        if ($val !== null && $val !== '') {
                            $parts = array_filter(array_map('trim', explode(',', (string)$val)), fn($x)=>$x!=='');
                            if (count($parts) > 1) { $fields[$alias] = $parts; }
                            else { $fields[$alias] = $parts ? $parts[0] : $val; }
                        }
                    }
                }
            }
            if (!empty($fields)) {
                $filters['fields'] = $fields;
                LogHelper::debug('ApiController.list: FINAL filters[fields]=' . json_encode($fields, JSON_UNESCAPED_UNICODE), 'radicalmart_telegram_catalog');
            }
        } catch (\Throwable $e) {
            LogHelper::error('ApiController.list: EXCEPTION in field filters: ' . $e->getMessage(), 'radicalmart_telegram_catalog');
        }

        // Финальное логирование собранных фильтров
        if ($debug) {
            LogHelper::debug('ApiController.list: page=' . $page . ' limit=' . $lim . ' filters=' . json_encode($filters, JSON_UNESCAPED_UNICODE), 'radicalmart_telegram_catalog');
        }

        $items = (new CatalogService())->listProducts($page, $lim, $filters);
        try {
            if (!empty($items)) {
                $metaCount = 0; $simpleCount = 0;
                foreach ($items as $it) { if (!empty($it['is_meta'])) $metaCount++; else $simpleCount++; }
                if (!empty($debug)) {
                    LogHelper::debug('ApiController.list: items total=' . count($items) . ' meta=' . $metaCount . ' simple=' . $simpleCount, 'radicalmart_telegram_catalog');
                    // Duplicate concise summary to common channel for visibility
                    LogHelper::debug('[catalog] list totals: total=' . count($items) . ', meta=' . $metaCount . ', simple=' . $simpleCount);
                }
            } else if (!empty($debug)) {
                LogHelper::info('ApiController.list: items empty', 'radicalmart_telegram_catalog');
                LogHelper::info('[catalog] list: items empty');
            }
        } catch (\Throwable $e) {}
        echo new JsonResponse(['items' => $items]);
        $app->close();
    }

    /**
     * Возвращает динамические опции фильтров (facets) на основе текущих фильтров и наличия товаров.
     * Формат ответа: { facets: { <alias>: [ { value, label, count } ] } }
     */
    public function facets(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('facets', 60);

        try {
            $inStock   = $app->input->getInt('in_stock', 0) === 1;
            $priceFrom = trim((string) $app->input->get('price_from', '', 'string'));
            $priceTo   = trim((string) $app->input->get('price_to', '', 'string'));

            // Собираем выбранные фильтры по полям
            $selectedFields = [];
            try {
                $params = $app->getParams('com_radicalmart_telegram');
                $cfg = $params->get('filters_fields');
                if (!empty($cfg) && is_array($cfg)) {
                    foreach ($cfg as $row) {
                        if (is_object($row)) { $row = get_object_vars($row); }
                        if (!is_array($row)) { continue; }
                        if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                        $alias = isset($row['alias']) ? trim((string)$row['alias']) : '';
                        if ($alias === '') continue;
                        $type = isset($row['type']) ? (string)$row['type'] : 'text';
                        if ($type === 'range') {
                            $from = $app->input->getString('field_' . $alias . '_from', '');
                            $to   = $app->input->getString('field_' . $alias . '_to', '');
                            if ($from !== '' || $to !== '') { $selectedFields[$alias] = ['from' => $from, 'to' => $to]; }
                        } else {
                            $val = $app->input->getString('field_' . $alias, null);
                            if ($val === null) {
                                $arr = $app->input->get('field', [], 'array');
                                if (isset($arr[$alias])) { $val = (string) $arr[$alias]; }
                            }
                            if ($val !== null && $val !== '') {
                                $parts = array_filter(array_map('trim', explode(',', (string)$val)), fn($x)=>$x!=='');
                                if (count($parts) > 1) { $selectedFields[$alias] = $parts; }
                                else { $selectedFields[$alias] = $parts ? $parts[0] : $val; }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Загружаем метаданные полей (alias, options)
            $db = Factory::getContainer()->get('DatabaseDriver');
            $params = $app->getParams('com_radicalmart_telegram');
            $cfg = (array) ($params->get('filters_fields') ?: []);
            $fieldIds = [];
            foreach ($cfg as $row) {
                if (is_object($row)) { $row = get_object_vars($row); }
                if (!is_array($row)) { continue; }
                if (!empty($row['enabled']) && (int)$row['enabled']===1 && !empty($row['field_id'])) $fieldIds[] = (int)$row['field_id'];
            }
            $fieldsMeta = [];
            if (!empty($fieldIds)) {
                $q = $db->getQuery(true)
                    ->select($db->quoteName(['id','title','alias','plugin','params','options']))
                    ->from($db->quoteName('#__radicalmart_fields'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('area') . ' = ' . $db->quote('products'))
                    ->whereIn($db->quoteName('id'), $fieldIds);
                $rows = (array) $db->setQuery($q)->loadObjectList();
                foreach ($rows as $r) {
                    $opts = [];
                    try {
                        $pp = json_decode((string)$r->params, true) ?: [];
                        if (isset($pp['options']) && is_array($pp['options'])) { $opts = $pp['options']; }
                        elseif (isset($pp['values']) && is_array($pp['values'])) { $opts = $pp['values']; }
                        elseif (isset($pp['choices']) && is_array($pp['choices'])) { $opts = $pp['choices']; }
                        elseif (isset($pp['variations']) && is_array($pp['variations'])) { $opts = $pp['variations']; }
                        $colOpts = json_decode((string)$r->options, true);
                        if (is_array($colOpts) && !empty($colOpts)) { $opts = $colOpts; }
                    } catch (\Throwable $e) {}
                    // Нормализуем опции к массиву [ [value=>..., label=>...] ]
                    $norm = [];
                    foreach ($opts as $k => $v) {
                        if (is_array($v)) {
                            $val = (string) ($v['value'] ?? $v['val'] ?? $v['id'] ?? $k);
                            $lab = (string) ($v['label'] ?? $v['text'] ?? $v['title'] ?? $val);
                        } elseif (is_object($v)) {
                            $val = (string) ($v->value ?? $v->val ?? $v->id ?? $k);
                            $lab = (string) ($v->label ?? $v->text ?? $v->title ?? $val);
                        } else {
                            $val = is_int($k) ? (string)$v : (string)$k; $lab = (string)$v;
                        }
                        if ($val !== '') { $norm[] = ['value' => $val, 'label' => $lab]; }
                    }
                    $fieldsMeta[(int)$r->id] = [ 'alias' => (string)$r->alias, 'title' => (string)$r->title, 'options' => $norm ];
                }
            }

            // Также учитываем напрямую присланные field_<alias> из запроса (если такие alias известны)
            if (!empty($fieldsMeta)) {
                foreach ($fieldsMeta as $meta) {
                    $a = $meta['alias'] ?? '';
                    if ($a === '') continue;
                    $v = $app->input->getString('field_' . $a, null);
                    if ($v !== null && $v !== '') {
                        $parts = array_filter(array_map('trim', explode(',', (string)$v)), fn($x)=>$x!=='');
                        if (count($parts) > 1) { $selectedFields[$a] = $parts; }
                        else { $selectedFields[$a] = $parts ? $parts[0] : $v; }
                    }
                }
            }

            // Построим базовые условия для выборки товаров
            $langTag = Factory::getApplication()->getLanguage()->getTag();
            $where = [];
            $binds = [];
            $where[] = 'p.state = 1';
            // Язык: текущий или *
            $where[] = 'p.language IN (' . $db->quote($langTag) . ', ' . $db->quote('*') . ')';
            // Всегда считаем фасеты только по товарам в наличии
            $where[] = 'p.in_stock = 1';

            // Фильтр по цене
            if ($priceFrom !== '' || $priceTo !== '') {
                $currency = \Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper::getCurrency(null);
                $group = $currency['group'];
                $priceExpr = 'CAST(JSON_VALUE(p.prices, ' . $db->quote('$."' . $group . '".final') . ') as double)';
                if ($priceFrom !== '') { $where[] = $priceExpr . ' >= :pf'; $binds[':pf'] = (float) $priceFrom; }
                if ($priceTo   !== '') { $where[] = $priceExpr . ' <= :pt'; $binds[':pt'] = (float) $priceTo; }
            }

            // Наложим выбранные фильтры по другим полям
            foreach ($selectedFields as $alias => $val) {
                $path = '$."' . $alias . '"';
                if (is_array($val)) {
                    // Диапазон или мульти-значения
                    if (isset($val['from']) || isset($val['to'])) {
                        if (isset($val['from']) && $val['from'] !== '') { $where[] = 'CAST(JSON_VALUE(p.fields, ' . $db->quote($path) . ') as double) >= :f_' . md5($alias . 'from'); $binds[':f_' . md5($alias . 'from')] = (float) $val['from']; }
                        if (isset($val['to'])   && $val['to']   !== '') { $where[] = 'CAST(JSON_VALUE(p.fields, ' . $db->quote($path) . ') as double) <= :t_' . md5($alias . 'to');   $binds[':t_' . md5($alias . 'to')]   = (float) $val['to']; }
                    } else {
                        $orParts = [];
                        foreach ($val as $mv) {
                            $mv = trim((string)$mv); if ($mv==='') continue;
                            $orParts[] = '('
                                . 'JSON_VALUE(p.fields, ' . $db->quote($path) . ') = ' . $db->quote($mv)
                                . ' OR JSON_CONTAINS(p.fields, ' . $db->quote('"' . $db->escape($mv, true) . '"') . ', ' . $db->quote($path) . ')
                            )';
                        }
                        if ($orParts) { $where[] = '(' . implode(' OR ', $orParts) . ')'; }
                    }
                } else {
                    $v = trim((string)$val); if ($v==='') continue;
                    $where[] = '('
                        . 'JSON_VALUE(p.fields, ' . $db->quote($path) . ') = :sv_' . md5($alias)
                        . ' OR JSON_CONTAINS(p.fields, :js_' . md5($alias) . ', ' . $db->quote($path) . ')
                    )';
                    $binds[':sv_' . md5($alias)] = $v;
                    $binds[':js_' . md5($alias)] = '"' . $db->escape($v, true) . '"';
                }
            }

            // Для каждого поля собираем counts по значениям из options
            $facets = [];
            foreach ($cfg as $row) {
                if (is_object($row)) { $row = get_object_vars($row); }
                if (!is_array($row)) { continue; }
                if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                $fid = (int) ($row['field_id'] ?? 0);
                if ($fid <= 0 || empty($fieldsMeta[$fid]['alias'])) continue;
                $alias = $fieldsMeta[$fid]['alias'];
                $options = $fieldsMeta[$fid]['options'] ?? [];
                if (empty($options)) { continue; }

                $list = [];
                foreach ($options as $op) {
                    $val = (string) ($op['value'] ?? '');
                    if ($val === '') continue;
                    $label = (string) ($op['label'] ?? $val);

                    // Строим COUNT(*) с учётом всех where и текущего значения поля
                    $q = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__radicalmart_products', 'p'));
                    if (!empty($where)) { $q->where(implode(' AND ', $where)); }
                    $path = '$."' . $alias . '"';
                    $cond = '('
                        . 'JSON_VALUE(p.fields, ' . $db->quote($path) . ') = :cv_' . md5($alias . $val)
                        . ' OR JSON_CONTAINS(p.fields, :cj_' . md5($alias . $val) . ', ' . $db->quote($path) . ')
                    )';
                    $q->where($cond);
                    // Привязки к запросу
                    foreach ($binds as $k => $bv) { $q->bind($k, $bv); }
                    $q->bind(':cv_' . md5($alias . $val), $val);
                    $jsonVal = '"' . $db->escape($val, true) . '"';
                    $q->bind(':cj_' . md5($alias . $val), $jsonVal);

                    $cnt = (int) $db->setQuery($q)->loadResult();
                    if ($cnt > 0) { $list[] = ['value' => $val, 'label' => $label, 'count' => $cnt]; }
                }
                $facets[$alias] = $list;
            }

            echo new JsonResponse(['facets' => $facets]);
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }
        $app->close();
    }

    public function add(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('add');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        $qty  = (float) $app->input->get('qty', 1, 'float');

        if ($chat <= 0 || $id <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }

        $svc = new CartService();
        $res = $svc->addProduct($chat, $id, $qty);
        if ($res === false) {
            echo new JsonResponse(null, 'Add failed', true);
            $app->close();
        }

        $cart = $res['cart'] ?? null;
        echo new JsonResponse(['cart' => $cart]);
        $app->close();
    }

    public function cart(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('cart', 60);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }

        $svc  = new CartService();
        $cart = $svc->getCart($chat);

        // Load checkout data from SessionStore (DB) and sync to Joomla session
        // This is needed because Joomla HTTP session doesn't persist across Telegram WebApp fetch() calls
        $checkoutSvc = new CheckoutService();
        $checkoutSvc->loadAndSyncCheckoutData($chat);

        // Применяем скидку промокода к корзине
        $promoInfo = $this->applyPromoToCart($cart);

        // Добавляем информацию о потенциальном кэшбэке
        $cashbackInfo = $this->calculateCartCashback($cart);

        // Проверяем привязан ли пользователь (для показа сообщения о кэшбэке гостям)
        $isLinked = false;
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . (int) $chat);
            $userId = (int) $db->setQuery($query)->loadResult();
            $isLinked = ($userId > 0);
        } catch (\Throwable $e) {}

        echo new JsonResponse([
            'cart' => $cart,
            'cashback' => $cashbackInfo,
            'is_linked' => $isLinked,
            'promo' => $promoInfo
        ]);
        $app->close();
    }

    /**
     * Apply promo code discount to cart object
     * Modifies cart totals and products based on applied promo from session
     * @param object|null $cart Cart object to modify
     * @return array Promo info: ['applied'=>bool, 'code'=>string, 'discount'=>float, 'discount_string'=>string]
     */
    protected function applyPromoToCart(&$cart): array
    {
        $result = [
            'applied' => false,
            'code' => '',
            'discount' => 0,
            'discount_type' => '',
            'discount_string' => ''
        ];

        if (!$cart || empty($cart->products) || empty($cart->total)) {
            return $result;
        }

        try {
            $app = Factory::getApplication();
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);

            // Get promo code - can be in 'code' (string) or 'plugins.bonuses.codes' (array of IDs)
            $appliedCodeString = $sessionData['code'] ?? '';
            $appliedCodeIds = $sessionData['plugins']['bonuses']['codes'] ?? [];

            // Determine which source to use
            $codeData = null;
            if (!empty($appliedCodeString) && class_exists(CodesHelper::class)) {
                // Try to find by string code first
                $codeData = CodesHelper::find($appliedCodeString);
            }

            if (!$codeData && !empty($appliedCodeIds) && is_array($appliedCodeIds)) {
                // Fallback: find by ID from array using getCodes()
                $firstCodeId = (int) reset($appliedCodeIds);
                if ($firstCodeId > 0 && class_exists(CodesHelper::class)) {
                    $codes = CodesHelper::getCodes([$firstCodeId]);
                    $codeData = $codes[$firstCodeId] ?? null;
                }
            }

            if (!$codeData || empty($codeData->discount)) {
                return $result;
            }

            // Parse discount value
            $discountRaw = $codeData->discount ?? '';
            $isPercent = (strpos($discountRaw, '%') !== false);
            $discountValue = (float) preg_replace('/[^0-9.]/', '', $discountRaw);

            if ($discountValue <= 0) {
                return $result;
            }

            // Get the code string for display
            $codeString = $codeData->code ?? $appliedCodeString;

            $result['applied'] = true;
            $result['code'] = $codeString;
            $result['discount_type'] = $isPercent ? 'percent' : 'fixed';

            // Calculate total discount
            $baseTotal = (float) ($cart->total['base'] ?? 0);
            $discountAmount = 0;

            if ($isPercent) {
                $discountAmount = $baseTotal * ($discountValue / 100);
                $result['discount_string'] = $discountValue . '%';
            } else {
                $discountAmount = min($discountValue, $baseTotal); // Don't exceed base total
                $result['discount_string'] = number_format($discountValue, 0, '', ' ') . ' ₽';
            }

            $discountAmount = round($discountAmount, 0);
            $result['discount'] = $discountAmount;

            // Format discount string for display
            $discountAmountString = number_format($discountAmount, 0, '', ' ') . ' ₽';

            // Update cart totals (final = base - discount, shipping is separate)
            // RadicalMart stores shipping separately, not in total.final
            $cart->total['discount'] = $discountAmount;
            $cart->total['discount_string'] = $discountAmountString;
            $finalAmount = max(0, $baseTotal - $discountAmount);
            $cart->total['final'] = $finalAmount;
            $cart->total['final_string'] = number_format($finalAmount, 0, '', ' ') . ' ₽';

            // Store promo info in cart plugins (format expected by frontend renderSummary)
            if (!isset($cart->plugins) || !is_array($cart->plugins)) {
                $cart->plugins = [];
            }
            $cart->plugins['bonuses'] = [
                'codes' => [(int) $codeData->id],
                'code_string' => $codeString,
                'discount' => $discountAmount,
                'codes_discount_string' => $discountAmountString  // Frontend expects this key
            ];

            LogHelper::debug('applyPromoToCart: code=' . $codeString . ' id=' . $codeData->id . ' discount=' . $discountAmount . ' base=' . $baseTotal . ' final=' . $cart->total['final']);

        } catch (\Throwable $e) {
            LogHelper::warning('applyPromoToCart error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Рассчитать потенциальный кэшбэк для корзины
     * Учитывает реферальные промокоды (если применён — кэшбэк не начисляется)
     * @param object|null $cart Объект корзины
     * @return array ['enabled'=>bool, 'total'=>int, 'has_referral'=>bool, 'percent'=>float]
     */
    protected function calculateCartCashback($cart): array
    {
        $result = [
            'enabled' => false,
            'total' => 0,
            'has_referral' => false,
            'percent' => 0,
            'message' => ''
        ];

        try {
            $config = CatalogService::getCashbackConfig();
            if (!$config['enabled']) {
                return $result;
            }

            $result['enabled'] = true;
            $result['percent'] = $config['percent'];

            if (!$cart || empty($cart->products)) {
                return $result;
            }

            // Проверяем наличие реферального промокода
            $hasReferral = false;

            // 1) Проверка в данных продуктов корзины
            foreach ($cart->products as $product) {
                if (!empty($product->order['plugins']['bonuses']['referral'])) {
                    $hasReferral = true;
                    break;
                }
            }

            // 2) Проверка применённого промокода из сессии
            if (!$hasReferral) {
                $app = Factory::getApplication();
                $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);

                // Get code - can be string or array of IDs
                $appliedCodeString = $sessionData['code'] ?? '';
                $appliedCodeIds = $sessionData['plugins']['bonuses']['codes'] ?? [];

                $codeData = null;
                if ($appliedCodeString !== '' && class_exists(CodesHelper::class)) {
                    $codeData = CodesHelper::find($appliedCodeString);
                }
                if (!$codeData && !empty($appliedCodeIds) && is_array($appliedCodeIds)) {
                    $firstCodeId = (int) reset($appliedCodeIds);
                    if ($firstCodeId > 0 && class_exists(CodesHelper::class)) {
                        $codes = CodesHelper::getCodes([$firstCodeId]);
                        $codeData = $codes[$firstCodeId] ?? null;
                    }
                }

                if ($codeData && !empty($codeData->referral) && (int) $codeData->referral === 1) {
                    $hasReferral = true;
                }
            }

            $result['has_referral'] = $hasReferral;

            if ($hasReferral) {
                // Если применён реферальный промокод — кэшбэк не начисляется
                $result['total'] = 0;
                $result['message'] = Text::_('COM_RADICALMART_TELEGRAM_CASHBACK_DISABLED_REFERRAL');
                return $result;
            }

            // Считаем общий кэшбэк
            $totalCashback = 0;
            foreach ($cart->products as $product) {
                $qty = (float) ($product->order['quantity'] ?? 1);
                $priceForCashback = 0;

                // Выбираем цену в зависимости от настройки (base или final)
                if ($config['from'] === 'base' && !empty($product->price['base'])) {
                    $priceForCashback = (float) $product->price['base'];
                } elseif (!empty($product->price['final'])) {
                    $priceForCashback = (float) $product->price['final'];
                }

                if ($priceForCashback > 0) {
                    $productCashback = CatalogService::calculateCashback($priceForCashback);
                    $totalCashback += $productCashback * $qty;
                }
            }

            $result['total'] = (int) $totalCashback;

        } catch (\Throwable $e) {
            LogHelper::warning('ApiController::calculateCartCashback error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Детальная карточка товара (product detail) для WebApp.
     * Параметры: id (int) - ID товара (обязательный)
     * Возвращает полную информацию о товаре включая fieldsets для графиков.
     */
    public function product(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('product', 60);

        $id = $app->input->getInt('id', 0);
        if ($id <= 0) {
            echo new JsonResponse(null, 'Product ID required', true);
            $app->close();
        }

        try {
            // Используем ProductModel для получения полной информации
            $model = new \Joomla\Component\RadicalMart\Site\Model\ProductModel();
            $model->setState('product.id', $id);
            $model->setState('filter.published', [1, 2]);
            $product = $model->getItem($id);

            if (empty($product) || empty($product->id)) {
                echo new JsonResponse(null, 'Product not found', true);
                $app->close();
            }

            // Формируем данные для WebApp
            $data = [
                'id' => (int) $product->id,
                'title' => (string) ($product->title ?? ''),
                'type' => (string) ($product->type ?? 'product'),
                'state' => (int) ($product->state ?? 0),
                'in_stock' => !empty($product->in_stock),
            ];

            // Изображения
            $data['image'] = '';
            if (!empty($product->image) && is_string($product->image)) {
                $data['image'] = $product->image;
            } elseif (!empty($product->media)) {
                try {
                    $media = is_string($product->media)
                        ? new \Joomla\Registry\Registry($product->media)
                        : new \Joomla\Registry\Registry((array) $product->media);
                    $data['image'] = (string) $media->get('image', '');
                } catch (\Throwable $e) {}
            }

            // Галерея
            $data['gallery'] = [];
            if (!empty($product->gallery) && is_array($product->gallery)) {
                foreach ($product->gallery as $g) {
                    if (is_object($g) && !empty($g->src)) {
                        $data['gallery'][] = (string) $g->src;
                    } elseif (is_array($g) && !empty($g['src'])) {
                        $data['gallery'][] = (string) $g['src'];
                    } elseif (is_string($g)) {
                        $data['gallery'][] = $g;
                    }
                }
            }

            // Категории
            $data['categories'] = [];
            if (!empty($product->categories)) {
                foreach ($product->categories as $cat) {
                    $data['categories'][] = [
                        'id' => (int) ($cat->id ?? 0),
                        'title' => (string) ($cat->title ?? ''),
                        'link' => (string) ($cat->link ?? ''),
                    ];
                }
            }

            // Категория
            if (!empty($product->category) && is_object($product->category)) {
                $data['category'] = [
                    'id' => (int) ($product->category->id ?? 0),
                    'title' => (string) ($product->category->title ?? ''),
                ];
            }

            // Производители
            $data['manufacturers'] = [];
            if (!empty($product->manufacturers)) {
                foreach ($product->manufacturers as $m) {
                    $data['manufacturers'][] = [
                        'id' => (int) ($m->id ?? 0),
                        'title' => (string) ($m->title ?? ''),
                        'link' => (string) ($m->link ?? ''),
                    ];
                }
            }

            // Цена
            if (!empty($product->price) && is_array($product->price)) {
                $data['price'] = [
                    'final' => (float) ($product->price['final'] ?? 0),
                    'final_string' => (string) ($product->price['final_string'] ?? ''),
                    'base' => (float) ($product->price['base'] ?? 0),
                    'base_string' => (string) ($product->price['base_string'] ?? ''),
                    'discount_enable' => !empty($product->price['discount_enable']),
                    'discount_string' => (string) ($product->price['discount_string'] ?? ''),
                ];
            }

            // Кэшбек
            $config = CatalogService::getCashbackConfig();
            $data['cashback'] = 0;
            $data['cashback_percent'] = $config['percent'] ?? 0;
            if ($config['enabled'] && !empty($product->price)) {
                $priceFor = $config['from'] === 'base'
                    ? (float) ($product->price['base'] ?? $product->price['final'] ?? 0)
                    : (float) ($product->price['final'] ?? 0);
                $data['cashback'] = CatalogService::calculateCashback($priceFor, $config['from'] !== 'base');
            }

            // Introtext и fulltext
            $data['introtext'] = (string) ($product->introtext ?? '');
            $data['fulltext'] = (string) ($product->fulltext ?? '');

            // Fieldsets с полями (для графиков)
            $data['fieldsets'] = [];
            if (!empty($product->fieldsets)) {
                foreach ($product->fieldsets as $fsAlias => $fieldset) {
                    if ($fieldset->alias === 'root') continue;
                    $fs = [
                        'alias' => (string) ($fieldset->alias ?? $fsAlias),
                        'title' => (string) ($fieldset->title ?? ''),
                        'fields' => [],
                    ];
                    if (!empty($fieldset->fields)) {
                        foreach ($fieldset->fields as $fAlias => $field) {
                            $fs['fields'][$fAlias] = [
                                'alias' => (string) ($field->alias ?? $fAlias),
                                'title' => (string) ($field->title ?? ''),
                                'value' => $field->value ?? null,
                                'rawvalue' => $field->rawvalue ?? null,
                            ];
                        }
                    }
                    $data['fieldsets'][$fsAlias] = $fs;
                }
            }

            // Badges
            $data['badges'] = [];
            if (!empty($product->badges)) {
                foreach ($product->badges as $badge) {
                    $data['badges'][] = [
                        'id' => (int) ($badge->id ?? 0),
                        'title' => (string) ($badge->title ?? ''),
                        'link' => (string) ($badge->link ?? ''),
                    ];
                }
            }

            // Variability (варианты для мета-товаров)
            $data['variability'] = null;
            if (!empty($product->type) && $product->type === 'variability') {
                try {
                    $variability = $model->getVariability();
                    if (!empty($variability) && !empty($variability->products)) {
                        $data['variability'] = [
                            'fields' => array_keys($variability->fields ?? []),
                            'products' => [],
                        ];
                        foreach ($variability->products as $vp) {
                            $data['variability']['products'][] = [
                                'id' => (int) ($vp->id ?? 0),
                                'title' => (string) ($vp->title ?? ''),
                                'link' => (string) ($vp->link ?? ''),
                                'fields' => $vp->fieldsVariability ?? [],
                            ];
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // Quantity
            if (!empty($product->quantity)) {
                $data['quantity'] = [
                    'min' => (int) ($product->quantity['min'] ?? 1),
                    'max' => (int) ($product->quantity['max'] ?? 0),
                    'step' => (int) ($product->quantity['step'] ?? 1),
                ];
            }

            echo new JsonResponse($data);
            $app->close();

        } catch (\Throwable $e) {
            LogHelper::warning('ApiController::product error: ' . $e->getMessage());
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Поиск товаров (быстрый search в WebApp)
     * Параметры: q (строка), limit (int)
     * Возвращает мета-товары с детьми (полные карточки как в каталоге)
     */
    public function search(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('search', 40);
        $q    = trim((string) $app->input->get('q', '', 'string'));
        $lim  = $app->input->getInt('limit', 12);
        $debug = $app->input->getInt('debug', 0) === 1;
        if ($lim <= 0 || $lim > 50) { $lim = 12; }
        if ($q === '') { echo new JsonResponse(['items'=>[]]); $app->close(); }
        try {
            // Используем listMetas с фильтром search для полных карточек
            $filters = ['search' => $q];
            if ($debug) { $filters['_search_debug'] = true; }
            $result = (new CatalogService())->listMetas(1, $lim, $filters);
            // Если debug и result массив с _debug
            if ($debug && is_array($result) && isset($result['_debug'])) {
                $items = $result['items'] ?? [];
                $response = ['items' => $items, '_debug' => $result['_debug']];
            } else {
                $items = is_array($result) && isset($result['items']) ? $result['items'] : (is_array($result) ? $result : []);
                $response = ['items' => $items];
            }
            if ($debug) {
                $response['_debug'] = array_merge($response['_debug'] ?? [], [
                    'query' => $q,
                    'query_len' => mb_strlen($q, 'UTF-8'),
                    'query_hex' => bin2hex($q),
                    'items_count' => count($items),
                ]);
            }
            echo new JsonResponse($response);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    /**
     * Профиль пользователя: данные аккаунта, баллы, реферальные коды, статистика.
     * optional action=createcode (POST): создать реферальный код.
     */
    public function profile(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('profile', 20);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        try {
            $svc = new ProfileService();
            $data = $svc->getProfile($chat);

            // Создание кода action=createcode
            $action = $app->input->getCmd('action', '');
            if ($action === 'createcode' && $data['can_create_code'] && $data['user']) {
                $this->guardRateLimitDb('profilecreate', 5);
                $this->guardNonce('createcode');
                $currency = $app->input->getString('currency', '');
                $custom = $app->input->getString('code', '');
                $createdCode = $svc->createReferralCode((int)$data['user']['id'], $currency, $custom);
                $data['created_code'] = $createdCode;
                // Refresh profile after creation
                $data = $svc->getProfile($chat);
                $data['created_code'] = $createdCode;
            }

            echo new JsonResponse($data);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    /**
     * Update profile (phone, name, etc.) via WebApp
     * Endpoint: task=api.updateprofile
     */
    public function updateprofile(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            // Read JSON body
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];

            $phone = trim($jsonData['phone'] ?? $app->input->getString('phone', ''));
            $firstName = trim($jsonData['first_name'] ?? '');
            $secondName = trim($jsonData['second_name'] ?? '');
            $lastName = trim($jsonData['last_name'] ?? '');
            $email = trim($jsonData['email'] ?? '');

            $db = Factory::getContainer()->get('DatabaseDriver');

            // Get current user_id from telegram_users
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

            // Update phone in telegram_users
            if (!empty($phone)) {
                $phone = RMUserHelper::cleanPhone($phone) ?: $phone;
                if (!preg_match('#^\+?7?\d{10,11}$#', $phone)) {
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FORMAT'), 400);
                }

                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__radicalmart_telegram_users'))
                    ->set($db->quoteName('phone') . ' = ' . $db->quote($phone))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chat);
                $db->setQuery($upd)->execute();

                // Try to link by phone if not already linked
                if ($userId <= 0) {
                    $found = RMUserHelper::findUser(['phone' => $phone]);
                    if ($found && $found->id) {
                        $userId = (int) $found->id;
                        $upd2 = $db->getQuery(true)
                            ->update($db->quoteName('#__radicalmart_telegram_users'))
                            ->set($db->quoteName('user_id') . ' = ' . (int) $userId)
                            ->where($db->quoteName('chat_id') . ' = :chat')
                            ->bind(':chat', $chat);
                        $db->setQuery($upd2)->execute();
                        LogHelper::debug('[updateprofile] Linked user ' . $userId . ' by phone for chat=' . $chat);
                    }
                }
            }

            // Update FIO in telegram_users (profile data separate from order data)
            if ($firstName || $secondName || $lastName) {
                $sets = [];
                if ($firstName) {
                    $sets[] = $db->quoteName('first_name') . ' = ' . $db->quote($firstName);
                }
                if ($secondName) {
                    $sets[] = $db->quoteName('second_name') . ' = ' . $db->quote($secondName);
                }
                if ($lastName) {
                    $sets[] = $db->quoteName('last_name') . ' = ' . $db->quote($lastName);
                }
                if (!empty($sets)) {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->where($db->quoteName('chat_id') . ' = :chat')
                        ->bind(':chat', $chat);
                    foreach ($sets as $set) {
                        $upd->set($set);
                    }
                    $db->setQuery($upd)->execute();
                    LogHelper::debug('[updateprofile] Updated FIO in telegram_users for chat=' . $chat);
                }
            }

            // Update email in telegram_users (with verified reset if changed)
            if (!empty($email)) {
                $updateResult = EmailVerificationHelper::updateEmail($chat, $email);
                if (!$updateResult['success']) {
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_' . $updateResult['error']), 400);
                }
                if ($updateResult['changed']) {
                    LogHelper::debug('[updateprofile] Email updated (verified reset) for chat=' . $chat);
                }
            }

            // Update RadicalMart user contacts if user is linked
            if ($userId > 0 && ($firstName || $secondName || $lastName || $phone)) {
                $query = $db->getQuery(true)
                    ->select('contacts')
                    ->from($db->quoteName('#__radicalmart_users'))
                    ->where($db->quoteName('user_id') . ' = ' . (int) $userId);
                $contactsJson = $db->setQuery($query, 0, 1)->loadResult();
                $contacts = $contactsJson ? json_decode($contactsJson, true) : [];

                // Update only non-empty fields
                if ($firstName) $contacts['first_name'] = $firstName;
                if ($secondName) $contacts['second_name'] = $secondName;
                if ($lastName) $contacts['last_name'] = $lastName;
                if ($phone) $contacts['phone'] = $phone;

                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__radicalmart_users'))
                    ->set($db->quoteName('contacts') . ' = ' . $db->quote(json_encode($contacts)))
                    ->where($db->quoteName('user_id') . ' = ' . (int) $userId);
                $db->setQuery($upd)->execute();

                LogHelper::debug('[updateprofile] Updated RM contacts for user=' . $userId);
            }

            // Update Joomla user email if needed
            if ($userId > 0 && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $jUser = Factory::getUser($userId);
                if ($jUser && !$jUser->guest && $jUser->email !== $email) {
                    // Check if email is already taken
                    $query = $db->getQuery(true)
                        ->select('id')
                        ->from($db->quoteName('#__users'))
                        ->where($db->quoteName('email') . ' = ' . $db->quote($email))
                        ->where($db->quoteName('id') . ' != ' . (int) $userId);
                    $existing = $db->setQuery($query, 0, 1)->loadResult();
                    if (!$existing) {
                        $jUser->email = $email;
                        $jUser->save();
                        LogHelper::debug('[updateprofile] Updated email for user=' . $userId);
                    }
                }
            }

            LogHelper::debug('[updateprofile] Profile updated for chat=' . $chat);

            // Return updated profile
            $svc = new ProfileService();
            $data = $svc->getProfile($chat);
            echo new JsonResponse($data);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Change user password with current password validation
     * Endpoint: task=api.changePassword
     */
    public function changePassword(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 10); // Stricter rate limit for password changes
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            // Read JSON body
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];

            $currentPassword = $jsonData['current_password'] ?? '';
            $newPassword = $jsonData['new_password'] ?? '';

            // Validation
            if (empty($currentPassword)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CURRENT_REQUIRED'), 400);
            }
            if (empty($newPassword)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NEW_REQUIRED'), 400);
            }
            if (strlen($newPassword) < 8) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_TOO_SHORT'), 400);
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            // Get user_id from telegram_users
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

            if ($userId <= 0) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NO_ACCOUNT'), 400);
            }

            // Get Joomla user
            $user = Factory::getUser($userId);
            if (!$user || $user->guest) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_NO_ACCOUNT'), 400);
            }

            // Verify current password
            $match = \Joomla\CMS\User\UserHelper::verifyPassword($currentPassword, $user->password, $userId);
            if (!$match) {
                LogHelper::warning('[changePassword] Invalid current password for user=' . $userId . ', chat=' . $chat);
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CURRENT_INVALID'), 400);
            }

            // Change password
            $user->password = \Joomla\CMS\User\UserHelper::hashPassword($newPassword);

            if (!$user->save()) {
                LogHelper::error('[changePassword] Failed to save password for user=' . $userId . ': ' . implode(', ', $user->getErrors()));
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_PASSWORD_CHANGE_ERROR'), 500);
            }

            LogHelper::info('[changePassword] Password changed for user=' . $userId . ', chat=' . $chat);

            echo new JsonResponse(['success' => true]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Update marketing consent via WebApp
     * Endpoint: task=api.updateconsent
     */
    public function updateconsent(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];
            $marketing = isset($jsonData['marketing']) ? (bool) $jsonData['marketing'] : null;

            if ($marketing !== null) {
                $db = Factory::getContainer()->get('DatabaseDriver');

                // Update marketing consent flag
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__radicalmart_telegram_users'))
                    ->set($db->quoteName('consent_marketing') . ' = ' . ($marketing ? 1 : 0))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chat);
                $db->setQuery($upd)->execute();

                LogHelper::debug('[updateconsent] Updated marketing consent for chat=' . $chat . ' marketing=' . ($marketing ? 'true' : 'false'));
            }

            echo new JsonResponse(['success' => true]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Delete user data via WebApp (GDPR right to be forgotten)
     * Endpoint: task=api.deletedata
     */
    public function deletedata(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 10);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];
            $confirm = isset($jsonData['confirm']) && $jsonData['confirm'] === true;

            if (!$confirm) {
                throw new \RuntimeException('Confirmation required', 400);
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            // Delete from telegram_users table
            $del = $db->getQuery(true)
                ->delete($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $db->setQuery($del)->execute();

            // Delete from telegram_sessions table
            $del2 = $db->getQuery(true)
                ->delete($db->quoteName('#__radicalmart_telegram_sessions'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $db->setQuery($del2)->execute();

            // Delete from telegram_links table
            $del3 = $db->getQuery(true)
                ->delete($db->quoteName('#__radicalmart_telegram_links'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $db->setQuery($del3)->execute();

            LogHelper::debug('[deletedata] Deleted all data for chat=' . $chat);

            echo new JsonResponse(['success' => true, 'deleted' => true]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Send email verification code
     * Endpoint: task=api.sendEmailCode
     */
    public function sendEmailCode(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];
            $email = trim($jsonData['email'] ?? '');

            if (empty($email)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_EMAIL_EMPTY'), 400);
            }

            // Validate format
            $validation = EmailVerificationHelper::validateFormat($email);
            if (!$validation['valid']) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_' . $validation['error']), 400);
            }

            // Check uniqueness
            $uniqueness = EmailVerificationHelper::checkUniqueness($email, $chat);
            if (!$uniqueness['unique']) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_' . $uniqueness['error']), 400);
            }

            // Check rate limit
            $rateCheck = EmailVerificationHelper::canRequestCode($chat);
            if (!$rateCheck['allowed']) {
                $error = $rateCheck['error'] === 'RATE_LIMIT'
                    ? Text::sprintf('COM_RADICALMART_TELEGRAM_EMAIL_RATE_LIMIT', $rateCheck['waitSeconds'])
                    : Text::sprintf('COM_RADICALMART_TELEGRAM_EMAIL_TOO_MANY_ATTEMPTS', (int) ceil($rateCheck['waitSeconds'] / 60));
                throw new \RuntimeException($error, 429);
            }

            // Generate and save code
            $code = EmailVerificationHelper::generateCode();
            if (!EmailVerificationHelper::saveCode($chat, $email, $code)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_DB'), 500);
            }

            // Send email with code and verification link
            if (!EmailVerificationHelper::sendVerificationEmail($email, $code, $chat)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_SEND'), 500);
            }

            LogHelper::debug('[sendEmailCode] Code sent to ' . $email . ' for chat=' . $chat);

            echo new JsonResponse([
                'success' => true,
                'email' => $email,
                'expiresMinutes' => EmailVerificationHelper::CODE_EXPIRES_MINUTES,
            ]);
            $app->close();
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 400;
            http_response_code($code >= 400 && $code < 600 ? $code : 400);
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Verify email code
     * Endpoint: task=api.verifyEmailCode
     */
    public function verifyEmailCode(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true) ?: [];
            $code = trim($jsonData['code'] ?? '');

            if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_EMAIL_CODE_INVALID_FORMAT'), 400);
            }

            // Verify code
            $result = EmailVerificationHelper::verifyCode($chat, $code);

            if (!$result['success']) {
                $errorKey = 'COM_RADICALMART_TELEGRAM_EMAIL_' . $result['error'];
                $message = $result['error'] === 'INVALID_CODE'
                    ? Text::sprintf($errorKey, $result['attemptsLeft'])
                    : Text::_($errorKey);
                throw new \RuntimeException($message, 400);
            }

            LogHelper::debug('[verifyEmailCode] Email verified for chat=' . $chat);

            // Get updated email data
            $emailData = EmailVerificationHelper::getEmailData($chat);

            echo new JsonResponse([
                'success' => true,
                'verified' => true,
                'email' => $emailData['email'] ?? null,
            ]);
            $app->close();
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 400;
            http_response_code($code >= 400 && $code < 600 ? $code : 400);
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Get email verification status
     * Endpoint: task=api.getEmailStatus
     */
    public function getEmailStatus(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('read', 60);
        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        try {
            $emailData = EmailVerificationHelper::getEmailData($chat);

            echo new JsonResponse([
                'email' => $emailData['email'] ?? null,
                'verified' => (bool) ($emailData['email_verified'] ?? false),
                'subscribed' => (bool) ($emailData['acymailing_subscribed'] ?? false),
            ]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Verify email via link click (no initData required)
     * Endpoint: task=api.verifyEmailLink&token=...
     * Redirects to a result page
     */
    public function verifyEmailLink(): void
    {
        $app = Factory::getApplication();
        $token = $app->input->getString('token', '');

        try {
            if (empty($token)) {
                throw new \RuntimeException('Missing token', 400);
            }

            // Verify by token
            $result = EmailVerificationHelper::verifyByToken($token);

            if (!$result['success']) {
                $errorKey = 'COM_RADICALMART_TELEGRAM_EMAIL_LINK_' . $result['error'];
                $message = Text::_($errorKey);
                // Fallback if key doesn't exist
                if ($message === $errorKey) {
                    $message = Text::_('COM_RADICALMART_TELEGRAM_EMAIL_' . $result['error']);
                }
                throw new \RuntimeException($message, 400);
            }

            $alreadyVerified = $result['alreadyVerified'] ?? false;
            $email = $result['email'] ?? '';

            LogHelper::info('[verifyEmailLink] Email verified via link for chat=' . $result['chatId'] . ', email=' . $email);

            // Redirect to success page or show success message
            $redirectUrl = \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_radicalmart_telegram&view=emailverified&status=success';
            if ($alreadyVerified) {
                $redirectUrl .= '&already=1';
            }
            $app->redirect($redirectUrl);
        } catch (\Throwable $e) {
            LogHelper::error('[verifyEmailLink] Error: ' . $e->getMessage());
            // Redirect to error page
            $errorCode = urlencode($e->getMessage());
            $redirectUrl = \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_radicalmart_telegram&view=emailverified&status=error&msg=' . $errorCode;
            $app->redirect($redirectUrl);
        }
    }

    public function qty(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('qty');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        $qty  = (float) $app->input->get('qty', 1, 'float');
        if ($chat <= 0 || $id <= 0 || $qty < 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }
        $svc = new CartService();
        $res = $svc->setQuantity($chat, $id, $qty);
        if ($res === false) { echo new JsonResponse(null, 'Update failed', true); $app->close(); }
        echo new JsonResponse(['cart' => $res['cart'] ?? null]);
        $app->close();
    }

    public function remove(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('remove');
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        if ($chat <= 0 || $id <= 0) {
            echo new JsonResponse(null, 'Invalid parameters', true);
            $app->close();
        }
        $svc = new CartService();
        $res = $svc->remove($chat, $id);
        if ($res === false) { echo new JsonResponse(null, 'Remove failed', true); $app->close(); }
        echo new JsonResponse(['cart' => $res['cart'] ?? null]);
        $app->close();
    }

    public function consents(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('consents', 20);
        try {
            $chat = $this->getChatId();
            $svc = new ProfileService();
            echo new JsonResponse($svc->getConsents($chat));
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function setconsent(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 60);
        $this->guardNonce('setconsent');
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, 'Invalid chat', true); $app->close(); }
        $type = trim((string) $app->input->get('type', '', 'string'));
        $val  = (int) $app->input->getInt('value', 0) === 1;
        try {
            $svc = new ProfileService();
            $ok = $svc->setConsent((int)$chat, $type, (bool)$val);
            if (!$ok) { echo new JsonResponse(null, 'Save failed', true); $app->close(); }
            echo new JsonResponse(['ok' => true]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function legal(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('legal', 30);
        $type = trim((string)$app->input->get('type', '', 'string'));
        try {
            $svc = new ProfileService();
            $html = $svc->getLegalDocument($type);
            echo new JsonResponse(['html' => $html]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true); $app->close();
        }
    }

    public function checkout(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('checkout', 20);
        $this->guardNonce('checkout');
        $chat = $this->getChatId();
        $op   = $app->input->getString('action', 'create');

        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid chat', true);
            $app->close();
        }

        if ($op !== 'create') {
            echo new JsonResponse(null, 'Unsupported action', true);
            $app->close();
        }

        // Backend consent enforcement: require personal_data and terms
        try {
            $cons = ConsentHelper::getConsents((int)$chat);
            if (empty($cons['personal_data']) || empty($cons['terms'])) {
                echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CONSENT_REQUIRED'), true);
                $app->close();
            }
        } catch (\Throwable $e) {
            // If consent check fails treat as missing (fail‑closed)
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CONSENT_REQUIRED'), true);
            $app->close();
        }

        // Load checkout data from SessionStore and sync to Joomla session
        // This ensures shipping/payment/promo data persists across API calls
        $checkoutSvc = new CheckoutService();
        $storedCheckoutData = $checkoutSvc->loadAndSyncCheckoutData($chat);
        LogHelper::debug('[checkout] Loaded stored checkout data for chat=' . $chat . ': ' . json_encode($storedCheckoutData));

        $first = trim($app->input->getString('first_name', ''));
        $second = trim($app->input->getString('second_name', '')); // отчество
        $last  = trim($app->input->getString('last_name', ''));
        $fallbackName = $app->input->getString('name', '');
        $phone = $app->input->getString('phone', '');
        $email = trim($app->input->getString('email', ''));
        $shippingId = $app->input->getInt('shipping_id', 0);
        $paymentId  = $app->input->getInt('payment_id', 0);

        // Use stored values if not provided in request
        if ($shippingId <= 0 && !empty($storedCheckoutData['shipping']['id'])) {
            $shippingId = (int) $storedCheckoutData['shipping']['id'];
        }
        if ($paymentId <= 0 && !empty($storedCheckoutData['payment']['id'])) {
            $paymentId = (int) $storedCheckoutData['payment']['id'];
        }

        try {
            // Ensure cart exists
            $cartSvc = new CartService();
            $cart    = $cartSvc->getCart($chat);
            if (!$cart || empty($cart->id)) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), 400);
            }

            // Basic validation
            $phone = RMUserHelper::cleanPhone($phone) ?: $phone;
            if (empty($phone)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_REQUIRED'), 400); }
            if (!preg_match('#^\+?7?\d{10,11}$#', $phone)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FORMAT'), 400); }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_EMAIL_FORMAT'), 400); }
            if (empty($first) || empty($last)) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_NAME_REQUIRED'), 400); }

            // Server-side validation: ApiShip shipping methods require PVZ selection
            // ApiShip shipping IDs: 4 (CDEK), 6 (yataxi), 7 (5Post)
            $apishipShippingIds = [4, 6, 7];
            if (in_array($shippingId, $apishipShippingIds, true)) {
                // Check that PVZ point is selected
                $pointId = $storedCheckoutData['shipping']['point']['id'] ?? null;
                if (empty($pointId)) {
                    LogHelper::warning('[checkout] VALIDATION FAILED: ApiShip shipping=' . $shippingId . ' requires PVZ selection, but point.id is empty');
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PVZ_REQUIRED'), 400);
                }

                // Check that tariff is selected
                $tariffId = $storedCheckoutData['shipping']['tariff']['id'] ?? null;
                if (empty($tariffId)) {
                    LogHelper::warning('[checkout] VALIDATION FAILED: ApiShip shipping=' . $shippingId . ' requires tariff selection, but tariff.id is empty');
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_TARIFF_REQUIRED'), 400);
                }

                // Check that shipping price is calculated
                $priceBase = $storedCheckoutData['shipping']['price']['base'] ?? 0;
                if ((int)$priceBase <= 0) {
                    LogHelper::debug('[checkout] WARNING: ApiShip shipping=' . $shippingId . ' has price.base=' . $priceBase . ' (may be zero for free shipping)');
                }
            }

            // Resolve or create user
            $db = Factory::getContainer()->get('DatabaseDriver');
            $userId = 0;

            // Check if user already exists by chat_id
            $query = $db->getQuery(true)
                ->select(['user_id', 'phone', 'email', 'email_verified', 'tg_first_name', 'tg_last_name'])
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $existingRecord = $db->setQuery($query, 0, 1)->loadAssoc();
            $userId = (int) ($existingRecord['user_id'] ?? 0);
            $existingPhone = (string) ($existingRecord['phone'] ?? '');

            // Priority for verified email from telegram_users
            $verifiedEmail = '';
            if (!empty($existingRecord['email']) && !empty($existingRecord['email_verified'])) {
                $verifiedEmail = (string) $existingRecord['email'];
                LogHelper::debug('[checkout] Using verified email from telegram_users: ' . $verifiedEmail);
            }
            // Use verified email if form email is empty, or prioritize verified email
            if (!empty($verifiedEmail) && (empty($email) || $email !== $verifiedEmail)) {
                $email = $verifiedEmail;
            }

            if ($userId <= 0 && !empty($phone)) {
                $found = RMUserHelper::findUser(['phone' => $phone]);
                if ($found && $found->id) {
                    $userId = (int) $found->id;
                }
            }

            // Update phone in telegram_users table if it's missing or different
            if (!empty($phone) && $existingRecord !== null && (empty($existingPhone) || $existingPhone !== $phone)) {
                try {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('phone') . ' = ' . $db->quote($phone))
                        ->where($db->quoteName('chat_id') . ' = :chat')
                        ->bind(':chat', $chat);
                    if ($userId > 0 && (int)($existingRecord['user_id'] ?? 0) <= 0) {
                        $upd->set($db->quoteName('user_id') . ' = ' . (int) $userId);
                    }
                    $db->setQuery($upd)->execute();
                    LogHelper::debug('[checkout] Updated phone in telegram_users for chat=' . $chat . ' phone=' . $phone);
                } catch (\Throwable $e) {
                    LogHelper::warning('[checkout] Failed to update phone: ' . $e->getMessage());
                }
            }

            if ($userId <= 0) {
                // Create new user from provided contacts (минимально необходимые поля)
                $contacts = [];
                if (!empty($first)) { $contacts['first_name'] = $first; }
                if (!empty($second)) { $contacts['second_name'] = $second; }
                if (!empty($last)) { $contacts['last_name'] = $last; }
                if (!empty($phone)) { $contacts['phone'] = $phone; }
                if (!empty($email)) { $contacts['email'] = $email; }

                $res = RMUserHelper::saveData('com_radicalmart.checkout', 0, $contacts, false);
                if (!$res || empty($res['result']) || empty($res['user']) || empty($res['user']->id)) {
                    throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_CREATE_USER'), 500);
                }
                $userId = (int) $res['user']->id;

                // Map Telegram chat to user
                $link = (object) [
                    'chat_id' => $chat,
                    'tg_user_id' => $this->tgUserId ?: null,
                    'user_id' => $userId,
                    'username' => $this->tgUsername ?: '',
                    'phone' => $phone,
                    'locale' => 'ru',
                    'consent_notifications' => 0,
                    'consent_personal' => 0,
                    'created' => (new \Joomla\CMS\Date\Date())->toSql(),
                ];
                try {
                    $db->insertObject('#__radicalmart_telegram_users', $link);
                } catch (\Throwable $e) {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__radicalmart_telegram_users'))
                        ->set($db->quoteName('user_id') . ' = :uid')
                        ->set($db->quoteName('phone') . ' = :ph')
                        ->set($db->quoteName('tg_user_id') . ' = :tg')
                        ->set($db->quoteName('username') . ' = :un')
                        ->where($db->quoteName('chat_id') . ' = :chat')
                        ->bind(':uid', $userId)
                        ->bind(':ph', $phone)
                        ->bind(':tg', $this->tgUserId)
                        ->bind(':un', $this->tgUsername)
                        ->bind(':chat', $chat);
                    $db->setQuery($upd)->execute();
                }
            }

            if ($userId <= 0) {
                throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PHONE_FOR_REG'), 400);
            }

            // Update Joomla user email if current is fake and we have verified email
            if (!empty($verifiedEmail) && $userId > 0) {
                try {
                    $userQuery = $db->getQuery(true)
                        ->select('email')
                        ->from($db->quoteName('#__users'))
                        ->where($db->quoteName('id') . ' = :uid')
                        ->bind(':uid', $userId, \Joomla\Database\ParameterType::INTEGER);
                    $currentJoomlaEmail = $db->setQuery($userQuery)->loadResult();

                    // Check if email is fake (contains @fake. or @telegram.fake etc.)
                    if ($currentJoomlaEmail && (
                        stripos($currentJoomlaEmail, '@fake.') !== false ||
                        stripos($currentJoomlaEmail, '.fake') !== false ||
                        stripos($currentJoomlaEmail, '@telegram.') !== false
                    )) {
                        $updateEmail = $db->getQuery(true)
                            ->update($db->quoteName('#__users'))
                            ->set($db->quoteName('email') . ' = :email')
                            ->where($db->quoteName('id') . ' = :uid')
                            ->bind(':email', $verifiedEmail)
                            ->bind(':uid', $userId, \Joomla\Database\ParameterType::INTEGER);
                        $db->setQuery($updateEmail)->execute();
                        LogHelper::info('[checkout] Updated Joomla user ' . $userId . ' email from fake to verified: ' . $verifiedEmail);
                    }
                } catch (\Throwable $e) {
                    LogHelper::warning('[checkout] Failed to update Joomla user email: ' . $e->getMessage());
                }
            }

            // Persist chosen shipping/payment in session state for RadicalMart checkout
            // Validate selected shipping/payment via available methods
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            LogHelper::debug('[checkout] Session data BEFORE merge: ' . json_encode([
                'shipping_id' => $sessionData['shipping']['id'] ?? 'none',
                'shipping_tariff_id' => $sessionData['shipping']['tariff']['id'] ?? 'none',
                'shipping_price_base' => $sessionData['shipping']['price']['base'] ?? 'none',
                'plugins_bonuses' => $sessionData['plugins']['bonuses'] ?? 'none',
            ]));

            // CRITICAL FIX: Merge storedCheckoutData BEFORE calling getItem()!
            // Otherwise getItem() reads old session data without shipping.price
            // and onRadicalMartGetOrderShipping event won't have the preset price
            if (!empty($storedCheckoutData['shipping'])) {
                $sessionData['shipping'] = array_replace_recursive(
                    $sessionData['shipping'] ?? [],
                    $storedCheckoutData['shipping']
                );
            }

            // Set specific shipping/payment IDs from request (override stored values if provided)
            if ($shippingId > 0) {
                $sessionData['shipping']['id'] = $shippingId;
            }
            if ($paymentId > 0) {
                $sessionData['payment']['id'] = $paymentId;
            }

            LogHelper::debug('[checkout] Session data AFTER merge (before setUserState): ' . json_encode([
                'shipping_id' => $sessionData['shipping']['id'] ?? 'none',
                'shipping_tariff_id' => $sessionData['shipping']['tariff']['id'] ?? 'none',
                'shipping_price_base' => $sessionData['shipping']['price']['base'] ?? 'none',
                'plugins_bonuses' => $sessionData['plugins']['bonuses'] ?? 'none',
            ]));

            // Save merged session BEFORE calling getItem() for validation
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            $model = new CheckoutModel();
            // CRITICAL: Force populateState() first to prevent cart.id/code being overwritten
            $model->getState();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            // Validate shipping/payment methods are available
            if ($shippingId > 0) {
                $allowed = [];
                if (!empty($item->shippingMethods)) { foreach ($item->shippingMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$shippingId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_SHIPPING_UNAVAILABLE'), 400); }
            }
            if ($paymentId > 0) {
                $allowed = [];
                if (!empty($item->paymentMethods)) { foreach ($item->paymentMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$paymentId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PAYMENT_UNAVAILABLE'), 400); }
            }

            LogHelper::debug('[checkout] Session data verified, shipping total from getItem: ' . json_encode([
                'shipping_order_price_base' => $item->shipping->order->price['base'] ?? 'none',
                'total_base' => $item->total['base'] ?? 'none',
                'total_final' => $item->total['final'] ?? 'none',
            ]));

            // Verify session was set correctly
            $verifyData = $app->getUserState('com_radicalmart.checkout.data', []);
            LogHelper::debug('[checkout] Session data AFTER setUserState: ' . json_encode([
                'shipping_id' => $verifyData['shipping']['id'] ?? 'none',
                'shipping_tariff_id' => $verifyData['shipping']['tariff']['id'] ?? 'none',
                'shipping_price_base' => $verifyData['shipping']['price']['base'] ?? 'none',
                'plugins_bonuses' => $verifyData['plugins']['bonuses'] ?? 'none',
            ]));

            // Create order via CheckoutModel
            // Re-init model after session update
            $model = new CheckoutModel();
            // CRITICAL: Force populateState() to run FIRST by calling getState()
            // Otherwise setState() values will be overwritten when getState() is called later
            // This ensures cart.id and cart.code are correctly set for order creation
            $model->getState();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);

            // Build orderData with shipping, payment, and plugins (promo codes)
            // RadicalMart expects these in $data for createOrder
            // CRITICAL: currency must be set explicitly for discount calculations to work
            $orderData = [
                'created_by' => $userId,
                'currency' => (!empty($item->currency['code'])) ? $item->currency['code'] : 'RUB',
                'contacts' => [
                    'first_name' => ($first ?: ($fallbackName ?: '')),
                    'second_name' => $second,
                    'last_name' => $last,
                    'phone' => $phone,
                    'email' => $email,
                ],
                'shipping' => $sessionData['shipping'] ?? [],
                'payment' => $sessionData['payment'] ?? [],
            ];

            // Include plugins (promo codes/bonuses) from session data
            // This ensures promo codes are saved with the order
            if (!empty($sessionData['plugins'])) {
                $orderData['plugins'] = $sessionData['plugins'];
                LogHelper::debug('[checkout] Including plugins in orderData: ' . json_encode($sessionData['plugins']));
            }

            LogHelper::debug('[checkout] Creating order with data: ' . json_encode([
                'created_by' => $orderData['created_by'],
                'currency' => $orderData['currency'] ?? 'NOT SET',
                'shipping_id' => $orderData['shipping']['id'] ?? 'none',
                'payment_id' => $orderData['payment']['id'] ?? 'none',
                'plugins' => $orderData['plugins'] ?? 'none',
            ]));

            // CRITICAL: Call getItem() BEFORE createOrder() to trigger discount calculation
            // This fires onRadicalMartGetOrderProducts event with context=com_radicalmart.checkout
            // which allows Bonuses plugin to apply promo code discounts to products
            LogHelper::debug('[checkout] Calling getItem() to trigger discount calculation...');
            $checkoutItem = $model->getItem();
            if (!$checkoutItem) {
                throw new \RuntimeException('Failed to prepare checkout item for discount calculation', 500);
            }
            LogHelper::debug('[checkout] getItem() completed. Pre-order totals: base=' . ($checkoutItem->total['base'] ?? 'null') . ', final=' . ($checkoutItem->total['final'] ?? 'null'));

            if (!$order = $model->createOrder($orderData)) {
                $errors = $model->getErrors();
                $msg = $errors ? (is_array($errors) ? implode("\n", array_map(fn($e)=> ($e instanceof \Exception)?$e->getMessage():$e, $errors)) : (string) $errors) : 'Ошибка оформления заказа';
                throw new \RuntimeException($msg, 500);
            }

            // Log order data after initial creation
            LogHelper::debug('[checkout] Order created (initial): id=' . ($order->id ?? 'null') . ', number=' . ($order->number ?? 'null') . ', total_base=' . ($order->total['base'] ?? 'null') . ', total_final=' . ($order->total['final'] ?? 'null') . ', shipping_price_base=' . ($order->shipping->order->price['base'] ?? 'null'));

            // CRITICAL FIX: Re-save order with recalculate_price=1 to force shipping price recalculation
            // This triggers onRadicalMartGetOrderShipping with recalculate_price flag, ensuring
            // the shipping price is properly added to total before payment
            $orderId = (int) ($order->id ?? 0);
            if ($orderId > 0) {
                try {
                    $orderModel = new \Joomla\Component\RadicalMart\Administrator\Model\OrderModel();
                    $orderModel->setState('order.id', $orderId);

                    // Build resave data with recalculate flag
                    $resaveData = [
                        'id' => $orderId,
                        'shipping' => array_merge(
                            $sessionData['shipping'] ?? [],
                            ['recalculate_price' => 1]
                        ),
                    ];

                    LogHelper::debug('[checkout] Re-saving order ' . $orderId . ' with recalculate_price=1');

                    if ($orderModel->save($resaveData)) {
                        // Reload order to get updated totals
                        $order = $orderModel->getItem($orderId);
                        LogHelper::debug('[checkout] Order re-saved: total_base=' . ($order->total['base'] ?? 'null') . ', total_final=' . ($order->total['final'] ?? 'null') . ', shipping_price_base=' . ($order->shipping->order->price['base'] ?? 'null'));
                    } else {
                        $errors = $orderModel->getErrors();
                        LogHelper::warning('[checkout] Order re-save failed: ' . json_encode($errors));
                    }
                } catch (\Throwable $e) {
                    LogHelper::warning('[checkout] Order re-save exception: ' . $e->getMessage());
                }
            }

            $number  = $order->number ?? null;
            $orderId = (int) ($order->id ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Заказ создан, но не получен идентификатор', 500);
            }
            if (empty($number)) {
                throw new \RuntimeException('Заказ создан, но не получен номер заказа', 500);
            }

            // Generate payment URL using RadicalMart SEF format
            $rmParams = \Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper::getComponentParams();
            $paymentEntry = $rmParams->get('payment_entry', 'radicalmart_payment');
            $payUrl = rtrim(Uri::root(), '/') . '/' . $paymentEntry . '/pay/' . urlencode((string) $number);

            // Optionally, send Telegram Payment invoice (cards) if enabled and selected payment is telegram*
            try {
                $params = $app->getParams('com_radicalmart_telegram');
                $cardsEnabled = (int) $params->get('payments_telegram_cards', 1) === 1;
                $provider = (string) $params->get('provider_cards', 'yookassa');
                $env      = (string) $params->get('payments_env', 'test');
                $ptoken   = '';
                if ($provider === 'yookassa') {
                    $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
                } else {
                    $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
                }
                if ($cardsEnabled && $ptoken !== '') {
                    // Use the created order directly - cart is already deleted after createOrder()
                    // $order contains full order data including total with shipping
                    $paymentPlugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
                    $isTelegramPayment = ($paymentPlugin !== '' && stripos($paymentPlugin, 'telegram') !== false);
                    if (!$isTelegramPayment) { throw new \RuntimeException('Skip invoice: payment not telegram'); }
                    $currency = (!empty($order->currency['code'])) ? (string) $order->currency['code'] : 'RUB';
                    $amountStr = (!empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';
                    $amountMinor = 0;
                    if (!empty($order->total['final'])) {
                        $amountMinor = (int) round(((float) $order->total['final']) * 100);
                    } elseif ($amountStr !== '') {
                        $num = preg_replace('#[^0-9\.,]#', '', $amountStr);
                        $num = str_replace(' ', '', $num);
                        $num = str_replace(',', '.', $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }
                    LogHelper::debug('[checkout] Telegram Payment invoice: total_final=' . ($order->total['final'] ?? 'null') . ', shipping_price_base=' . ($order->shipping->order->price['base'] ?? 'null') . ', amountMinor=' . $amountMinor);
                    if ($amountMinor > 0) {
                        $title = 'Заказ ' . ($number ?: ('#' . $orderId));
                        $desc  = 'Оплата заказа в магазине';
                        $payload = 'order:' . (string) $number;
                        $tg = new TelegramClient();
                        if ($tg->isConfigured()) {
                            $tg->sendInvoice((int) $chat, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Send chat message with payment link (optional)
            try {
                $tg = new TelegramClient();
                if ($tg->isConfigured()) {
                    $message = 'Заказ ' . ($number ?: ('#' . $orderId)) . " создан.\nПерейдите к оплате по ссылке.";
                    $tg->sendMessage((int) $chat, $message, [
                        'reply_markup' => [
                            'inline_keyboard' => [[
                                [ 'text' => 'Оплатить заказ', 'url' => $payUrl ],
                            ]],
                        ],
                    ]);
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Clear checkout data from SessionStore after successful order creation
            try {
                $checkoutSvc->clearCheckoutData($chat);
                LogHelper::debug('[checkout] Cleared checkout data for chat=' . $chat . ' after order ' . $number);
            } catch (\Throwable $e) { /* ignore */ }

            echo new JsonResponse([
                'order_id' => $orderId,
                'order_number' => $number,
                'pay_url' => $payUrl,
            ]);
            $app->close();
        }
        catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function methods(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('methods', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc = new CheckoutService();
            $res = $svc->getMethods($chat);
            echo new JsonResponse($res);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function setpvz(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $this->guardNonce('setpvz');
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        $shippingId = $app->input->getInt('shipping_id', 0);
        $tariffId   = $app->input->getString('tariff_id', '');
        $pvzData = [
            'id'       => $app->input->getString('id', ''),
            'provider' => $app->input->getString('provider', ''),
            'title'    => $app->input->getString('title', ''),
            'address'  => $app->input->getString('address', ''),
            'lat'      => (float) $app->input->get('lat', 0, 'float'),
            'lon'      => (float) $app->input->get('lon', 0, 'float'),
        ];

        LogHelper::debug("[setpvz] INPUT: shippingId=$shippingId, provider={$pvzData['provider']}, extId={$pvzData['id']}, tariffId=$tariffId, chat=$chat");

        try {
            $svc = new CheckoutService();
            $result = $svc->setPvz($chat, $pvzData, $shippingId, $tariffId);
            echo new JsonResponse($result);
            $app->close();
        } catch (\Throwable $e) {
            LogHelper::error("[setpvz] ERROR: " . $e->getMessage());
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Set payment method in session
     */
    public function setpayment(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('mut', 30);
        $this->guardNonce('setpayment');

        $paymentId = $app->input->getInt('id', 0);

        if ($paymentId <= 0) {
            echo new JsonResponse(null, 'Invalid payment ID', true);
            $app->close();
        }

        try {
            $chat = $this->getChatId();
            $svc = new CheckoutService();
            $res = $svc->setPayment($chat, $paymentId);
            echo new JsonResponse(['success' => true, 'payment_id' => $res['payment_id']]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }


    public function pvz(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('pvz', 20);
        $bbox = $app->input->getString('bbox', '');
        $prov = $app->input->getString('providers', '');
        $limit = $app->input->getInt('limit', 1000);

        try {
            $svc = new PvzService();
            $items = $svc->getPvzList($bbox, $prov, $limit);
            echo new JsonResponse(['items' => $items]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function orders(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('orders', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $page = max(1, (int) $app->input->getInt('page', 1));
            $limit = min(50, max(1, (int) $app->input->getInt('limit', 10)));
            $statusRaw = trim((string) $app->input->get('status', '', 'string'));
            $status = ($statusRaw !== '' && ctype_digit($statusRaw)) ? (int) $statusRaw : null;

            $svc = new OrderService();
            $result = $svc->getOrders($chat, $page, $limit, $status);
            echo new JsonResponse($result);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function invoice(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('invoice', 10);
        $this->guardNonce('invoice');
        $chat = $this->getChatId();
        $number = trim((string) $app->input->getString('number', ''));
        if ($chat <= 0 || $number === '') { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc = new OrderService();
            $result = $svc->sendInvoice($chat, $number);
            echo new JsonResponse($result);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    /**
     * Batch tariff calculation for multiple PVZ points
     * POST api.tariffs with pvz_ids=[id1,id2,...] (max 20)
     * Returns { results: { pvz_id: { min_price, tariffs: [...] } | null } }
     */
    public function tariffs(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('tariffs', 10);

        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        $pvzIdsRaw = $app->input->getString('pvz_ids', '');
        $shippingId = $app->input->getInt('shipping_id', 0);

        try {
            $pvzIds = array_filter(array_map('trim', explode(',', $pvzIdsRaw)));
            if (empty($pvzIds)) {
                echo new JsonResponse(['results' => []]);
                $app->close();
            }

            $svc = new CheckoutService();
            $result = $svc->getTariffsBatch($chat, $pvzIds, $shippingId);

            // Handle inactive marking via PvzService
            if (!empty($result['inactive_to_mark'])) {
                $pvzSvc = new PvzService();
                foreach ($result['inactive_to_mark'] as $item) {
                    $pvzSvc->incrementInactiveCount($item['ext_id'], $item['provider'], $chat);
                }
            }

            echo new JsonResponse(['results' => $result['results']]);
            $app->close();
        } catch (\Throwable $e) {
            LogHelper::error("[tariffs] Exception: " . $e->getMessage());
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Mark PVZ as inactive (no tariffs available)
     * Increments inactive_count; if >= 10, point becomes permanently hidden
     */
    public function markpvz(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('markpvz', 30);

        $chat = $this->getChatId();
        if ($chat <= 0) {
            echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true);
            $app->close();
        }

        $extId = $app->input->getString('ext_id', '');
        $provider = $app->input->getString('provider', '');
        $active = $app->input->getInt('active', 0);

        if (empty($extId) || empty($provider)) {
            echo new JsonResponse(null, 'Missing ext_id or provider', true);
            $app->close();
        }

        try {
            $svc = new PvzService();
            if ($active === 1) {
                $svc->resetInactiveCount($extId, $provider);
            } else {
                $svc->incrementInactiveCount($extId, $provider, $chat);
            }
            echo new JsonResponse(['ok' => true]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    /**
     * Apply bonus points to cart/order
     * Called via AJAX from checkout: task=checkout.applyPoints
     */
    public function applyPoints(): void
    {
        $app = Factory::getApplication();

        try {
            $this->guardInitData();

            $points = $app->input->getInt('points', 0);
            $chatId = $app->input->getInt('chat', 0);

            // Get user from TelegramUserHelper
            $tgUser = \Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper::getCurrentUser();
            $userId = $tgUser['user_id'] ?? 0;

            if ($userId <= 0) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_BONUSES_LOGIN_HINT')]);
                $app->close();
                return;
            }

            // In RadicalMart, customer_id equals user_id directly
            // The #__radicalmart_customers table has 'id' column which matches user_id
            $customerId = (int) $userId;

            if ($customerId <= 0) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_ERROR_CUSTOMER_NOT_FOUND')]);
                $app->close();
                return;
            }

            // Validate points
            if (class_exists(PointsHelper::class)) {
                $availablePoints = (float) PointsHelper::getCustomerPoints($customerId);
                $points = min($points, (int) $availablePoints);
                $points = max(0, $points);
            } else {
                $points = 0;
            }

            // Store points in RadicalMart session (com_radicalmart.checkout.data)
            // This is where RadicalMart Bonuses plugin expects them
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if (!isset($sessionData['plugins'])) {
                $sessionData['plugins'] = [];
            }
            if (!isset($sessionData['plugins']['bonuses'])) {
                $sessionData['plugins']['bonuses'] = [];
            }
            $sessionData['plugins']['bonuses']['points'] = $points;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Calculate money equivalent
            $moneyEquivalent = 0;
            if ($points > 0 && class_exists(PointsHelper::class)) {
                $moneyEquivalent = PointsHelper::convertToMoney($points, 'RUB');
            }

            $message = $points > 0
                ? Text::sprintf('COM_RADICALMART_TELEGRAM_POINTS_APPLIED') . ': ' . number_format($points, 0, ',', ' ') . ' ' . Text::_('COM_RADICALMART_TELEGRAM_POINTS_UNIT') . ' (= ' . number_format($moneyEquivalent, 0, ',', ' ') . ' ₽)'
                : Text::_('COM_RADICALMART_TELEGRAM_POINTS_CLEARED');

            echo new JsonResponse([
                'success' => true,
                'message' => $message,
                'points' => $points,
                'moneyEquivalent' => $moneyEquivalent
            ]);

        } catch (\Throwable $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    /**
     * Apply promo code to cart/order
     * Called via AJAX from checkout: task=api.applyPromo
     */
    public function applyPromo(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('promo', 20);
        $this->guardNonce('applyPromo');
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        $code = trim($app->input->getString('code', ''));
        if (empty($code)) {
            echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_ERR_PROMO_REQUIRED')]);
            $app->close();
        }

        try {
            $svc = new BonusesService();
            $result = $svc->applyPromo($chat, $code);
            echo new JsonResponse([
                'success' => true,
                'message' => Text::_('COM_RADICALMART_TELEGRAM_PROMO_APPLIED'),
                'code' => $result['code'],
                'discount' => $result['discount'] ?? '',
                'discount_string' => $result['discount_string'] ?? ''
            ]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
            $app->close();
        }
    }

    /**
     * Remove promo code from session
     * Called via AJAX from checkout: task=api.removePromo
     */
    public function removePromo(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('promo', 20);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }

        try {
            $svc = new BonusesService();
            $svc->removePromo($chat);
            echo new JsonResponse([
                'success' => true,
                'message' => Text::_('COM_RADICALMART_TELEGRAM_PROMO_REMOVED')
            ]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
            $app->close();
        }
    }

    /**
     * Create a new referral code for the user
     * Called via AJAX from referrals page: task=api.createReferralCode
     */
    public function createReferralCode(): void
    {
        $app = Factory::getApplication();

        try {
            $this->guardInitData();

            $customCode = trim($app->input->getString('code', ''));
            $chatId = $app->input->getInt('chat', 0);

            // Get user
            $tgUser = \Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper::getCurrentUser();
            $userId = $tgUser['user_id'] ?? 0;

            if ($userId <= 0) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_LOGIN_REQUIRED')]);
                $app->close();
                return;
            }

            // Check if user is in referral chain
            if (!class_exists(\Joomla\Component\RadicalMartBonuses\Administrator\Helper\ReferralHelper::class)) {
                echo new JsonResponse(['success' => false, 'message' => 'Bonuses component not available']);
                $app->close();
                return;
            }

            $inChain = \Joomla\Component\RadicalMartBonuses\Administrator\Helper\ReferralHelper::inChain($userId);
            if (!$inChain) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_NOT_IN_PROGRAM')]);
                $app->close();
                return;
            }

            // Get RadicalMart params
            $rmParams = \Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper::getComponentParams();

            // Check if referral codes are enabled
            if ((int) $rmParams->get('bonuses_referral_codes_enabled', 1) === 0) {
                echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_CODES_DISABLED')]);
                $app->close();
                return;
            }

            // Check codes limit
            $codesLimit = (int) $rmParams->get('bonuses_referral_codes_limit', 1);
            if ($codesLimit > 0) {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__radicalmart_bonuses_codes'))
                    ->where($db->quoteName('referral') . ' = 1')
                    ->where($db->quoteName('created_by') . ' = ' . (int) $userId);
                $currentCount = (int) $db->setQuery($query)->loadResult();

                if ($currentCount >= $codesLimit) {
                    echo new JsonResponse(['success' => false, 'message' => Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_CODES_LIMIT_REACHED')]);
                    $app->close();
                    return;
                }
            }

            // Check if custom code is allowed
            $canCustomCode = ((int) $rmParams->get('bonuses_referral_codes_custom_code', 1) === 1);
            if (!$canCustomCode) {
                $customCode = ''; // Force auto-generation
            }

            // Use ReferralsModel to create the code
            /** @var \Joomla\Component\RadicalMartBonuses\Site\Model\ReferralsModel $model */
            $model = $app->bootComponent('com_radicalmart_bonuses')
                ->getMVCFactory()
                ->createModel('Referrals', 'Site', ['ignore_request' => true]);

            $model->setState('user.id', $userId);

            $code = $model->createCode($customCode, 'RUB');

            if ($code === false) {
                $errors = $model->getErrors();
                $errorMsg = !empty($errors) ? implode(', ', $errors) : Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_CODE_CREATE_ERROR');
                echo new JsonResponse(['success' => false, 'message' => $errorMsg]);
                $app->close();
                return;
            }

            // Get the created code details
            $linkEnabled = ((int) $rmParams->get('bonuses_codes_cookies_enabled', 1) === 1);
            $linkPrefix = $rmParams->get('bonuses_codes_cookies_selector', 'rbc');
            $link = $linkEnabled ? Uri::root() . '?' . $linkPrefix . '=' . $code : '';

            // Get discount from template
            $templateId = (int) $rmParams->get('bonuses_referral_codes_template_RUB', 0);
            $discount = '';
            if ($templateId > 0) {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select(['discount'])
                    ->from($db->quoteName('#__radicalmart_bonuses_codes'))
                    ->where($db->quoteName('id') . ' = ' . $templateId);
                $template = $db->setQuery($query)->loadObject();
                if ($template && !empty($template->discount)) {
                    $cleanDiscount = \Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper::cleanAdjustmentValue($template->discount);
                    if (strpos($cleanDiscount, '%') !== false) {
                        $discount = $cleanDiscount;
                    } else {
                        $discount = \Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper::toString($cleanDiscount, 'RUB');
                    }
                }
            }

            echo new JsonResponse([
                'success' => true,
                'message' => Text::_('COM_RADICALMART_TELEGRAM_REFERRALS_CODE_CREATED'),
                'code' => $code,
                'link' => $link,
                'discount' => $discount
            ]);

        } catch (\Throwable $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    /**
     * Get bonuses/points info for user
     */
    public function bonuses(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('bonuses', 60);
        $chat = $this->getChatId();

        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid chat', true);
            $app->close();
        }

        try {
            $svc = new BonusesService();
            $data = $svc->getBonusesData($chat);
            echo new JsonResponse($data);
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }

    /**
     * Get summary info (cart count, bonus balance, etc.)
     */
    public function summary(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('summary', 60);
        $chat = $this->getChatId();

        if ($chat <= 0) {
            echo new JsonResponse(null, 'Invalid chat', true);
            $app->close();
        }

        try {
            // Cart count
            $cartSvc = new CartService();
            $cart = $cartSvc->getCart($chat);
            $cartCount = 0;
            if ($cart && !empty($cart->products)) {
                foreach ($cart->products as $p) {
                    $cartCount += (int) ($p->quantity ?? 1);
                }
            }

            // Bonus balance
            $bonusBalance = 0;
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q = $db->getQuery(true)
                    ->select('user_id')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = ' . (int) $chat);
                $userId = (int) $db->setQuery($q)->loadResult();

                if ($userId > 0 && class_exists(PointsHelper::class)) {
                    $bonusBalance = (int) PointsHelper::getBalance($userId);
                }
            } catch (\Throwable $e) {}

            echo new JsonResponse([
                'cart_count' => $cartCount,
                'bonus_balance' => $bonusBalance
            ]);
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }

    /**
     * Get order status for polling (awaiting payment)
     */
    public function orderStatus(): void
    {
        $app = Factory::getApplication();

        try {
            $orderId = $app->input->getInt('id', 0);
            $chat = $app->input->getInt('chat', 0);

            if ($orderId <= 0) {
                throw new \RuntimeException('Order ID required');
            }

            // Get user from chat
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = ' . (int) $chat);
            $userId = (int) $db->setQuery($query)->loadResult();

            if ($userId <= 0) {
                throw new \RuntimeException('User not found');
            }

            // Get order status
            $query = $db->getQuery(true)
                ->select(['o.id', 'o.status', 's.title as status_title'])
                ->from($db->quoteName('#__radicalmart_orders', 'o'))
                ->leftJoin($db->quoteName('#__radicalmart_statuses', 's') . ' ON s.id = o.status')
                ->where($db->quoteName('o.id') . ' = ' . (int) $orderId)
                ->where($db->quoteName('o.created_by') . ' = ' . (int) $userId);

            $db->setQuery($query);
            $order = $db->loadObject();

            if (!$order) {
                throw new \RuntimeException('Order not found');
            }

            echo new JsonResponse([
                'order_id' => (int) $order->id,
                'status_id' => (int) $order->status,
                'status_title' => Text::_($order->status_title ?: ''),
            ]);
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }

    /**
     * Отладка: показать все корзины пользователя
     *
     * @return void
     * @since 1.0.0
     */
    public function debugCarts(): void
    {
        $app = Factory::getApplication();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $chatId = $this->getChatId();
            $userId = 0;

            // Найдём user_id по chat_id
            if ($chatId > 0) {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select('user_id')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chatId');
                $query->bind(':chatId', $chatId, \Joomla\Database\ParameterType::INTEGER);
                $db->setQuery($query);
                $userId = (int) $db->loadResult();
            }

            if ($userId <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'User not linked'
                ], JSON_UNESCAPED_UNICODE);
                $app->close();
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(['id', 'code', 'user_id', 'products', 'created', 'modified'])
                ->from($db->quoteName('#__radicalmart_carts'))
                ->where($db->quoteName('user_id') . ' = :userId')
                ->order($db->quoteName('modified') . ' DESC');
            $query->bind(':userId', $userId, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $carts = $db->loadObjectList();

            $result = [
                'success' => true,
                'user_id' => $userId,
                'chat_id' => $chatId,
                'carts_count' => count($carts),
                'carts' => []
            ];

            foreach ($carts as $cart) {
                $products = json_decode($cart->products ?: '{}', true);
                $result['carts'][] = [
                    'id' => (int) $cart->id,
                    'code' => $cart->code,
                    'created' => $cart->created,
                    'modified' => $cart->modified,
                    'products_count' => is_array($products) ? count($products) : 0,
                    'products' => $products
                ];
            }

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        $app->close();
    }

    /**
     * Отладка: удалить все корзины пользователя кроме самой новой
     *
     * @return void
     * @since 1.0.0
     */
    public function cleanupCarts(): void
    {
        $app = Factory::getApplication();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $chatId = $this->getChatId();
            $userId = 0;

            // Найдём user_id по chat_id
            if ($chatId > 0) {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select('user_id')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chatId');
                $query->bind(':chatId', $chatId, \Joomla\Database\ParameterType::INTEGER);
                $db->setQuery($query);
                $userId = (int) $db->loadResult();
            }

            if ($userId <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'User not linked'
                ], JSON_UNESCAPED_UNICODE);
                $app->close();
                return;
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            // Получим все корзины
            $query = $db->getQuery(true)
                ->select(['id', 'products', 'modified'])
                ->from($db->quoteName('#__radicalmart_carts'))
                ->where($db->quoteName('user_id') . ' = :userId')
                ->order($db->quoteName('modified') . ' DESC');
            $query->bind(':userId', $userId, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $carts = $db->loadObjectList();

            $deleted = [];
            $kept = null;
            $first = true;

            foreach ($carts as $cart) {
                if ($first) {
                    // Оставляем самую новую корзину
                    $kept = (int) $cart->id;
                    $first = false;
                    continue;
                }
                // Удаляем лишние корзины
                $cartId = (int) $cart->id;
                $delQuery = $db->getQuery(true)
                    ->delete($db->quoteName('#__radicalmart_carts'))
                    ->where($db->quoteName('id') . ' = ' . $cartId);
                $db->setQuery($delQuery);
                $db->execute();
                $deleted[] = $cartId;
            }

            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'chat_id' => $chatId,
                'kept' => $kept,
                'deleted' => $deleted,
                'deleted_count' => count($deleted)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        $app->close();
    }

}


