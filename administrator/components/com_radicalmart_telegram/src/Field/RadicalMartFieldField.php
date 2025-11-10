<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

class RadicalMartFieldField extends ListField
{
    protected $type = 'RadicalMartField';

    protected function getOptions()
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id', 'value'),
                    $db->quoteName('title', 'text'),
                    $db->quoteName('alias'),
                    $db->quoteName('plugin')
                ])
                ->from($db->quoteName('#__radicalmart_fields'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('area') . ' = ' . $db->quote('products'))
                ->order($db->quoteName('title') . ' ASC');

            $db->setQuery($query);
            $fields = $db->loadObjectList();

            foreach ($fields as $field) {
                $options[] = HTMLHelper::_(
                    'select.option',
                    $field->value,
                    $field->text . ' (' . $field->alias . ')'
                );
            }
        } catch (\Exception $e) {
            // В случае ошибки просто возвращаем пустой список
        }

        return $options;
    }
}
