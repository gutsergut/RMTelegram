<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\ParameterType;

class HtmlView extends BaseHtmlView
{
    protected $params;
    protected array $categoryButtons = [];
    protected bool $isMapMode = false;

    public function display($tpl = null)
    {
        // Load component language file for menu item constants
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        $app = Factory::getApplication();
        $input = $app->input;
        $this->isMapMode = (string)$input->get('mode', 'list') === 'map';

        // Build category buttons from selected ids in params
        $selected = (array)$this->params->get('catalog_categories_filter', []);
        $selected = array_values(array_filter(array_map('intval', $selected)));
        if (!empty($selected)) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title']))
                ->from($db->quoteName('#__radicalmart_categories'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_fill(0, count($selected), '?')) . ')');
            foreach ($selected as $i => $id) {
                $query->bind($i + 1, $id, ParameterType::INTEGER);
            }
            $db->setQuery($query);
            $rows = (array)$db->loadAssocList();
            $this->categoryButtons = array_map(fn($r) => ['id' => (int)$r['id'], 'title' => (string)$r['title']], $rows);
        }

        $this->assign('categoryButtons', $this->categoryButtons);
        $this->assign('isMapMode', $this->isMapMode);

        $app = Factory::getApplication();

        // Force YooTheme template for WebApp (overrides tmpl=component default)
        if ($app->getTemplate() !== 'yootheme')
        {
            $app->setTemplate('yootheme');
        }

        // Load jQuery and core Joomla scripts
        HTMLHelper::_('jquery.framework');

        // Load YooTheme and UIKit assets
        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();

        // YooTheme custom styles
        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css?1745431273');
        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css?4.5.9');

        // UIKit framework
        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js?4.5.9', [], ['defer' => false]);
        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js?4.5.9', ['uikit.js'], ['defer' => false]);

        parent::display($tpl);
    }
}
