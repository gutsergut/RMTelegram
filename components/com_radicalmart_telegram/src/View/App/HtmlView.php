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
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Database\ParameterType;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null; // Данные пользователя из TelegramUserHelper
    public array $categoryButtons = [];
    public bool $isMapMode = false;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');

        // Используем централизованный хелпер для идентификации пользователя
        $this->tgUser = TelegramUserHelper::getCurrentUser();

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
                ->where($db->quoteName('state') . ' = 1')
                ->whereIn($db->quoteName('id'), $selected);
            $db->setQuery($query);
            $rows = (array)$db->loadAssocList();
            $this->categoryButtons = array_map(fn($r) => ['id' => (int)$r['id'], 'title' => (string)$r['title']], $rows);
        }

        if ($app->getTemplate() !== 'yootheme') {
            $app->setTemplate('yootheme');
        }

        HTMLHelper::_('jquery.framework');

        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();

        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css?1745431273');
        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css?4.5.9');

        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js?4.5.9', [], ['defer' => false]);
        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js?4.5.9', ['uikit.js'], ['defer' => false]);

        parent::display($tpl);
    }
}
