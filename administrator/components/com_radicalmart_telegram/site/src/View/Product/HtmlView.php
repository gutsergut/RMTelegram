<?php
/*
 * @package     com_radicalmart_telegram (site)
 * Product view with full RadicalMart data: gallery, variability, etc.
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Product;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\PluginsHelper;

class HtmlView extends BaseHtmlView
{
    /**
     * Product object
     * @var object|null
     */
    protected $product = null;

    /**
     * Telegram chat ID
     * @var int
     */
    protected $chatId = 0;

    /**
     * Category object
     * @var object|null
     */
    protected $category = null;

    /**
     * Product variability meta product object
     * @var object|false
     */
    protected $variability = false;

    /**
     * Products variability form
     * @var Form|false
     */
    protected $variabilityForm = false;

    /**
     * This product gallery data
     * @var array
     */
    protected array $gallery = [];

    /**
     * RadicalMart ProductModel instance
     * @var \Joomla\Component\RadicalMart\Site\Model\ProductModel|null
     */
    protected $rmModel = null;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $this->chatId = $app->input->getInt('chat', $app->input->getInt('chat_id', 0));

        $id = $app->input->getInt('id', 0);
        if ($id > 0) {
            $this->loadProductData($id);
        }

        parent::display($tpl);
    }

    /**
     * Load full product data including gallery and variability
     */
    protected function loadProductData(int $id): void
    {
        try {
            // Create RadicalMart ProductModel
            $this->rmModel = new \Joomla\Component\RadicalMart\Site\Model\ProductModel();
            $this->rmModel->setState('product.id', $id);
            $this->rmModel->setState('filter.published', [1, 2]);

            // Get product
            $this->product = $this->rmModel->getItem($id);

            if (empty($this->product) || empty($this->product->id)) {
                return;
            }

            // Get category
            $this->category = $this->product->category ?? null;

            // Get variability (for variants like weight)
            $this->variability = $this->rmModel->getVariability($id);

            // Get variability form
            $this->variabilityForm = $this->rmModel->getVariabilityForm($id);

            // Get gallery
            $this->gallery = $this->getGallery();

        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Error loading product: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Method to get product gallery data
     * Based on RadicalMart\Site\View\Product\HtmlView::getGallery()
     *
     * @return array Gallery data array
     */
    protected function getGallery(): array
    {
        if (empty($this->product) || empty($this->product->media)) {
            return [];
        }

        $gallery = $this->product->media->get('gallery', []);
        if (empty($gallery)) {
            return [];
        }

        // Get media types
        $types = [
            'image' => [
                'layout_slide'   => 'components.radicalmart.gallery.image.slide',
                'layout_preview' => 'components.radicalmart.gallery.image.preview',
            ]
        ];

        // Trigger plugin event for additional gallery types (video, etc.)
        try {
            PluginHelper::importPlugin('radicalmart');
            PluginsHelper::triggerPlugins(
                ['radicalmart_media', 'radicalmart', 'system'],
                'onRadicalMartGetProductGalleryTypes',
                ['com_radicalmart.product', &$types, $this->product, $this->category]
            );
        } catch (\Throwable $e) {
            // Ignore plugin errors
        }

        $result = [];
        foreach ($gallery as $item) {
            $type = isset($types[$item->type]) ? $types[$item->type] : false;
            if (empty($type)) {
                continue;
            }

            $result[] = [
                'item' => $item,
                'type' => $type,
            ];
        }

        return $result;
    }
}
