<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\View\Status;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected array $items = [];

    public function display($tpl = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('provider'), $db->quoteName('last_fetch'), $db->quoteName('last_total')])
                ->from($db->quoteName('#__radicalmart_apiship_meta'))
                ->order($db->quoteName('provider') . ' ASC');
            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->items = $rows;
        parent::display($tpl);
    }
}

?>

