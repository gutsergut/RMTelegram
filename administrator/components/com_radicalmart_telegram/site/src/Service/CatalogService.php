<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Site\Model\ProductsModel;

class CatalogService
{
    public function listProducts(int $page = 1, int $limit = 5, array $filters = []): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(20, $limit));

        $model = new ProductsModel();
        $model->setState('list.limit', $limit);
        $model->setState('list.start', ($page - 1) * $limit);

        if (!empty($filters['in_stock'])) { $model->setState('filter.in_stock', ['all' => '1']); }
        if (!empty($filters['sort'])) {
            $sort = (string) $filters['sort'];
            if ($sort === 'price_asc') { $model->setState('products.ordering', 'p.ordering_price asc'); }
            elseif ($sort === 'price_desc') { $model->setState('products.ordering', 'p.ordering_price desc'); }
            elseif ($sort === 'new') { $model->setState('products.ordering', 'p.created desc'); }
        }
        if (!empty($filters['price'])) {
            $price = $filters['price'];
            if (is_array($price)) {
                $model->setState('filter.price', [ 'from' => $price['from'] ?? '', 'to' => $price['to'] ?? '' ]);
            }
        }
        if (!empty($filters['fields']) && is_array($filters['fields'])) { $model->setState('filter.fields', $filters['fields']); }

        $items = $model->getItems();
        if (!is_array($items)) { return []; }

        $out = [];
        foreach ($items as $it) {
            $priceFinal = (!empty($it->price['final_string'])) ? (string) $it->price['final_string'] : '';
            $image = (!empty($it->image) && is_string($it->image)) ? $it->image : '';
            $category = (!empty($it->category->title)) ? (string) $it->category->title : '';
            $out[] = [ 'id' => (int) ($it->id ?? 0), 'title' => (string) ($it->title ?? ''), 'price_final' => $priceFinal, 'image' => $image, 'category' => $category ];
        }
        return $out;
    }
}

