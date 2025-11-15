<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\View\Links;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

class HtmlView extends BaseHtmlView
{
    protected array $items = [];
    protected array $filters = [];
    protected $filterForm;
    protected array $activeFilters = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');
        try {
            // Build filter form (SearchTools)
            $form = Form::getInstance('com_radicalmart_telegram.links.filters', JPATH_ADMINISTRATOR . '/components/com_radicalmart_telegram/forms/links_filters.xml', ['control' => 'filter']);
            // Bind current request filter values
            $data = ['filter' => $app->input->get('filter', [], 'array')];
            $form->bind($data);

            // Read filters (with defaults)
            $fSearch = (string) $form->getValue('search', 'filter');
            $fChat   = (string) $form->getValue('chat_id', 'filter');
            $fTg     = (string) $form->getValue('tg_user_id', 'filter');
            $fUser   = (int)    $form->getValue('user_id', 'filter');
            $fUname  = (string) $form->getValue('username', 'filter');
            $fPhone  = (string) $form->getValue('phone', 'filter');
            $fPD     = (string) $form->getValue('consent_personal_data', 'filter');
            $fTerms  = (string) $form->getValue('consent_terms', 'filter');
            $fMkt    = (string) $form->getValue('consent_marketing', 'filter');

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

            // Apply global search
            if ($fSearch !== '') {
                $like = '%' . $db->escape($fSearch, true) . '%';
                $ors  = [];
                foreach (['u.chat_id','u.tg_user_id','u.username','u.phone','ju.name','ju.username','ju.email'] as $col) {
                    $ors[] = $db->quoteName($col) . ' LIKE ' . $db->quote($like, false);
                }
                $query->where('(' . implode(' OR ', $ors) . ')');
            }

            // Apply individual filters if present
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
            if ($fPD !== '') {
                $query->where($db->quoteName('u.consent_personal_data') . ' = :fpd')->bind(':fpd', (int) $fPD);
            }
            if ($fTerms !== '') {
                $query->where($db->quoteName('u.consent_terms') . ' = :fterms')->bind(':fterms', (int) $fTerms);
            }
            if ($fMkt !== '') {
                $query->where($db->quoteName('u.consent_marketing') . ' = :fmkt')->bind(':fmkt', (int) $fMkt);
            }
            $db->setQuery($query, 0, 500);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }
        $this->items = $rows;
        $this->filters = [
            'search' => $fSearch,
            'chat' => $fChat,
            'tg'   => $fTg,
            'user' => $fUser,
            'username' => $fUname,
            'phone' => $fPhone,
            'consent_personal_data' => $fPD,
            'consent_terms' => $fTerms,
            'consent_marketing' => $fMkt,
        ];

        // Expose SearchTools props
        $this->filterForm = $form ?? null;
        $active = [];
        foreach ($this->filters as $k => $v) {
            if ($v !== '' && $v !== null && !(is_int($v) && $v === 0 && !in_array($k, ['user'], true))) {
                $active[$k] = $v;
            }
        }
        $this->activeFilters = $active;
        parent::display($tpl);
    }
}

?>
