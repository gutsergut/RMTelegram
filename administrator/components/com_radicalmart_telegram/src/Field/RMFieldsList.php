<?php
namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;

/**
 * Field listing RadicalMart product fields (alias) + special tokens.
 */
class RMFieldsList extends ListField










































}    }        return $options;        }            $options = array_merge($parent, $options);        if (is_array($parent)) {        $parent = parent::getOptions();        // Merge with parent (allows XML <option> if any)        }            $options[] = (object)['value' => 'weight', 'text' => 'weight'];            // Fallback: minimal option if query fails        } catch (\Throwable $e) {            }                ];                    'text'  => $title !== '' ? $title . ' [' . $alias . ']' : $alias                    'value' => $alias,                $options[] = (object)[                $title = trim((string)$r->title);                if ($alias === '') continue;                $alias = trim((string)$r->alias);            foreach ($rows as $r) {            $rows = $db->setQuery($q)->loadObjectList() ?: [];                ->order($db->escape('f.ordering') . ' ASC');                ->where($db->quoteName('f.state') . ' = 1')                ->from($db->quoteName('#__radicalmart_fields','f'))                ->select(['f.id','f.title','f.alias'])            $q = $db->getQuery(true)            $db = Factory::getContainer()->get('DatabaseDriver');        try {        $options[] = (object)['value' => 'discount', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_DISCOUNT')];        $options[] = (object)['value' => 'in_stock', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_IN_STOCK')];        // Special tokens        $options = [];    {    protected function getOptions()    protected $type = 'RMFieldsList';{
