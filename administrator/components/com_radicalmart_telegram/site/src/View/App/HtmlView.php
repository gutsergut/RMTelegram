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

class HtmlView extends BaseHtmlView
{
    protected $params;

    public function display($tpl = null)
    {
        // Load component language file for menu item constants
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');

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
