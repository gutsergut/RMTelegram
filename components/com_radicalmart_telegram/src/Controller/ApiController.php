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

    public function list(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('list', 60);

        // Инициализация логгера в самом начале для отладки фильтров
        $debug = true;
        try {
            static $loggerReady = false;
            if ($debug && !$loggerReady) {
                \Joomla\CMS\Log\Log::addLogger([
                    'text_file' => 'com_radicalmart.telegram.catalog.php',
                    'extension' => 'com_radicalmart_telegram'
                ], \Joomla\CMS\Log\Log::ALL, ['radicalmart_telegram_catalog']);
                $loggerReady = true;
            }
        } catch (\Throwable $e) {}

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
        // Field filters: read configured field_ids, load aliases from DB, pick values from request
        try {
            $params = $app->getParams('com_radicalmart_telegram');
            $cfg = $params->get('filters_fields');
            $fields = [];
            // DEBUG: вывод всех параметров запроса
            $allInput = $app->input->getArray();
            \Joomla\CMS\Log\Log::add('ApiController.list: ALL INPUT PARAMS=' . json_encode($allInput, JSON_UNESCAPED_UNICODE), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');

            // DEBUG: проверка конфигурации фильтров
            \Joomla\CMS\Log\Log::add('ApiController.list: filters_fields RAW cfg=' . json_encode($cfg, JSON_UNESCAPED_UNICODE) . ' type=' . gettype($cfg) . ' empty=' . (empty($cfg) ? 'YES' : 'NO') . ' isArray=' . (is_array($cfg) ? 'YES' : 'NO'), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');

            // Конвертируем объект в массив (Joomla subform возвращает stdClass)
            if (is_object($cfg)) {
                $cfg = get_object_vars($cfg);
                \Joomla\CMS\Log\Log::add('ApiController.list: Converted object to array, new type=' . gettype($cfg), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
            }

            // Сначала загружаем aliases из БД по field_id
            $fieldIdToAlias = [];
            if (!empty($cfg) && is_array($cfg)) {
                \Joomla\CMS\Log\Log::add('ApiController.list: filters_fields config=' . json_encode($cfg, JSON_UNESCAPED_UNICODE), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
                $fieldIds = [];
                foreach ($cfg as $row) {
                    if (is_object($row)) { $row = get_object_vars($row); }
                    if (!is_array($row)) { continue; }
                    if (empty($row['enabled']) || (int)$row['enabled'] !== 1) continue;
                    if (!empty($row['field_id'])) { $fieldIds[] = (int)$row['field_id']; }
                }
                \Joomla\CMS\Log\Log::add('ApiController.list: field_ids to load=' . json_encode($fieldIds), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
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
                    \Joomla\CMS\Log\Log::add('ApiController.list: fieldIdToAlias map=' . json_encode($fieldIdToAlias, JSON_UNESCAPED_UNICODE), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
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
                    \Joomla\CMS\Log\Log::add('ApiController.list: checking field alias=' . $alias . ' type=' . $type, \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
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
                        \Joomla\CMS\Log\Log::add('ApiController.list: field_' . $alias . ' value=' . json_encode($val), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
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
                \Joomla\CMS\Log\Log::add('ApiController.list: FINAL filters[fields]=' . json_encode($fields, JSON_UNESCAPED_UNICODE), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
            }
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add('ApiController.list: EXCEPTION in field filters: ' . $e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'radicalmart_telegram_catalog');
        }

        // Финальное логирование собранных фильтров
        if ($debug) {
            \Joomla\CMS\Log\Log::add('ApiController.list: page=' . $page . ' limit=' . $lim . ' filters=' . json_encode($filters, JSON_UNESCAPED_UNICODE), \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
        }

        $items = (new CatalogService())->listProducts($page, $lim, $filters);
        try {
            if (!empty($items)) {
                $metaCount = 0; $simpleCount = 0;
                foreach ($items as $it) { if (!empty($it['is_meta'])) $metaCount++; else $simpleCount++; }
                if (!empty($debug)) {
                    \Joomla\CMS\Log\Log::add('ApiController.list: items total=' . count($items) . ' meta=' . $metaCount . ' simple=' . $simpleCount, \Joomla\CMS\Log\Log::DEBUG, 'radicalmart_telegram_catalog');
                    // Duplicate concise summary to common channel for visibility
                    \Joomla\CMS\Log\Log::add('[catalog] list totals: total=' . count($items) . ', meta=' . $metaCount . ', simple=' . $simpleCount, \Joomla\CMS\Log\Log::DEBUG, 'com_radicalmart.telegram');
                }
            } else if (!empty($debug)) {
                \Joomla\CMS\Log\Log::add('ApiController.list: items empty', \Joomla\CMS\Log\Log::INFO, 'radicalmart_telegram_catalog');
                \Joomla\CMS\Log\Log::add('[catalog] list: items empty', \Joomla\CMS\Log\Log::INFO, 'com_radicalmart.telegram');
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
        echo new JsonResponse(['cart' => $cart]);
        $app->close();
    }

    /**
     * Поиск товаров (быстрый search в WebApp)
     * Параметры: q (строка), limit (int)
     */
    public function search(): void
    {
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('search', 40);
        $q    = trim((string) $app->input->get('q', '', 'string'));
        $lim  = $app->input->getInt('limit', 10);
        if ($lim <= 0 || $lim > 50) { $lim = 10; }
        if ($q === '') { echo new JsonResponse(['items'=>[]]); $app->close(); }
        try {
            // Используем CatalogService с фильтром по имени (оставляем как text search)
            $filters = ['search' => $q];
            $items = (new CatalogService())->listProducts(1, $lim, $filters);
            echo new JsonResponse(['items' => $items]);
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
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Связка chat->user
            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();
            $user   = null;
            if ($userId > 0) {
                $user = Factory::getUser($userId);
            }
            $points = 0.0;
            if ($userId > 0) { $points = (float) PointsHelper::getCustomerPoints($userId); }
            // Рефералы: реферальные коды + инфо
            $info = [];
            $codes = [];
            $canCreate = false;
            $createMode = '';
            if ($userId > 0) {
                try {
                    $refModel = new \Joomla\Component\RadicalMartBonuses\Site\Model\ReferralsModel();
                    $refModel->setState('user.id', $userId);
                    $info = $refModel->getInfo($userId);
                    $codes = $refModel->getCodes() ?: [];
                    $createMode = $refModel->canCreateCode();
                    $canCreate = ($createMode !== false);
                } catch (\Throwable $e) { /* ignore */ }
            }
            // Создание кода action=createcode
            $action = $app->input->getCmd('action', '');
            $createdCode = null;
            if ($action === 'createcode' && $canCreate && $userId > 0) {
                $this->guardRateLimitDb('profilecreate', 5);
                $this->guardNonce('createcode');
                try {
                    $refModel = new \Joomla\Component\RadicalMartBonuses\Site\Model\ReferralsModel();
                    $refModel->setState('user.id', $userId);
                    $currency = $app->input->getString('currency', '');
                    $custom   = $app->input->getString('code', '');
                    $createdCode = $refModel->createCode($custom, $currency) ?: null;
                    // обновим список кодов после создания
                    $codes = $refModel->getCodes() ?: [];
                } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
            }
            $data = [
                'user' => ($user ? [
                    'id' => (int) $user->id,
                    'name' => (string) ($user->name ?: $user->username ?: ''),
                    'username' => (string) $user->username,
                    'email' => (string) $user->email,
                    'phone' => (string) ($user->getParam('profile.phonenum') ?: ''),
                ] : null),
                'points' => $points,
                'referrals_info' => $info,
                'referral_codes' => array_map(function($c){
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
                'created_code' => $createdCode,
            ];
            echo new JsonResponse($data);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
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
            $statuses = ConsentHelper::getConsents(max(0, (int) $chat));
            $docs = ConsentHelper::getAllDocumentUrls();
            echo new JsonResponse(['statuses' => $statuses, 'documents' => $docs]);
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
        $allowed = ['personal_data','marketing','terms'];
        if (!in_array($type, $allowed, true)) { echo new JsonResponse(null, 'Invalid type', true); $app->close(); }
        try {
            $ok = ConsentHelper::saveConsent((int)$chat, $type, (bool)$val);
            if (!$ok) { echo new JsonResponse(null, 'Save failed', true); $app->close(); }
            echo new JsonResponse(['ok' => true]);
            $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function legal(): void
    {
        // Возвращает HTML документа (privacy|consent|terms|marketing) санитированный
        $app = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('legal', 30);
        $type = trim((string)$app->input->get('type', '', 'string'));
        $map = [ 'privacy' => 'article_privacy_policy', 'consent' => 'article_consent_personal_data', 'terms' => 'article_terms_of_service', 'marketing' => 'article_consent_marketing' ];
        if (!isset($map[$type])) { echo new JsonResponse(null, 'Invalid type', true); $app->close(); }
        try {
            $params = $app->getParams('com_radicalmart_telegram');
            $articleId = (int)$params->get($map[$type], 0);
            if ($articleId <= 0) { echo new JsonResponse(['html' => '<p>Документ не настроен.</p>']); $app->close(); }
            // Прямой запрос статьи
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q = $db->getQuery(true)
                ->select($db->quoteName(['introtext','fulltext']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $articleId);
            $row = $db->setQuery($q, 0, 1)->loadObject();
            if (!$row) { echo new JsonResponse(['html' => '<p>Документ не найден.</p>']); $app->close(); }
            $htmlRaw = (string)($row->introtext ?? '') . (string)($row->fulltext ?? '');
            // Простая санитизация: удаляем скрипты, iframe, style, on* атрибуты
            $clean = preg_replace('#<script[^>]*?>.*?</script>#is', '', $htmlRaw);
            $clean = preg_replace('#<iframe[^>]*?>.*?</iframe>#is', '', $clean);
            $clean = preg_replace('#<style[^>]*?>.*?</style>#is', '', $clean);
            $clean = preg_replace('#on[a-zA-Z]+\s*=\s*["\"][^"\"]*["\"]#is', '', $clean);
            // Ограничить потенциально опасные ссылки target
            $clean = preg_replace('#<a([^>]+)>#i', '<a$1 target="_blank" rel="noopener">', $clean);
            echo new JsonResponse(['html' => $clean]);
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

        $first = trim($app->input->getString('first_name', ''));
        $second = trim($app->input->getString('second_name', '')); // отчество
        $last  = trim($app->input->getString('last_name', ''));
        $fallbackName = $app->input->getString('name', '');
        $phone = $app->input->getString('phone', '');
        $email = trim($app->input->getString('email', ''));
        $shippingId = $app->input->getInt('shipping_id', 0);
        $paymentId  = $app->input->getInt('payment_id', 0);

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

            // Resolve or create user
            $db = Factory::getContainer()->get('DatabaseDriver');
            $userId = 0;

            $query = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $userId = (int) $db->setQuery($query, 0, 1)->loadResult();

            if ($userId <= 0 && !empty($phone)) {
                $found = RMUserHelper::findUser(['phone' => $phone]);
                if ($found && $found->id) {
                    $userId = (int) $found->id;
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

            // Persist chosen shipping/payment in session state for RadicalMart checkout
            // Validate selected shipping/payment via available methods
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            if ($shippingId > 0) {
                $allowed = [];
                if (!empty($item->shippingMethods)) { foreach ($item->shippingMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$shippingId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_SHIPPING_UNAVAILABLE'), 400); }
                $sessionData['shipping']['id'] = $shippingId;
            }
            if ($paymentId > 0) {
                $allowed = [];
                if (!empty($item->paymentMethods)) { foreach ($item->paymentMethods as $m) { $allowed[(int)$m->id] = true; } }
                if (!isset($allowed[$paymentId])) { throw new \RuntimeException(Text::_('COM_RADICALMART_TELEGRAM_ERR_PAYMENT_UNAVAILABLE'), 400); }
                $sessionData['payment']['id'] = $paymentId;
            }
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            // Create order via CheckoutModel
            // Re-init model after session update
            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);

            $orderData = [
                'created_by' => $userId,
                'contacts' => [
                    'first_name' => ($first ?: ($fallbackName ?: '')),
                    'second_name' => $second,
                    'last_name' => $last,
                    'phone' => $phone,
                    'email' => $email,
                ],
            ];

            if (!$order = $model->createOrder($orderData)) {
                $errors = $model->getErrors();
                $msg = $errors ? (is_array($errors) ? implode("\n", array_map(fn($e)=> ($e instanceof \Exception)?$e->getMessage():$e, $errors)) : (string) $errors) : 'Ошибка оформления заказа';
                throw new \RuntimeException($msg, 500);
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
                    // Try to get order total and currency
                    $model2 = new CheckoutModel();
                    $model2->setState('cart.id', (int) $cart->id);
                    $model2->setState('cart.code', (string) $cart->code);
                    $order2 = $model2->getItem();
                    $paymentPlugin = (!empty($order2->payment) && !empty($order2->payment->plugin)) ? (string) $order2->payment->plugin : '';
                    $isTelegramPayment = ($paymentPlugin !== '' && stripos($paymentPlugin, 'telegram') !== false);
                    if (!$isTelegramPayment) { throw new \RuntimeException('Skip invoice: payment not telegram'); }
                    $currency = (!empty($order2->currency['code'])) ? (string) $order2->currency['code'] : 'RUB';
                    $amountStr = (!empty($order2->total['final_string'])) ? (string) $order2->total['final_string'] : '';
                    $amountMinor = 0;
                    if (!empty($order2->total['final'])) {
                        $amountMinor = (int) round(((float) $order2->total['final']) * 100);
                    } elseif ($amountStr !== '') {
                        $num = preg_replace('#[^0-9\.,]#', '', $amountStr);
                        $num = str_replace(' ', '', $num);
                        $num = str_replace(',', '.', $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }
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
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(['shipping'=>['methods'=>[],'selected'=>0],'payment'=>['methods'=>[],'selected'=>0]]); $app->close(); }

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $item  = $model->getItem();

            // Map shipping methods with providers info
            $mapShipping = function($list) {
                $out = [];
                if ($list) {
                    foreach ($list as $m) {
                        $method = [
                            'id' => (int)$m->id,
                            'title' => (string)$m->title,
                            'disabled' => !empty($m->disabled),
                            'plugin' => isset($m->plugin) ? (string)$m->plugin : '',
                            'providers' => []
                        ];
                        // Get providers for ApiShip methods
                        if (!empty($m->plugin) && stripos($m->plugin, 'apiship') !== false) {
                            try {
                                $params = \Joomla\Plugin\RadicalMartShipping\ApiShip\Extension\ApiShip::getShippingMethodParams((int)$m->id);
                                $providers = $params->get('providers', []);
                                if (!empty($providers)) {
                                    $method['providers'] = array_values((array)$providers);
                                }
                            } catch (\Throwable $e) { /* ignore */ }
                        }
                        $out[] = $method;
                    }
                }
                return $out;
            };
            $mapPayment = function($list){
                $out = [];
                if ($list) {
                    foreach ($list as $m) {
                        $method = [
                            'id' => (int)$m->id,
                            'title' => (string)$m->title,
                            'disabled' => !empty($m->disabled),
                            'plugin' => isset($m->plugin) ? (string)$m->plugin : '',
                            'description' => isset($m->description) ? (string)$m->description : '',
                            'icon' => ''
                        ];
                        // Get icon from media
                        if (!empty($m->media)) {
                            $iconPath = '';
                            if ($m->media instanceof \Joomla\Registry\Registry) {
                                $iconPath = $m->media->get('icon', '');
                            } elseif (is_object($m->media) && isset($m->media->icon)) {
                                $iconPath = $m->media->icon;
                            } elseif (is_array($m->media) && isset($m->media['icon'])) {
                                $iconPath = $m->media['icon'];
                            }
                            if ($iconPath) {
                                // Remove #joomlaImage:// suffix
                                if (($hashPos = strpos($iconPath, '#')) !== false) {
                                    $iconPath = substr($iconPath, 0, $hashPos);
                                }
                                // Remove full URL prefix if present, keep only path
                                if (preg_match('#^https?://[^/]+(/.*?)$#i', $iconPath, $match)) {
                                    $iconPath = $match[1];
                                }
                                // Remove any leading slashes first, then add exactly one
                                $iconPath = ltrim($iconPath, '/');
                                if ($iconPath !== '') {
                                    // $iconPath = '/' . $iconPath;
                                }
                                $method['icon'] = $iconPath;
                            }
                        }
                        $out[] = $method;
                    }
                }
                return $out;
            };

            $selectedShipping = (!empty($item->shipping) && !empty($item->shipping->id)) ? (int) $item->shipping->id : 0;
            $selectedPayment  = (!empty($item->payment) && !empty($item->payment->id)) ? (int) $item->payment->id : 0;
            $res = [
                'shipping' => [
                    'selected' => $selectedShipping,
                    'methods'  => $mapShipping($item->shippingMethods ?? []),
                ],
                'payment' => [
                    'selected' => $selectedPayment,
                    'methods'  => $mapPayment($item->paymentMethods ?? []),
                ],
            ];
            // If chat is not mapped to a user, mark telegram payments disabled
            $hasMap = false;
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__radicalmart_telegram_users'))
                    ->where($db->quoteName('chat_id') . ' = :chat')
                    ->bind(':chat', $chat);
                $hasMap = ((int) $db->setQuery($q, 0, 1)->loadResult()) > 0;
            } catch (\Throwable $e) { $hasMap = false; }
            if ((!$hasMap) && !empty($res['payment']['methods'])) {
                foreach ($res['payment']['methods'] as &$pm) {
                    if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegram') !== false) {
                        $pm['disabled'] = true;
                    }
                }
                unset($pm);
            }
            // Stars method: optionally hide by plugin rules (categories/products)
            try {
                if (!empty($item->products) && !empty($res['payment']['methods'])) {
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select($db->quoteName('params'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'));
                    $paramsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
                    $conf = [];
                    if ($paramsJson !== '') { try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) { $conf = []; } }
                    $parseCsv = function($csv){ $out=[]; foreach (explode(',', (string)$csv) as $v){ $v=trim($v); if ($v!=='' && ctype_digit($v)) $out[]=(int)$v; } return array_values(array_unique($out)); };
                    $allowedCats = $parseCsv($conf['allowed_categories'] ?? '');
                    $excludedCats= $parseCsv($conf['excluded_categories'] ?? '');
                    $allowedProd = $parseCsv($conf['allowed_products'] ?? '');
                    $excludedProd= $parseCsv($conf['excluded_products'] ?? '');
                    $productCats = function($prod){ $ids=[]; if (!empty($prod->category) && !empty($prod->category->id)) $ids[]=(int)$prod->category->id; if (!empty($prod->categories) && is_array($prod->categories)) { foreach ($prod->categories as $c){ if (is_array($c) && !empty($c['id']) && is_numeric($c['id'])) $ids[]=(int)$c['id']; if (is_object($c) && !empty($c->id)) $ids[]=(int)$c->id; } } if (!empty($prod->category_id) && is_numeric($prod->category_id)) $ids[]=(int)$prod->category_id; return array_values(array_unique($ids)); };
                    $isAllowedByCats = function($products) use ($allowedCats,$excludedCats,$productCats){ if (empty($allowedCats)) return true; foreach ($products as $prod){ $ids=$productCats($prod); if (!empty($ids)){ $ok=false; foreach($ids as $cid){ if (in_array($cid,$allowedCats,true)) { $ok=true; break; } } foreach($ids as $cid){ if (in_array($cid,$excludedCats,true)) { $ok=false; break; } } if (!$ok) return false; } } return true; };
                    $isAllowedByProd = function($products) use ($allowedProd,$excludedProd){ if (empty($allowedProd) && empty($excludedProd)) return true; foreach ($products as $prod){ $pid=(int)($prod->id ?? 0); if ($pid<=0) continue; if (!empty($allowedProd) && !in_array($pid,$allowedProd,true)) return false; if (!empty($excludedProd) && in_array($pid,$excludedProd,true)) return false; } return true; };
                    $allowedAll = $isAllowedByCats($item->products) && $isAllowedByProd($item->products);
                    if (!$allowedAll) {
                        foreach ($res['payment']['methods'] as &$pm) {
                            if (!empty($pm['plugin']) && stripos((string)$pm['plugin'], 'telegramstars') !== false) {
                                $pm['disabled'] = true;
                            }
                        }
                        unset($pm);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            echo new JsonResponse($res); $app->close();
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
        $provider   = $app->input->getString('provider', '');
        $extId      = $app->input->getString('id', '');
        $title      = $app->input->getString('title', '');
        $address    = $app->input->getString('address', '');
        $lat        = (float) $app->input->get('lat', 0, 'float');
        $lon        = (float) $app->input->get('lon', 0, 'float');
        $tariffId   = $app->input->getString('tariff_id', '');

        // Логирование входных параметров
        Log::add("[setpvz] INPUT: shippingId=$shippingId, provider=$provider, extId=$extId, tariffId=$tariffId, chat=$chat", Log::DEBUG, 'com_radicalmart.telegram');

        try {
            $svc  = new CartService();
            $cart = $svc->getCart($chat);
            if (!$cart || empty($cart->id)) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_CART_EMPTY'), true); $app->close(); }

            Log::add("[setpvz] Cart found: id={$cart->id}, products=" . count($cart->products ?? []), Log::DEBUG, 'com_radicalmart.telegram');

            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            if ($shippingId > 0) { $sessionData['shipping']['id'] = $shippingId; }
            $sessionData['shipping']['point'] = [
                'id'        => $extId,
                'provider'  => $provider,
                'title'     => $title,
                'address'   => $address,
                'latitude'  => $lat,
                'longitude' => $lon,
            ];

            // Calculate Tariff if ApiShip
            $tariffs = [];
            $selectedTariff = null;
            $tariffDebug = null;
            if ($shippingId > 0 && class_exists(ApiShip::class)) {
                Log::add("[setpvz] Calling calculateTariff for provider=$provider, pointId=$extId", Log::DEBUG, 'com_radicalmart.telegram');
                $tariffResult = ApiShipIntegrationHelper::calculateTariff($shippingId, $cart, $extId, $provider);

                // Extract debug info and tariffs from result
                $tariffDebug = $tariffResult['__debug'] ?? null;
                $tariffs = $tariffResult['tariffs'] ?? [];

                Log::add("[setpvz] calculateTariff returned " . count($tariffs) . " tariffs", Log::DEBUG, 'com_radicalmart.telegram');

                if (!empty($tariffs)) {
                    // Try to find selected tariff
                    if (!empty($tariffId)) {
                        foreach ($tariffs as $t) {
                            if ((string)$t->tariffId === $tariffId) {
                                $selectedTariff = $t;
                                break;
                            }
                        }
                    }

                    // If not selected and only one exists, pick it
                    if (!$selectedTariff && count($tariffs) === 1) {
                        $selectedTariff = $tariffs[0];
                    }

                    if ($selectedTariff) {
                        $deliveryCost = (float)($selectedTariff->deliveryCost ?? 0);
                        $sessionData['shipping']['tariff'] = [
                            'id'   => (int)$selectedTariff->tariffId,
                            'name' => $selectedTariff->tariffName,
                            'hash' => '',
                            'deliveryCost' => $deliveryCost,
                            'daysMin' => (int)($selectedTariff->daysMin ?? 0),
                            'daysMax' => (int)($selectedTariff->daysMax ?? 0),
                        ];
                        // RadicalMart expects price as array with 'base' key
                        $sessionData['shipping']['price'] = [
                            'base' => $deliveryCost,
                        ];
                        Log::add("[setpvz] Selected tariff: id={$selectedTariff->tariffId}, name={$selectedTariff->tariffName}, cost={$deliveryCost}", Log::DEBUG, 'com_radicalmart.telegram');
                    } else {
                        // Multiple tariffs and none selected - clear tariff to force selection
                        unset($sessionData['shipping']['tariff']);
                        Log::add("[setpvz] No tariff selected (multiple available)", Log::DEBUG, 'com_radicalmart.telegram');
                    }
                } else {
                    Log::add("[setpvz] No tariffs returned from calculateTariff", Log::WARNING, 'com_radicalmart.telegram');
                }
            } else {
                Log::add("[setpvz] Skipping tariff calc: shippingId=$shippingId, ApiShip class exists=" . (class_exists(ApiShip::class) ? 'yes' : 'no'), Log::DEBUG, 'com_radicalmart.telegram');
            }

            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            $model = new CheckoutModel();
            $model->setState('cart.id', (int) $cart->id);
            $model->setState('cart.code', (string) $cart->code);
            $order = $model->getItem();

            $shippingTitle = (!empty($order->shipping) && !empty($order->shipping->title)) ? (string) $order->shipping->title : '';
            $orderTotal    = (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '';

            // Prepare order data for frontend (total block update)
            $orderData = null;
            $productsSumString = '';
            $shippingString = '';
            $discountString = '';

            if (!empty($order->total)) {
                // Get shipping price from RadicalMart or selected tariff
                $shippingPrice = 0;
                $rmShippingCalculated = false;

                // First, try to get from shipping object (RadicalMart calculated)
                if (!empty($order->shipping->order->price['final'])) {
                    $shippingPrice = (float) $order->shipping->order->price['final'];
                    $rmShippingCalculated = true;
                }

                // If RadicalMart didn't calculate shipping but we have selected tariff, use it
                if ($selectedTariff && $shippingPrice <= 0) {
                    $shippingPrice = (float)($selectedTariff->deliveryCost ?? 0);
                    $rmShippingCalculated = false;
                }

                // Format shipping string
                if ($shippingPrice > 0) {
                    $shippingString = number_format($shippingPrice, 0, '', ' ') . ' ₽';
                }

                // RadicalMart's final:
                // - If RM calculated shipping: final INCLUDES shipping already
                // - If RM did NOT calculate shipping: final is products only (need to ADD shipping)
                $rmFinal = (float)($order->total['final'] ?? 0);
                $baseSum = (float)($order->total['base'] ?? 0);

                if ($rmShippingCalculated) {
                    // RM's final already includes shipping, so products = final - shipping
                    $productsSum = $rmFinal - $shippingPrice;
                    $finalTotal = $rmFinal;
                } else {
                    // RM's final is products only, need to ADD shipping
                    $productsSum = $rmFinal;
                    $finalTotal = $rmFinal + $shippingPrice;
                }

                $productsSumString = number_format($productsSum, 0, '', ' ') . ' ₽';
                $orderTotal = number_format($finalTotal, 0, '', ' ') . ' ₽';

                // Discount on products = base - productsSum
                $productDiscount = $baseSum - $productsSum;
                $discountString = ($productDiscount > 0)
                    ? number_format($productDiscount, 0, '', ' ') . ' ₽'
                    : '';

                $orderData = [
                    'total' => [
                        'quantity'        => $order->total['quantity'] ?? 0,
                        'sum_string'      => $productsSumString,  // Products sum (after discount, before shipping)
                        'final_string'    => $orderTotal,          // Final total with shipping
                        'discount_string' => $discountString,      // Product discount only
                        'shipping_string' => $shippingString,      // Shipping cost
                    ]
                ];
            }

            Log::add("[setpvz] OUTPUT: shippingTitle=$shippingTitle, orderTotal=$orderTotal, tariffs=" . count($tariffs) .
                ", products_sum=$productsSumString, shipping=$shippingString, discount=$discountString" .
                ", rm_final=" . ($order->total['final'] ?? 'N/A') . ", rm_base=" . ($order->total['base'] ?? 'N/A') .
                ", rm_shipping_calculated=" . ($rmShippingCalculated ? 'yes' : 'no'), Log::DEBUG, 'com_radicalmart.telegram');

            echo new JsonResponse([
                'shipping_title' => $shippingTitle,
                'order_total'    => $orderTotal,
                'order'          => $orderData,
                'pvz'            => $sessionData['shipping']['point'],
                'tariffs'        => $tariffs,
                'selected_tariff' => $selectedTariff ? $selectedTariff->tariffId : null,
                '_debug_tariff'  => $tariffDebug,
                '_debug_session' => [
                    'tariff_id' => $sessionData['shipping']['tariff']['id'] ?? null,
                    'tariff_name' => $sessionData['shipping']['tariff']['name'] ?? null,
                    'price_base' => $sessionData['shipping']['price']['base'] ?? null,
                ]
            ]);
            $app->close();
        } catch (\Throwable $e) {
            Log::add("[setpvz] ERROR: " . $e->getMessage(), Log::ERROR, 'com_radicalmart.telegram');
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
            $sessionData = $app->getUserState('com_radicalmart.checkout.data', []);
            $sessionData['payment']['id'] = $paymentId;
            $app->setUserState('com_radicalmart.checkout.data', $sessionData);

            echo new JsonResponse(['success' => true, 'payment_id' => $paymentId]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }


    public function pvz(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('pvz', 20);
        $bbox = $app->input->getString('bbox', ''); // lon1,lat1,lon2,lat2
        $prov = $app->input->getString('providers', '');
        $limit= $app->input->getInt('limit', 1000);

        try {
            $items = ApiShipIntegrationHelper::getPvzList($bbox, $prov, $limit);
            echo new JsonResponse(['items' => $items]);
            $app->close();
        } catch (\Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
            $app->close();
        }
    }

    public function orders(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('orders', 30);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q  = $db->getQuery(true)
                ->select('user_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $uid = (int) $db->setQuery($q, 0, 1)->loadResult();
            if ($uid <= 0) { echo new JsonResponse(['items'=>[], 'has_more'=>false, 'page'=>1]); $app->close(); }
            $page  = max(1, (int) $app->input->getInt('page', 1));
            $limit = min(50, max(1, (int) $app->input->getInt('limit', 10)));
            $status = trim((string) $app->input->get('status', '', 'string'));
            $q2 = $db->getQuery(true)
                ->select(['id','number'])
                ->from($db->quoteName('#__radicalmart_orders'))
                ->where($db->quoteName('created_by') . ' = :uid')
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('id') . ' DESC')
                ->bind(':uid', $uid);
            if ($status !== '' && ctype_digit($status)) {
                $q2->where($db->quoteName('status') . ' = :st')->bind(':st', (int) $status);
            }
            $offset = ($page - 1) * $limit;
            $db->setQuery($q2, $offset, $limit + 1);
            $rows = $db->loadAssocList() ?: [];
            $hasMore = false;
            if (count($rows) > $limit) { $hasMore = true; array_pop($rows); }
            $out = [];
            $omodel = new AdminOrderModel();
            foreach ($rows as $r) {
                $order = $omodel->getItem((int)$r['id']);
                if (!$order || empty($order->id)) continue;
                $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
                $canResend = ($plugin !== '' && stripos($plugin, 'telegram') !== false);
                $out[] = [
                    'id'     => (int) $order->id,
                    'number' => (string) ($order->number ?? ''),
                    'status' => (!empty($order->status) && !empty($order->status->title)) ? (string) $order->status->title : '',
                    'total'  => (!empty($order->total) && !empty($order->total['final_string'])) ? (string) $order->total['final_string'] : '',
                    'pay_url'=> rtrim(Uri::root(), '/') . '/index.php?option=com_radicalmart&task=payment.pay&order_number=' . urlencode((string) ($order->number ?? '')),
                    'created'=> (string) ($order->created ?? ''),
                    'payment_plugin' => $plugin,
                    'can_resend' => $canResend,
                ];
            }
            // Load statuses list (optional)
            $statuses = [];
            try {
                $qs = $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName('title')])
                    ->from($db->quoteName('#__radicalmart_statuses'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order($db->quoteName('ordering') . ' ASC');
                $statuses = $db->setQuery($qs)->loadAssocList() ?: [];
            } catch (\Throwable $e) { $statuses = []; }

            echo new JsonResponse(['items'=>$out, 'has_more'=>$hasMore, 'page'=>$page, 'statuses'=>$statuses]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

    public function invoice(): void
    {
        $app  = Factory::getApplication();
        $this->guardInitData();
        $this->guardRateLimitDb('invoice', 10);
        $this->guardNonce('invoice');
        $chat = $this->getChatId();
        $number = trim((string) $app->input->getString('number', ''));
        if ($chat <= 0 || $number === '') { echo new JsonResponse(null, Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), true); $app->close(); }
        try {
            // Load order by number
            $pm = new \Joomla\Component\RadicalMart\Site\Model\PaymentModel();
            $order = $pm->getOrder($number, 'number');
            if (!$order || empty($order->id)) { echo new JsonResponse(null, 'Order not found', true); $app->close(); }
            // Check it's telegram payment
            $plugin = (!empty($order->payment) && !empty($order->payment->plugin)) ? (string) $order->payment->plugin : '';
            if ($plugin === '' || stripos($plugin, 'telegram') === false) { echo new JsonResponse(null, 'Payment method is not Telegram', true); $app->close(); }
            // Resolve provider token from component settings (for cards)
            $params = $app->getParams('com_radicalmart_telegram');
            $provider = (string) $params->get('provider_cards', 'yookassa');
            $env      = (string) $params->get('payments_env', 'test');
            $ptoken   = '';
            if (stripos($plugin, 'telegramstars') === false) {
                if ($provider === 'yookassa') {
                    $ptoken = (string) $params->get($env === 'prod' ? 'yookassa_provider_token_prod' : 'yookassa_provider_token_test', '');
                } else {
                    $ptoken = (string) $params->get($env === 'prod' ? 'robokassa_provider_token_prod' : 'robokassa_provider_token_test', '');
                }
                if ($ptoken === '') { echo new JsonResponse(null, 'Missing provider token', true); $app->close(); }
            }
            // Compute amount and currency from AdminOrderModel
            $adm = new AdminOrderModel();
            $ord = $adm->getItem((int) $order->id);
            if (!$ord || empty($ord->id)) { echo new JsonResponse(null, 'Order not found', true); $app->close(); }
            $title = 'Заказ ' . ($ord->number ?? ('#' . (int) $ord->id));
            $desc  = 'Оплата заказа в магазине';
            $payload = 'order:' . (string) ($ord->number ?? (string) $ord->id);
            $tg = new TelegramClient();
            if ($tg->isConfigured()) {
                if (stripos($plugin, 'telegramstars') !== false) {
                    // Convert RUB to stars
                    $rub = 0.0;
                    if (!empty($ord->total['final'])) { $rub = (float) $ord->total['final']; }
                    elseif (!empty($ord->total['final_string'])) {
                        $num = preg_replace('#[^0-9\.,]#', '', (string) $ord->total['final_string']);
                        $num = str_replace(' ', '', $num); $num = str_replace(',', '.', $num);
                        $rub = (float) $num;
                    }
                    // Read stars plugin params
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    $q = $db->getQuery(true)
                        ->select($db->quoteName('params'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('radicalmart_payment'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('telegramstars'));
                    $paramsJson = (string) $db->setQuery($q, 0, 1)->loadResult();
                    $conf = [];
                    if ($paramsJson !== '') { try { $conf = json_decode($paramsJson, true) ?: []; } catch (\Throwable $e) { $conf = []; } }
                    $rubPerStar = isset($conf['rub_per_star']) ? (float) $conf['rub_per_star'] : 1.0;
                    $percent = isset($conf['conversion_percent']) ? (float) $conf['conversion_percent'] : 0.0;
                    if ($rubPerStar <= 0) { $rubPerStar = 1.0; }
                    $rub = $rub * (1.0 + ($percent/100.0));
                    $stars = (int) round($rub / $rubPerStar);
                    if ($stars <= 0) { echo new JsonResponse(null, 'Bad amount', true); $app->close(); }
                    $tg->sendInvoice((int) $chat, $title, $desc, $payload, '', 'XTR', $stars, []);
                } else {
                    $currency = (!empty($ord->currency['code'])) ? (string) $ord->currency['code'] : 'RUB';
                    $amountMinor = 0;
                    if (!empty($ord->total['final'])) {
                        $amountMinor = (int) round(((float) $ord->total['final']) * 100);
                    } elseif (!empty($ord->total['final_string'])) {
                        $num = preg_replace('#[^0-9\.,]#', '', (string) $ord->total['final_string']);
                        $num = str_replace(' ', '', $num); $num = str_replace(',', '.', $num);
                        $amountMinor = (int) round(((float) $num) * 100);
                    }
                    if ($amountMinor <= 0) { echo new JsonResponse(null, 'Bad amount', true); $app->close(); }
                    $tg->sendInvoice((int) $chat, $title, $desc, $payload, $ptoken, $currency, $amountMinor, []);
                }
            }
            echo new JsonResponse(['ok' => true]); $app->close();
        } catch (\Throwable $e) { echo new JsonResponse(null, $e->getMessage(), true); $app->close(); }
    }

}
