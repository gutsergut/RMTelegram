<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMart\Site\Model\ProductsModel;
use Joomla\Component\RadicalMart\Site\Model\MetasModel;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMartBonuses\Administrator\Helper\PointsHelper;

class CatalogService
{
    private $factory;

    /**
     * Кэш конфигурации кэшбэка
     * @var array|null
     */
    private static ?array $cashbackConfig = null;

    public function __construct()
    {
        $this->factory = Factory::getApplication()->bootComponent('com_radicalmart')->getMVCFactory();
    }

    /**
     * Получить конфигурацию кэшбэка из настроек RadicalMart
     * @return array ['enabled'=>bool, 'formula'=>string, 'from'=>'base'|'final', 'currency'=>string]
     */
    public static function getCashbackConfig(): array
    {
        if (self::$cashbackConfig !== null) {
            return self::$cashbackConfig;
        }

        self::$cashbackConfig = [
            'enabled' => false,
            'formula' => '',
            'from' => 'final',
            'currency' => 'RUB',
            'percent' => 0,
        ];

        try {
            if (!class_exists(ParamsHelper::class)) {
                return self::$cashbackConfig;
            }

            $params = ParamsHelper::getComponentParams();

            // Проверяем включены ли баллы
            if ((int) $params->get('bonuses_points_enabled', 0) !== 1) {
                return self::$cashbackConfig;
            }

            // Получаем текущую валюту
            $currency = \Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper::getCurrency(null);
            $currencyGroup = $currency['group'] ?? 'RUB';

            // Формула начисления баллов
            $formula = (string) $params->get('bonuses_points_accrual_formula_' . $currencyGroup, '');
            if ($formula === '') {
                return self::$cashbackConfig;
            }

            // От какой цены считать: base или final
            $from = (string) $params->get('bonuses_points_accrual_formula_from', 'base');
            if ($from !== 'base' && $from !== 'final') {
                $from = 'final';
            }

            // Вычисляем процент кэшбэка для отображения
            $percent = 0;
            if (strpos($formula, '%') !== false) {
                $percent = (float) preg_replace('/[^0-9.,]/', '', $formula);
            } elseif (strpos($formula, '=') !== false) {
                // Формат "1=100" означает 1 балл за 100 рублей = 1%
                list($points, $per) = explode('=', $formula, 2);
                $points = (float) $points;
                $per = (float) $per;
                if ($per > 0) {
                    $percent = ($points / $per) * 100;
                }
            }

            self::$cashbackConfig = [
                'enabled' => true,
                'formula' => $formula,
                'from' => $from,
                'currency' => $currencyGroup,
                'percent' => round($percent, 1),
            ];
        } catch (\Throwable $e) {
            // Логируем ошибку, но не ломаем работу
            Log::add('CatalogService::getCashbackConfig error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
        }

        return self::$cashbackConfig;
    }

    /**
     * Рассчитать кэшбэк (баллы) для цены
     * @param float $price Цена товара
     * @param bool $isFinal Это финальная цена (после скидки) или базовая
     * @return float Количество баллов
     */
    public static function calculateCashback(float $price, bool $isFinal = true): float
    {
        $config = self::getCashbackConfig();
        if (!$config['enabled'] || $config['formula'] === '' || $price <= 0) {
            return 0;
        }

        // Если настройка "от базовой цены", а передали финальную — возвращаем как есть
        // Логика: мы всегда передаём нужную цену, а здесь просто считаем
        try {
            if (class_exists(PointsHelper::class)) {
                return PointsHelper::calculatePoints($price, $config['formula'], $config['currency']);
            }
        } catch (\Throwable $e) {
            Log::add('CatalogService::calculateCashback error: ' . $e->getMessage(), Log::WARNING, 'com_radicalmart.telegram');
        }

        return 0;
    }

    public function listProducts(int $page = 1, int $limit = 5, array $filters = []): array
    {
        $page  = max(1, $page);
        $limit = (int) $limit; // <=0 => без ограничения
        $app = Factory::getApplication();
        $debug = false;
        try { $debug = (bool) ($app->input->getInt('debug_catalog', 0) === 1 || (int)$app->getParams('com_radicalmart_telegram')->get('debug_catalog', 0) === 1); } catch (\Throwable $e) {}
        if ($debug) {
            static $loggerReady = false;
            if (!$loggerReady) {
                Log::addLogger(['text_file' => 'com_radicalmart.telegram.catalog.php','extension'=>'com_radicalmart_telegram'], Log::ALL, ['radicalmart_telegram_catalog']);
                $loggerReady = true;
            }
        }
        $mode = 'metas';
        try { $mode = (string) $app->getParams('com_radicalmart_telegram')->get('catalog_mode', 'metas'); } catch (\Throwable $e) {}
        $hasFieldFilters = !empty($filters['fields']) && is_array($filters['fields']);
        $hasPriceFilter = !empty($filters['price']) && is_array($filters['price']) && ((string)($filters['price']['from'] ?? '') !== '' || (string)($filters['price']['to'] ?? '') !== '');
        $hasStockFilter = !empty($filters['in_stock']);
        $autoUseMetas = !$hasFieldFilters && !$hasPriceFilter && !$hasStockFilter;
        if ($debug) {
            Log::add('listProducts: page=' . $page . ' limit=' . $limit . ' mode=' . $mode . ' hasFieldFilters=' . (int)$hasFieldFilters . ' hasPriceFilter=' . (int)$hasPriceFilter . ' hasStockFilter=' . (int)$hasStockFilter . ' autoUseMetas=' . (int)$autoUseMetas, Log::DEBUG, 'radicalmart_telegram_catalog');
        }
        if ($mode === 'metas' || ($mode === 'auto' && $autoUseMetas)) {
            return $this->listMetas($page, $limit, $filters);
        }
        $model = new ProductsModel();
        if ($limit <= 0) { $model->setState('list.limit', 0); $model->setState('list.start', 0); }
        else { $model->setState('list.limit', $limit); $model->setState('list.start', ($page - 1) * $limit); }
        $model->setState('list.ordering', 'm.ordering');
        $model->setState('list.direction', 'asc');
        if (!empty($filters['in_stock'])) { $model->setState('filter.in_stock', ['all'=>'1']); }
        if (!empty($filters['sort'])) {
            $sort = (string)$filters['sort'];
            if ($sort==='price_asc') $model->setState('products.ordering','p.ordering_price asc');
            elseif ($sort==='price_desc') $model->setState('products.ordering','p.ordering_price desc');
            elseif ($sort==='new') $model->setState('products.ordering','p.created desc');
        }
        if (!empty($filters['price']) && is_array($filters['price'])) {
            $model->setState('filter.price',[ 'from'=>$filters['price']['from']??'', 'to'=>$filters['price']['to']??'' ]);
        }
        if (!empty($filters['fields']) && is_array($filters['fields'])) { $model->setState('filter.fields',$filters['fields']); }
        $items = $model->getItems(); if (!is_array($items)) return [];
        $out=[]; foreach ($items as $it) {
            $priceFinal=''; if (!empty($it->price) && is_array($it->price) && !empty($it->price['final_string'])) $priceFinal=$it->price['final_string'];
            $image=''; if (!empty($it->image) && is_string($it->image)) $image=$it->image;
            $category=''; if (!empty($it->category) && is_object($it->category) && !empty($it->category->title)) $category=(string)$it->category->title;
            $out[]=['id'=>(int)($it->id??0),'title'=>(string)($it->title??''),'price_final'=>$priceFinal,'image'=>$image,'category'=>$category];
        }
        if ($debug) { Log::add('[catalog] products_count=' . count($out), Log::DEBUG, 'com_radicalmart.telegram'); }
        return $out;
    }

    public function listMetas(int $page = 1, int $limit = 5, array $filters = []): array
    {
        $page = max(1,$page); $limit=(int)$limit;
        $app = Factory::getApplication();
        $debug=false; try { $debug = (bool) ($app->input->getInt('debug_catalog',0)===1 || (int)$app->getParams('com_radicalmart_telegram')->get('debug_catalog',0)===1); } catch (\Throwable $e) {}
        if ($debug) { static $loggerReady=false; if(!$loggerReady){ Log::addLogger(['text_file'=>'com_radicalmart.telegram.catalog.php','extension'=>'com_radicalmart_telegram'], Log::ALL,['radicalmart_telegram_catalog']); $loggerReady=true; } }

        // Определяем фильтры из переданных параметров
        $hasStockFilter = !empty($filters['in_stock']);
        $hasPriceFilter = !empty($filters['price']) && is_array($filters['price']) && ((string)($filters['price']['from'] ?? '') !== '' || (string)($filters['price']['to'] ?? '') !== '');
        $hasFieldFilters = !empty($filters['fields']) && is_array($filters['fields']);
        $hasSort = !empty($filters['sort']) && (string)$filters['sort'] !== '';
        // Автоматически включаем фильтр "в наличии" при любых фильтрах ИЛИ сортировке
        $hasAnyFilter = $hasStockFilter || $hasPriceFilter || $hasFieldFilters || $hasSort;
        if ($debug) { Log::add('listMetas: hasStockFilter=' . (int)$hasStockFilter . ' hasPriceFilter=' . (int)$hasPriceFilter . ' hasFieldFilters=' . (int)$hasFieldFilters . ' hasSort=' . (int)$hasSort . ' hasAnyFilter=' . (int)$hasAnyFilter . ' filters=' . json_encode($filters), Log::DEBUG,'radicalmart_telegram_catalog'); }

        $model = new MetasModel(); try { $model->populateState(); } catch (\Throwable $e) {}
        if ($limit<=0) { $model->setState('list.limit',0); $model->setState('list.start',0); } else { $model->setState('list.limit',$limit); $model->setState('list.start',($page-1)*$limit); }
        $model->setState('list.select',[ 'm.id','m.title','m.alias','m.code','m.type','m.category','m.categories','m.introtext','m.products','m.prices','m.state','m.media','m.params','m.ordering','m.plugins','m.language' ]);

        // Сортировка будет применена позже к массиву $out после фильтрации детей и вычисления цен
        // Для новинок применяем к запросу, для цены - вычислим минимум из детей
        $sortType = !empty($filters['sort']) ? (string)$filters['sort'] : 'default';
        if ($sortType === 'new') {
            $model->setState('list.ordering', 'm.created');
            $model->setState('list.direction', 'desc');
        } else {
            // Загружаем без сортировки по цене, отсортируем позже по вычисленной минимальной
            $orderingState=(string)$model->getState('list.ordering');
            if(str_starts_with($orderingState,'p.')||$orderingState==='') $orderingState='m.ordering';
            $model->setState('list.ordering',$orderingState);
            $model->setState('list.direction','asc');
        }
        $model->setState('products.ordering', null);
        $model->setState('filter.published',1);
        // Языковой режим из настроек компонента: all (0) или current (тег языка)
        $langMode = 'all';
        try { $langMode = (string) Factory::getApplication()->getParams('com_radicalmart_telegram')->get('catalog_language_mode','all'); } catch (\Throwable $e) {}
        if ($langMode === 'current') {
            try { $currentTag = Factory::getApplication()->getLanguage()->getTag(); $model->setState('filter.language',$currentTag); }
            catch (\Throwable $e) { $model->setState('filter.language',0); }
        } else {
            $model->setState('filter.language',0); // без фильтра
        }
        $model->setState('filter.fields',[]); $model->setState('filter.price',[]); $model->setState('filter.categories',[]); $model->setState('filter.manufacturers',[]); $model->setState('filter.badges',[]); $model->setState('filter.in_stock',[]); $model->setState('filter.search',''); $model->setState('filter.item_id',null); $model->setState('filter.item_id.include',true); $model->setState('products.metas',1);
        if (!empty($filters['sort'])) { $sort=(string)$filters['sort']; if($sort==='price_asc') $model->setState('products.ordering','p.ordering_price asc'); elseif($sort==='price_desc') $model->setState('products.ordering','p.ordering_price desc'); elseif($sort==='new') $model->setState('products.ordering','p.created desc'); }
        if ($debug) { Log::add('listMetas: states pre-query: published=' . json_encode($model->getState('filter.published')) . ' language=' . (int)$model->getState('filter.language') . ' products.metas=' . (int)$model->getState('products.metas') . ' ordering=' . (string)$model->getState('list.ordering'), Log::DEBUG,'radicalmart_telegram_catalog'); }
        $items = $model->getItems();
        if ($limit<=0) {
            try { $dbAll=Factory::getContainer()->get('DatabaseDriver'); $qAll=$dbAll->getQuery(true)->select(['m.id','m.title','m.alias','m.type','m.category','m.products','m.prices','m.media','m.language'])->from($dbAll->quoteName('#__radicalmart_metas','m'))->where($dbAll->quoteName('m.state').' = 1')->order($dbAll->escape('m.ordering').' ASC'); $rowsAll=$dbAll->setQuery($qAll)->loadObjectList()?:[]; if(is_array($items)&&count($rowsAll)>count($items)) { $items=$rowsAll; if($debug) Log::add('listMetas: expanded to full set count=' . count($items),Log::DEBUG,'radicalmart_telegram_catalog'); } elseif(!is_array($items)||empty($items)) { $items=$rowsAll; if($debug) Log::add('listMetas: model empty full SQL count=' . count($items),Log::DEBUG,'radicalmart_telegram_catalog'); } } catch (\Throwable $e) { if($debug) Log::add('listMetas: full SQL fetch error=' . $e->getMessage(),Log::WARNING,'radicalmart_telegram_catalog'); }
        }
        if (!is_array($items) || empty($items)) return [];
        $allIds=[]; $metaProductsMap=[]; $addedByMetaRef=[]; $dbProbe=null; try { $dbProbe=Factory::getContainer()->get('DatabaseDriver'); } catch (\Throwable $e) {}
        foreach ($items as $m) {
            $children=[]; $productsNorm=null;
            if (!empty($m->products)) {
                if (is_array($m->products)||is_object($m->products)) { $productsNorm=(array)$m->products; }
                elseif (is_string($m->products)) { $tmp=json_decode($m->products,true); if(is_array($tmp)) $productsNorm=$tmp; else { $tmp2=(new \Joomla\Registry\Registry($m->products))->toArray(); if(is_array($tmp2)) $productsNorm=$tmp2; } }
            }
            if (!empty($productsNorm)) {
                foreach ($productsNorm as $row) { $pid=0; if(is_array($row)){ if(isset($row['id'])) $pid=(int)$row['id']; elseif(count($row)===1 && isset($row[0]) && is_numeric($row[0])) $pid=(int)$row[0]; } elseif(is_object($row)){ if(isset($row->id)) $pid=(int)$row->id; } elseif(is_numeric($row)){ $pid=(int)$row; } if($pid>0){ $children[]=$pid; $allIds[$pid]=true; } }
            }
            if (empty($children) && is_string($m->products) && $m->products!=='') { if (preg_match_all('/"id"\s*:\s*"?(\d+)"?/u',$m->products,$mm)) { foreach($mm[1] as $pidRaw){ $pid=(int)$pidRaw; if($pid>0 && !in_array($pid,$children,true)){ $children[]=$pid; $allIds[$pid]=true; } } } }
            // Удалён probe по p.meta_id: в RadicalMart связка хранится только в поле products мета
            $metaProductsMap[(int)$m->id]=$children;
        }
        if ($debug) { $sampleMap=[]; $d=0; foreach($metaProductsMap as $mid=>$ids){ $sampleMap[$mid]=array_slice($ids,0,15); $d++; if($d>=5) break; } Log::add('listMetas: metaProductsMap metas=' . count($metaProductsMap) . ' sample=' . json_encode($sampleMap) . (empty($addedByMetaRef)?'':' added='.json_encode($addedByMetaRef)), Log::DEBUG,'radicalmart_telegram_catalog'); }
        $childrenById=[];
        if(!empty($allIds)){
            // Используем нативный ProductsModel для получения вариантов (чтобы цены и поля считались через ядро)
            try {
                $idsList=array_map('intval',array_keys($allIds));
                /** @var \Joomla\Component\RadicalMart\Site\Model\ProductsModel $productsModel */
                $productsModel = $this->factory->createModel('Products', 'Site', ['ignore_request' => true]);

                // Настраиваем фильтр по ID
                $productsModel->setState('filter.item_id', $idsList);
                $productsModel->setState('filter.item_id.include', true);
                $productsModel->setState('list.limit', 0); // Без лимита
                $productsModel->setState('list.start', 0);
                // ВАЖНО: включаем ВСЕ статусы (варианты могут быть неопубликованными)
                $productsModel->setState('filter.published', [0, 1]);
                // Отключаем языковой фильтр для вариантов
                $productsModel->setState('filter.language', 0);
                // Отключаем фильтрацию по мета
                $productsModel->setState('products.metas', 0);
                // Применяем фильтр цены если указан
                if ($hasPriceFilter) {
                    $productsModel->setState('filter.price', ['from'=>$filters['price']['from']??'', 'to'=>$filters['price']['to']??'']);
                }
                // Применяем фильтры по полям
                if ($hasFieldFilters) {
                    $productsModel->setState('filter.fields', $filters['fields']);
                }

                $rows = $productsModel->getItems();

                foreach($rows as $r){
                    $childrenById[(int)$r->id]=$this->mapProductForMeta($r);
                }

                if($debug){
                    $requested=array_keys($allIds);
                    $loaded=array_keys($childrenById);
                    $missing=array_diff($requested,$loaded);
                    Log::add('listMetas: ProductsModel children loaded=' . count($childrenById) . ' requested=' . count($requested) . ' missing=' . count($missing), Log::DEBUG,'radicalmart_telegram_catalog');
                }
            } catch(\Throwable $e){
                if($debug){ Log::add('listMetas: ProductsModel error=' . $e->getMessage(), Log::WARNING,'radicalmart_telegram_catalog'); }
            }
        }
        // Дополнительный анализ мета без детей при debug
        if($debug){
            $emptyMetaInfo=[]; $countEmpty=0; foreach($metaProductsMap as $mid=>$ids){ if(empty($ids)){ $countEmpty++; $rawMeta=null; foreach($items as $mtest){ if((int)$mtest->id === $mid){ $rawMeta=$mtest; break; } }
                    if($rawMeta){ $lenProd=is_string($rawMeta->products)? strlen($rawMeta->products): (is_array($rawMeta->products)? count($rawMeta->products):0); $emptyMetaInfo[]=['id'=>$mid,'products_len'=>$lenProd,'lang'=>(string)($rawMeta->language??''),'type'=>(string)($rawMeta->type??'')]; }
                    if(count($emptyMetaInfo)>=20) break; }
            }
            if($countEmpty>0){ Log::add('listMetas: metas with EMPTY children count=' . $countEmpty . ' sample=' . json_encode($emptyMetaInfo, JSON_UNESCAPED_UNICODE), Log::DEBUG,'radicalmart_telegram_catalog'); }
        }
        $out=[]; $metasWithout=0; $metasWith=0; $childrenTotal=0; $skippedByStock=0;
        foreach($items as $m){ $image=''; if(!empty($m->image)&&is_string($m->image)) $image=$m->image; elseif(!empty($m->media)){ try { $media=is_string($m->media)? new \Joomla\Registry\Registry($m->media): new \Joomla\Registry\Registry((array)$m->media); $img=(string)$media->get('image',''); if($img!=='') $image=$img; } catch(\Throwable $e){} } $category=''; if(!empty($m->category)&&is_object($m->category)&&!empty($m->category->title)) $category=(string)$m->category->title; $priceMin=''; $priceMax=''; if(!empty($m->price)&&is_array($m->price)){ $priceMin=(string)($m->price['min_string']??($m->price['min']['final_string']??'')); $priceMax=(string)($m->price['max_string']??($m->price['max']['final_string']??'')); }
            $children=[]; foreach($metaProductsMap[(int)$m->id] as $pid){ if(!empty($childrenById[$pid])) $children[]=$childrenById[$pid]; }
            // Debug: не добавляем missing-товары в отображение, только логируем
            if($debug){
                $missing=[]; foreach($metaProductsMap[(int)$m->id] as $pid){ if(empty($childrenById[$pid])) $missing[]=$pid; }
                if(!empty($missing)) Log::add('listMetas: meta='.(int)$m->id.' missing_children='.json_encode($missing), Log::DEBUG,'radicalmart_telegram_catalog');
            }

            // Фильтрация по наличию при любых активных фильтрах
            if($hasAnyFilter){
                // Если нет детей вообще — пропускаем
                if(empty($children)){
                    $skippedByStock++;
                    if($debug) Log::add('listMetas: skipped meta='.(int)$m->id.' (no children at all)', Log::DEBUG,'radicalmart_telegram_catalog');
                    continue;
                }
                // ВСЕГДА фильтруем по наличию при любом фильтре (убираем недоступные из списка)
                $children = array_values(array_filter($children, function($ch){ return !empty($ch['in_stock']); }));
                if(empty($children)){
                    $skippedByStock++;
                    if($debug) Log::add('listMetas: skipped meta='.(int)$m->id.' (no variants in stock after filter)', Log::DEBUG,'radicalmart_telegram_catalog');
                    continue; // Пропускаем этот мета-товар
                }
                // Примечание: фильтры цены и полей уже применены в ProductsModel, поэтому $children содержит только подходящие варианты
            }

            if(empty($children)) $metasWithout++; else { $metasWith++; $childrenTotal+=count($children); }

            // Вычисляем реальную минимальную цену из отфильтрованных детей для сортировки
            $minPriceRaw = null;
            foreach ($children as $child) {
                if (isset($child['price_final_raw']) && $child['price_final_raw'] > 0) {
                    if ($minPriceRaw === null || $child['price_final_raw'] < $minPriceRaw) {
                        $minPriceRaw = $child['price_final_raw'];
                    }
                }
            }

            // Определяем наличие мета-товара: есть хотя бы один вариант в наличии
            $metaInStock = false;
            foreach ($children as $child) {
                if (!empty($child['in_stock'])) {
                    $metaInStock = true;
                    break;
                }
            }

            // Вычисляем минимальный cashback из детей
            $minCashback = null;
            $cashbackPercent = 0;
            foreach ($children as $child) {
                if (isset($child['cashback_percent']) && $child['cashback_percent'] > 0) {
                    $cashbackPercent = $child['cashback_percent'];
                }
                if (isset($child['cashback']) && $child['cashback'] > 0) {
                    if ($minCashback === null || $child['cashback'] < $minCashback) {
                        $minCashback = $child['cashback'];
                    }
                }
            }
            if ($minCashback === null) $minCashback = 0;

            $out[]=['id'=>(int)($m->id??0),'title'=>(string)($m->title??''),'type'=>(string)($m->type??''),'image'=>$image,'category'=>$category,'price_min'=>$priceMin,'price_max'=>$priceMax,'price_final'=>$priceMin,'min_price_raw'=>$minPriceRaw,'children'=>$children,'is_meta'=>true,'in_stock'=>$metaInStock,'cashback'=>$minCashback,'cashback_percent'=>$cashbackPercent]; }

        // Применяем сортировку к массиву $out после фильтрации детей
        if ($sortType === 'price_asc') {
            usort($out, function($a, $b) {
                $priceA = $a['min_price_raw'] ?? PHP_FLOAT_MAX;
                $priceB = $b['min_price_raw'] ?? PHP_FLOAT_MAX;
                return $priceA <=> $priceB;
            });
        } elseif ($sortType === 'price_desc') {
            usort($out, function($a, $b) {
                $priceA = $a['min_price_raw'] ?? 0;
                $priceB = $b['min_price_raw'] ?? 0;
                return $priceB <=> $priceA;
            });
        } elseif ($sortType === 'default' && !$hasAnyFilter) {
            // При отсутствии фильтров: сначала товары в наличии, потом остальные
            // Используем стабильную сортировку для сохранения исходного порядка (ordering) внутри каждой группы
            $inStock = [];
            $outOfStock = [];
            foreach ($out as $item) {
                if (!empty($item['in_stock'])) {
                    $inStock[] = $item;
                } else {
                    $outOfStock[] = $item;
                }
            }
            $out = array_merge($inStock, $outOfStock);
            if ($debug) {
                Log::add('listMetas: stock sort applied - in_stock=' . count($inStock) . ' out_of_stock=' . count($outOfStock), Log::DEBUG, 'radicalmart_telegram_catalog');
            }
        }
        // Для 'new' сортировка уже применена к MetasModel, для 'default' тоже

        if($debug){ Log::add('listMetas: metas_count=' . count($out) . ' with_children=' . $metasWith . ' without_children=' . $metasWithout . ' children_total=' . $childrenTotal . ' skipped_by_stock=' . $skippedByStock, Log::DEBUG,'radicalmart_telegram_catalog'); Log::add('[catalog] metas_count=' . count($out) . ' with_children=' . $metasWith . ' without_children=' . $metasWithout, Log::DEBUG,'com_radicalmart.telegram'); }
        return $out;
    }

    private function mapProductForMeta(object $it): array
    {
        $priceFinal=''; $priceOriginal=''; $priceBase=''; $discountPercent=''; $discountValue=''; $discountString=''; $discountEnable=false;

        // DEBUG: логируем структуру price для первых товаров
        if((int)($it->id??0) <= 5){
            $priceType = empty($it->price) ? 'empty' : (is_array($it->price) ? 'array' : (is_object($it->price) ? 'object' : gettype($it->price)));
            $pricePreview = '';
            try {
                if(!empty($it->price)){
                    if(is_object($it->price)) $pricePreview = json_encode((array)$it->price, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    elseif(is_array($it->price)) $pricePreview = json_encode($it->price, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                }
            } catch(\Throwable $e){}
            Log::add('mapProductForMeta PRICE DEBUG: id='.(int)$it->id.' priceType='.$priceType.' pricePreview='.$pricePreview, Log::DEBUG,'com_radicalmart.telegram');
        }

        // Цена может быть массивом (уже подготовленным ProductsModel) или объектом/Registry
        if(!empty($it->price)){
            $priceArr = [];
            if(is_array($it->price)) $priceArr = $it->price; elseif(is_object($it->price)) $priceArr = (array)$it->price;
            if($priceArr){
                $priceFinal=(string)($priceArr['final_string']??$priceArr['final']??'');
                $priceBase=(string)($priceArr['base_string']??'');
                $priceOriginal=(string)($priceArr['base_string']??$priceArr['old_string']??$priceArr['original_string']??'');
                $discountEnable=(bool)($priceArr['discount_enable']??false);
                $discountString=(string)($priceArr['discount_string']??'');
                if($priceFinal && $priceOriginal){
                    $origNum=preg_replace('/[^0-9.,]/','',$priceOriginal);
                    $finalNum=preg_replace('/[^0-9.,]/','',$priceFinal);
                    $origNum=str_replace(',', '.', $origNum);
                    $finalNum=str_replace(',', '.', $finalNum);
                    if(is_numeric($origNum)&&is_numeric($finalNum)&&(float)$origNum>0 && (float)$finalNum<(float)$origNum){
                        $d=(float)$origNum-(float)$finalNum; $discountPercent=(string)round($d/(float)$origNum*100); $discountValue=(string)$d;
                    }
                }
            }
        }
        $image=''; if(!empty($it->image)&&is_string($it->image)) $image=$it->image; $category=''; if(!empty($it->category)&&is_object($it->category)&&!empty($it->category->title)) $category=(string)$it->category->title;
        static $params=null; if($params===null){ try { $params=Factory::getApplication()->getParams('com_radicalmart_telegram'); } catch(\Throwable $e){ $params=new \Joomla\Registry\Registry(); } }
        $extendedEnabled=(int)$params->get('cardview_enabled',0)===1; static $resolved=null; if($resolved===null){
            $resolved=['badge'=>[],'subtitle'=>[],'weight'=>'','weight_id'=>0];
            $badgeRaw=$params->get('card_badge_fields',[]); if(!is_array($badgeRaw)) $badgeRaw=$badgeRaw!==''? explode(',',(string)$badgeRaw):[];
            $subtitleRaw=$params->get('card_subtitle_fields',[]); if(!is_array($subtitleRaw)) $subtitleRaw=$subtitleRaw!==''? explode(',',(string)$subtitleRaw):[];
            $weightRaw=$params->get('card_variant_weight_field',''); if(is_array($weightRaw)) $weightRaw=reset($weightRaw);
            $specialTokens=['in_stock','discount'];
            $idsNeeded=[];
            foreach($badgeRaw as $v){ if($v===''||in_array($v,$specialTokens,true)) continue; if(ctype_digit((string)$v)) $idsNeeded[]=(int)$v; }
            foreach($subtitleRaw as $v){ if($v===''||in_array($v,$specialTokens,true)) continue; if(ctype_digit((string)$v)) $idsNeeded[]=(int)$v; }
            if($weightRaw!==''&&!in_array($weightRaw,$specialTokens,true) && ctype_digit((string)$weightRaw)) $idsNeeded[]=(int)$weightRaw;
            $idsNeeded=array_values(array_unique(array_filter($idsNeeded)));
            $idToAlias=[]; if($idsNeeded){ try { $db=Factory::getContainer()->get('DatabaseDriver'); $q=$db->getQuery(true)->select([$db->quoteName('id'),$db->quoteName('alias')])->from($db->quoteName('#__radicalmart_fields'))->where($db->quoteName('id').' IN ('.implode(',',array_map('intval',$idsNeeded)).')'); $rows=$db->setQuery($q)->loadAssocList(); foreach($rows as $r){ $idToAlias[(int)$r['id']]=trim((string)$r['alias']); } } catch(\Throwable $e){} }
            foreach($badgeRaw as $v){ if(in_array($v,$specialTokens,true)){ $resolved['badge'][]=$v; continue; } if(ctype_digit((string)$v)){ $id=(int)$v; if(isset($idToAlias[$id])&&$idToAlias[$id] !== '') $resolved['badge'][]=$idToAlias[$id]; } else { $resolved['badge'][]=trim((string)$v); } }
            foreach($subtitleRaw as $v){ if(in_array($v,$specialTokens,true)){ $resolved['subtitle'][]=$v; continue; } if(ctype_digit((string)$v)){ $id=(int)$v; if(isset($idToAlias[$id])&&$idToAlias[$id] !== '') $resolved['subtitle'][]=$idToAlias[$id]; } else { $resolved['subtitle'][]=trim((string)$v); } }
            if($weightRaw!==''&&!in_array($weightRaw,$specialTokens,true)){
                if(ctype_digit((string)$weightRaw)){
                    $id=(int)$weightRaw; $resolved['weight_id']=$id;
                    if(isset($idToAlias[$id])&&$idToAlias[$id] !== '') $resolved['weight']=$idToAlias[$id];
                } else {
                    $resolved['weight']=trim((string)$weightRaw);
                }
            } elseif(in_array($weightRaw,$specialTokens,true)) { $resolved['weight']=$weightRaw; }
            // Очистка пустых значений
            $resolved['badge']=array_values(array_filter($resolved['badge'], fn($x)=>$x!==''));
            $resolved['subtitle']=array_values(array_filter($resolved['subtitle'], fn($x)=>$x!==''));
            if(!is_string($resolved['weight'])) $resolved['weight']='';
        }
        $weightFieldAlias=$resolved['weight']; $weightFieldId=(int)($resolved['weight_id']??0); $badgeAliases=$resolved['badge']; $subtitleAliases=$resolved['subtitle']; $weightVal=''; $selectedValues=[];
        // Принудительный fallback если алиас пуст, но в настройках задано строковое поле
        if($weightFieldAlias===''){
            try { $rawCfg = (string)$params->get('card_variant_weight_field',''); if($rawCfg!=='') $weightFieldAlias=$rawCfg; } catch(\Throwable $e){}
        }
        $fields = $this->decodeMaybe($it->fields);
        // Лог для диагностики веса
        $debugLocal=false; try { $debugLocal=(bool)(Factory::getApplication()->input->getInt('debug_catalog',0)===1); } catch(\Throwable $e){}
        if($weightFieldAlias || $weightFieldId>0){
            if($weightFieldAlias!=='' && isset($fields[$weightFieldAlias])){ $wf=$fields[$weightFieldAlias]; $weightVal=$this->extractFieldDisplay($wf); }
            // Попытка по numeric ID как ключу
            if($weightVal==='' && $weightFieldId>0 && isset($fields[$weightFieldId])){ $wf=$fields[$weightFieldId]; $weightVal=$this->extractFieldDisplay($wf); }
            // Фолбэк: поиск по вложенному alias в значении массива/объекта
            if($weightVal===''){
                foreach($fields as $key=>$raw){
                    if(is_array($raw)||is_object($raw)){
                        $arr=is_object($raw)? (array)$raw : $raw;
                        if($weightFieldAlias!=='' && isset($arr['alias']) && $arr['alias']===$weightFieldAlias){ $weightVal=$this->extractFieldDisplay($arr); break; }
                        if($weightFieldId>0 && isset($arr['id']) && (int)$arr['id']===$weightFieldId){ $weightVal=$this->extractFieldDisplay($arr); break; }
                    }
                }
            }
            // Последний шанс: перебор всех значений и поиск совпадения ключа/alias (case-insensitive)
            if($weightVal===''){
                $lcAlias=$weightFieldAlias!==''? mb_strtolower($weightFieldAlias):'';
                foreach($fields as $k=>$raw){
                    $kLc=mb_strtolower((string)$k);
                    if($lcAlias!=='' && $kLc===$lcAlias){ $weightVal=$this->extractFieldDisplay($raw); break; }
                    if(is_array($raw)||is_object($raw)){
                        $arr=is_object($raw)? (array)$raw : $raw;
                        if($lcAlias!=='' && isset($arr['alias']) && mb_strtolower((string)$arr['alias'])===$lcAlias){ $weightVal=$this->extractFieldDisplay($arr); break; }
                        if($weightFieldId>0 && isset($arr['id']) && (int)$arr['id']===$weightFieldId){ $weightVal=$this->extractFieldDisplay($arr); break; }
                    }
                }
            }
        }
        if($extendedEnabled){
            $extractAliases=array_unique(array_merge($badgeAliases,$subtitleAliases)); foreach($extractAliases as $aliasKey){ if($aliasKey===''||$aliasKey==='in_stock'||$aliasKey==='discount') continue; if(!isset($fields[$aliasKey])){
                    // Фолбэк по вложенному alias
                    foreach($fields as $key=>$raw){ if(is_array($raw)||is_object($raw)){ $arr=is_object($raw)? (array)$raw : $raw; if(isset($arr['alias']) && $arr['alias']===$aliasKey){ $fv=$arr; $val=$this->extractFieldDisplay($fv); if($val!=='') $selectedValues[$aliasKey]=$val; } } }
                    continue; }
                $fv=$fields[$aliasKey]; $val=$this->extractFieldDisplay($fv); if($val!=='') $selectedValues[$aliasKey]=$val; }
        }
        if($debugLocal){
            $rawPreview='';
            try { $rawPreview=json_encode(array_slice($fields,0,8,true),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } catch(\Throwable $e){}
            Log::add('mapProductForMeta: id='.(int)$it->id.' weightAlias='.$weightFieldAlias.' weightId='.$weightFieldId.' hasKeys='.implode(',',array_keys($fields)).' resolvedWeight='.$weightVal.' fieldsPreview='.$rawPreview, Log::DEBUG,'radicalmart_telegram_catalog');
            if(($weightFieldAlias||$weightFieldId>0) && $weightVal===''){
                // Дополнительная детализация по каждому первому уровню если значение не найдено
                $i=0; foreach($fields as $k=>$v){ if($i>=5) break; $i++; $det=''; if(is_array($v)||is_object($v)){ $arr=is_object($v)?(array)$v:$v; $aliasIn=isset($arr['alias'])?$arr['alias']:''; $idIn=isset($arr['id'])?$arr['id']:''; $valIn=$arr['value']??''; $dispIn=$arr['display']??''; $det='struct alias='.$aliasIn.' id='.$idIn.' value='.((is_scalar($valIn)?$valIn:'' )).' display='.((is_scalar($dispIn)?$dispIn:'')); } else { $det='scalar='.((is_scalar($v)?$v:'')); }
                    Log::add('mapProductForMeta: fieldScan key='.$k.' '.$det, Log::DEBUG,'radicalmart_telegram_catalog');
                }
            }
        }
        // Извлекаем числовое значение финальной цены для сортировки
        $priceFinalRaw = 0.0;
        if ($priceFinal !== '') {
            $numStr = preg_replace('/[^0-9.,]/', '', $priceFinal);
            $numStr = str_replace(',', '.', $numStr);
            if (is_numeric($numStr)) {
                $priceFinalRaw = (float)$numStr;
            }
        }

        $out=['id'=>(int)($it->id??0),'title'=>(string)($it->title??''),'price_final'=>$priceFinal,'price_final_raw'=>$priceFinalRaw,'price_base'=>$priceBase,'base_string'=>$priceBase,'price_original'=>$priceOriginal,'discount_enable'=>$discountEnable,'discount_percent'=>$discountPercent,'discount_value'=>$discountValue,'discount_string'=>$discountString,'image'=>$image,'category'=>$category,'in_stock'=>(bool)($it->in_stock??false)];
        // Всегда гасим системный weight
        $out['weight']='';

        // Нативное извлечение поля веса: сначала из $it->fields (RadicalMart заполняет массив объектов полей)
        $fieldWeightDisplay='';
        if($weightFieldAlias!==''){
            if(isset($it->fields) && is_array($it->fields) && isset($it->fields[$weightFieldAlias])){
                $fw = $it->fields[$weightFieldAlias];
                if(is_object($fw) && isset($fw->value) && $fw->value!==false){
                    $rawVal = is_array($fw->value)? reset($fw->value): $fw->value;
                    if(is_scalar($rawVal)) $fieldWeightDisplay=(string)$rawVal;
                } elseif(is_scalar($fw)) { // иногда может быть просто строка
                    $fieldWeightDisplay=(string)$fw;
                }
            }
            // Если не нашли в fields — пробуем fieldsets
            if($fieldWeightDisplay===''){
                if(isset($it->fieldsets) && is_array($it->fieldsets)){
                    foreach($it->fieldsets as $fs){
                        if(isset($fs->fields) && is_array($fs->fields) && isset($fs->fields[$weightFieldAlias])){
                            $fw=$fs->fields[$weightFieldAlias];
                            if(is_object($fw) && isset($fw->value) && $fw->value!==false){
                                $rawVal = is_array($fw->value)? reset($fw->value): $fw->value;
                                if(is_scalar($rawVal)) $fieldWeightDisplay=(string)$rawVal;
                            }
                        }
                        if($fieldWeightDisplay!=='') break;
                    }
                }
            }
        }
        // Если вообще ничего не извлекли — fallback на ранее найденное значение
        if($fieldWeightDisplay==='') $fieldWeightDisplay=$weightVal;
        // Формат граммов -> г/кг как прежде (если чисто число)
        if($fieldWeightDisplay!=='' && is_numeric($fieldWeightDisplay)){
            $grams=(int)$fieldWeightDisplay;
            if($grams>=1000){ $kg=$grams/1000; $fieldWeightDisplay=($kg==(int)$kg)? ((int)$kg).'кг': $kg.'кг'; }
            else { $fieldWeightDisplay=$grams.'г'; }
        }
        $out['field_weight']=$fieldWeightDisplay;
        // DEBUG: детальный лог для первых товаров
        if((int)($it->id??0) <= 5){
            $fieldsKeys = is_array($fields) ? array_keys($fields) : [];
            $vesValue = isset($fields['ves']) ? json_encode($fields['ves']) : 'NOT_FOUND';
            Log::add('mapProductForMeta DEBUG: id='.(int)$it->id.' title='.(string)($it->title??'').' weightAlias='.$weightFieldAlias.' weightVal='.$weightVal.' formattedWeight='.$formattedWeight.' fieldsKeys=['.implode(',',$fieldsKeys).'] ves='.$vesValue, Log::DEBUG,'com_radicalmart.telegram');
        }
        if($extendedEnabled){ foreach($selectedValues as $k=>$v){ if(!array_key_exists($k,$out)) $out[$k]=$v; } }

        // Расчёт кэшбэка (баллов) для товара
        $cashbackConfig = self::getCashbackConfig();
        $out['cashback'] = 0;
        $out['cashback_percent'] = $cashbackConfig['percent'] ?? 0;
        if ($cashbackConfig['enabled'] && $priceFinalRaw > 0) {
            // Выбираем цену в зависимости от настройки (base или final)
            $priceForCashback = $priceFinalRaw;
            if ($cashbackConfig['from'] === 'base' && $priceBase !== '') {
                $baseNum = preg_replace('/[^0-9.,]/', '', $priceBase);
                $baseNum = str_replace(',', '.', $baseNum);
                if (is_numeric($baseNum) && (float)$baseNum > 0) {
                    $priceForCashback = (float)$baseNum;
                }
            }
            $out['cashback'] = (int) self::calculateCashback($priceForCashback);
        }

        return $out;
    }

    // --- Helpers for direct SQL mapping ---
    private function extractPriceArray(?string $json): array
    {
        $out=[]; if(!$json||trim($json)==='') return $out; $arr=json_decode($json,true); if(!is_array($arr)) return $out;
        // 1) Прямые ключи на верхнем уровне
        if(isset($arr['final_string'])) $out['final_string']=(string)$arr['final_string'];
        if(isset($arr['base_string'])) $out['base_string']=(string)$arr['base_string'];
        if(isset($arr['old_string'])&&!isset($out['base_string'])) $out['base_string']=(string)$arr['old_string'];
        if(isset($arr['original_string'])&&!isset($out['base_string'])) $out['base_string']=(string)$arr['original_string'];
        // 2) Поиск в группах если ещё не нашли
        if(empty($out['final_string'])||empty($out['base_string'])){
            foreach($arr as $grp=>$vals){ if(!is_array($vals)) continue; if(empty($out['final_string']) && isset($vals['final_string'])) $out['final_string']=(string)$vals['final_string'];
                if(empty($out['base_string']) && isset($vals['base_string'])) $out['base_string']=(string)$vals['base_string'];
                if(empty($out['base_string']) && isset($vals['old_string'])) $out['base_string']=(string)$vals['old_string'];
                if(empty($out['base_string']) && isset($vals['original_string'])) $out['base_string']=(string)$vals['original_string'];
                if(isset($out['final_string']) && isset($out['base_string'])) break; }
        }
        // 3) Дополнительный поиск по любому числовому значению > final (когда base_string отсутствует)
        if(empty($out['base_string']) && !empty($out['final_string'])){
            $finalNum=preg_replace('/[^0-9.,]/','',$out['final_string']); $finalNum=str_replace(',', '.', $finalNum);
            $candidate=''; $candidateVal=0.0; if(is_numeric($finalNum)){
                $fVal=(float)$finalNum; $queue=[ $arr ];
                while($queue){ $node=array_shift($queue); if(is_array($node)){ foreach($node as $v){ if(is_array($v)||is_object($v)) { $queue[]=$v; continue; } if(is_string($v)){ $num=preg_replace('/[^0-9.,]/','',$v); $num=str_replace(',', '.', $num); if(is_numeric($num)){ $nVal=(float)$num; if($nVal>$fVal && ($candidateVal==0.0 || $nVal<$candidateVal)){ $candidate=$v; $candidateVal=$nVal; } } } } } elseif(is_object($node)){ $queue[]=(array)$node; } }
                if($candidate!=='') $out['base_string']=$candidate;
            }
        }
        return $out;
    }
    private function extractImage(?string $media): string
    {
        if(!$media||trim($media)==='') return ''; $m=json_decode($media,true); if(is_array($m)&&isset($m['image'])) return (string)$m['image']; return '';
    }
    private function decodeMaybe($raw)
    {
        if (is_array($raw))
        {
            $arr = $raw;
        }
        elseif (is_object($raw))
        {
            $arr = (array) $raw;
        }
        elseif (is_string($raw))
        {
            $tmp = json_decode($raw, true);
            $arr = is_array($tmp) ? $tmp : [];
        }
        else
        {
            $arr = [];
        }

        return $this->flattenFieldContainers($arr);
    }

    private function flattenFieldContainers(array $fields): array
    {
        if (empty($fields))
        {
            return [];
        }

        $flat = [];
        foreach ($fields as $key => $value)
        {
            if (is_string($key) && str_starts_with($key, 'fields_') && is_array($value))
            {
                foreach ($value as $innerKey => $innerValue)
                {
                    if ($innerKey === '' || $innerKey === null)
                    {
                        continue;
                    }
                    $flat[$innerKey] = $innerValue;
                }

                continue;
            }

            $flat[$key] = $value;
        }

        return $flat;
    }
    private function buildCategoryStub($cat){ if(is_object($cat)&&isset($cat->title)) return $cat; if(is_string($cat)&&$cat!==''){ return (object)['title'=>$cat]; } return (object)[]; }

    private function extractFieldDisplay($fv): string
    {
        // Нормализуем в массив для удобства обхода
        if(is_object($fv)) $fv=(array)$fv;
        // Скаляр
        if(is_scalar($fv)) return trim((string)$fv);
        if(!is_array($fv)) return '';
        // ПРИОРИТЕТ: сначала значение (value/display), потом название (label/title)
        // Если есть value: он может быть скаляром или массивом
        if(array_key_exists('value',$fv)){
            $v=$fv['value']; if(is_object($v)) $v=(array)$v;
            if(is_scalar($v)) return trim((string)$v);
            if(is_array($v)){
                $parts=[]; foreach($v as $item){
                    if(is_object($item)) $item=(array)$item;
                    if(is_array($item)){
                        foreach(['display','text','label','title','value'] as $k){ if(isset($item[$k]) && $item[$k] !== ''){ $parts[] = trim((string)$item[$k]); break; } }
                    } else { $parts[] = trim((string)$item); }
                }
                return implode(' / ', array_filter($parts, fn($s)=>$s!==''));
            }
        }
        // Если нет value, попробуем display/text
        foreach(['display','text'] as $k){ if(isset($fv[$k]) && $fv[$k] !== '') return trim((string)$fv[$k]); }
        // Fallback на название поля (label/title) — используем только если нет значения
        foreach(['label','title'] as $k){ if(isset($fv[$k]) && $fv[$k] !== '') return trim((string)$fv[$k]); }
        // Возможно массив значений без ключей
        if(array_is_list($fv)){
            $parts=[]; foreach($fv as $item){ $parts[]=$this->extractFieldDisplay($item); }
            $parts=array_filter($parts, fn($s)=>$s!=='');
            return implode(' / ', $parts);
        }
        return '';
    }
}
