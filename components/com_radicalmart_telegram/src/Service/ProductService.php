<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Site\Model\ProductModel;

class ProductService
{
    public function getProduct(int $id)
    {
        $model = new ProductModel();
        return $model->getItem($id);
    }
}

