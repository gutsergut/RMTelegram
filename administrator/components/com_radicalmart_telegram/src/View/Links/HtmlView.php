<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\View\Links;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected array $items = [];
    protected array $filters = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');
        try {
            // Read filters
            $fChat   = $app->input->getString('filter_chat', '');
            $fTg     = $app->input->getString('filter_tg', '');
            $fUser   = $app->input->getInt('filter_user', 0);
            $fUname  = $app->input->getString('filter_username', '');
            $fPhone  = $app->input->getString('filter_phone', '');

            $query = $db->getQuery(true)
                ->select([
                    'u.id AS link_id', 'u.chat_id', 'u.tg_user_id', 'u.username', 'u.user_id', 'u.phone', 'u.created',
                    'u.consent_personal_data', 'u.consent_personal_data_at',
                    'u.consent_terms', 'u.consent_terms_at',
                    'u.consent_marketing', 'u.consent_marketing_at',
                    'ju.name AS jname', 'ju.username AS jlogin', 'ju.email AS jemail'
                ])
                ->from($db->quoteName('#__radicalmart_telegram_users', 'u'))
                ->join('LEFT', $db->quoteName('#__users', 'ju') . ' ON ju.id = u.user_id')
                ->order('u.created DESC');

            // Apply filters if present
            if ($fChat !== '') {
                $query->where($db->quoteName('u.chat_id') . ' = :fchat')->bind(':fchat', (int) $fChat);
            }
            if ($fTg !== '') {
                $query->where($db->quoteName('u.tg_user_id') . ' = :ftg')->bind(':ftg', (int) $fTg);
            }
            if ($fUser > 0) {
                $query->where($db->quoteName('u.user_id') . ' = :fuser')->bind(':fuser', (int) $fUser);
            }
            if ($fUname !== '') {
                $like = '%' . $db->escape($fUname, true) . '%';
                $query->where($db->quoteName('u.username') . ' LIKE ' . $db->quote($like, false));
            }
            if ($fPhone !== '') {
                $like = '%' . $db->escape($fPhone, true) . '%';
                $query->where($db->quoteName('u.phone') . ' LIKE ' . $db->quote($like, false));
            }
            $db->setQuery($query, 0, 500);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->items = $rows;
        $this->filters = [
            'chat' => $fChat,
            'tg'   => $fTg,
            'user' => $fUser,
            'username' => $fUname,
            'phone' => $fPhone,
        ];
        parent::display($tpl);
    }
}

?>
