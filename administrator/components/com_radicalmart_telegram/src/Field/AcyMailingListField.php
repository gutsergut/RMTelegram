<?php
declare(strict_types=1);
namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
class AcyMailingListField extends ListField
{
    protected $type = 'AcyMailingList';
    protected function getOptions(): array
    {
        $options = [];
        $options[] = (object) [
            'value' => '0',
            'text'  => Text::_('COM_RADICALMART_TELEGRAM_ACYMAILING_LIST_SELECT'),
        ];
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'active']))
                ->from($db->quoteName('#__acym_list'))
                ->order($db->quoteName('name') . ' ASC');
            $db->setQuery($query);
            $lists = $db->loadObjectList();
            if (!empty($lists)) {
                foreach ($lists as $list) {
                    $status = $list->active ? '' : ' [' . Text::_('COM_RADICALMART_TELEGRAM_ACYMAILING_INACTIVE') . ']';
                    $options[] = (object) [
                        'value' => $list->id,
                        'text'  => $list->name . $status,
                    ];
                }
            }
        } catch (\Exception $e) {
            $options[] = (object) [
                'value' => '',
                'text'  => Text::_('COM_RADICALMART_TELEGRAM_ACYMAILING_NOT_INSTALLED'),
            ];
        }
        return array_merge(parent::getOptions(), $options);
    }
}
