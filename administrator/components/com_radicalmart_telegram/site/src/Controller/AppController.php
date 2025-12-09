<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class AppController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $app = Factory::getApplication();
        $app->input->set('view', 'app');
        $app->input->set('tmpl', 'component');
        return parent::display($cachable, $urlparams);
    }
}

