<?php
/**
 * @package     com_radicalmart_telegram (site)
 * @subpackage  View
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Paymentresult;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView























}    }        parent::display($tpl);        $this->orderId = $input->get('order_id', 0, 'int');        $this->result = $input->get('result', 'return', 'string'); // success, error, return        $this->orderNumber = $input->get('order_number', '', 'string');        $this->params = $app->getParams('com_radicalmart_telegram');        $input = $app->input;        $app = Factory::getApplication();        $lang->load('com_radicalmart_telegram', JPATH_SITE);        $lang = Factory::getLanguage();    {    public function display($tpl = null)    protected $orderId;    protected $result;    protected $orderNumber;    protected $params;{
