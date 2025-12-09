<?php
/**
 * @package     com_radicalmart_telegram (site)
 * Payment Result View
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Paymentresult;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    protected $orderNumber;
    protected $result;
    protected $orderId;
    public $tgUser = null;

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $app = Factory::getApplication();
        $input = $app->input;
        
        $this->params = $app->getParams('com_radicalmart_telegram');
        $this->orderNumber = $input->get('order_number', '', 'string');
        $this->result = $input->get('result', 'return', 'string');
        $this->orderId = $input->get('order_id', 0, 'int');
        
        // Используем централизованный хелпер для идентификации пользователя
        $this->tgUser = TelegramUserHelper::getCurrentUser();

        parent::display($tpl);
    }
}
