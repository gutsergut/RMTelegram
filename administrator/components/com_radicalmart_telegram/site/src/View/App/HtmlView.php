<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public function display($tpl = null)
    { $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram'); parent::display($tpl); }
}

