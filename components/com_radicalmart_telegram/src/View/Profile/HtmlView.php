<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Profile;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;


































}    }        parent::display($tpl);        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js?4.5.9', ['uikit.js'], ['defer' => false]);        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js?4.5.9', [], ['defer' => false]);        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css?4.5.9');        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css?1745431273');        $wa = $doc->getWebAssetManager();        $doc = $app->getDocument();        HTMLHelper::_('jquery.framework');        }            $app->setTemplate('yootheme');        {        if ($app->getTemplate() !== 'yootheme')        $app = Factory::getApplication();        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');        $lang->load('com_radicalmart_telegram', JPATH_SITE);        $lang = Factory::getLanguage();    {    public function display($tpl = null)    protected $params;{class HtmlView extends BaseHtmlView
