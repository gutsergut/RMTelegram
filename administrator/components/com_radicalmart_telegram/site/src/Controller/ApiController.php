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

class ApiController extends BaseController
{
    protected function getChatId(): int
    {
        return (int) Factory::getApplication()->input->get('chat', 0, 'int');
    }

    protected function guardRate(string $scope, int $maxPerMinute): void
    {
        $app = Factory::getApplication();
        $session = $app->getSession();
        $now = time();
        $key = 'rm_tg.rl.' . md5($scope);
        $arr = $session->get($key, []);
        if (!is_array($arr)) { $arr = []; }
        $arr = array_values(array_filter($arr, function($t) use ($now) { return is_int($t) && $t > $now - 60; }));
        if (count($arr) >= $maxPerMinute) {
            echo new JsonResponse(null, 'Too many requests', true);
            $app->close();
        }
        $arr[] = $now;
        $session->set($key, $arr);
    }

    public function list(): void
    {
        $app  = Factory::getApplication();
        $this->guardRate('list', 60);
        $page = $app->input->getInt('page', 1);
        $lim  = $app->input->getInt('limit', 12);
        $inStock = $app->input->getInt('in_stock', 0) === 1;
        $sort = trim((string) $app->input->get('sort', '', 'string'));
        $filters = [ 'in_stock' => $inStock, 'sort' => $sort ];
        $items = (new CatalogService())->listProducts($page, $lim, $filters);
        echo new JsonResponse([ 'items' => $items, 'page' => $page, 'limit' => $lim ]);
        $app->close();
    }

    public function add(): void
    {
        $app  = Factory::getApplication();
        $this->guardRate('add', 30);
        $chat = $this->getChatId();
        $id   = $app->input->getInt('id', 0);
        $qty  = (float) $app->input->get('qty', 1.0, 'float');
        if ($chat <= 0 || $id <= 0 || $qty <= 0) {
            echo new JsonResponse(null, 'Invalid input', true); $app->close();
        }
        $res = (new CartService())->addProduct($chat, $id, $qty);
        if ($res === false) { echo new JsonResponse(null, 'Cannot add', true); $app->close(); }
        echo new JsonResponse($res); $app->close();
    }

    public function cart(): void
    {
        $app  = Factory::getApplication();
        $this->guardRate('cart', 60);
        $chat = $this->getChatId();
        if ($chat <= 0) { echo new JsonResponse(null, 'Invalid chat', true); $app->close(); }
        $cart = (new CartService())->getCart($chat);
        echo new JsonResponse([ 'cart' => $cart ]); $app->close();
    }
}

