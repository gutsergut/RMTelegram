<?php
/**
 * Legacy form field loader fallback for CardViewFields.
 * Provides list of RadicalMart product field aliases + special tokens (in_stock, discount).
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class JFormFieldCardViewFields extends ListField
{
    protected $type = 'CardViewFields';

    protected function getOptions()
    {
        $options = [];




































}    }        return $options;        }            $options = array_merge($parent, $options);        if (is_array($parent)) {        $parent = parent::getOptions();        // Merge with parent provided <option> if any        $options[] = (object)['value' => 'discount', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_DISCOUNT')];        $options[] = (object)['value' => 'in_stock', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_IN_STOCK')];        // Special tokens        }            $options[] = (object)['value' => 'weight', 'text' => 'weight'];            // minimal fallback        } catch (Throwable $e) {            }                $options[] = (object)['value' => $alias, 'text' => $text];                $text  = $title !== '' ? $title . ' [' . $alias . ']' : $alias;                $title = trim((string)$r->title);                if ($alias === '') { continue; }                $alias = trim((string)$r->alias);            foreach ($rows as $r) {            $rows = $db->setQuery($q)->loadObjectList() ?: [];                ->order($db->quoteName('ordering') . ' ASC');                ->where($db->quoteName('area') . ' = ' . $db->quote('products'))                ->where($db->quoteName('state') . ' = 1')                ->from($db->quoteName('#__radicalmart_fields'))                ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('alias')])            $q = $db->getQuery(true)            $db = Factory::getContainer()->get('DatabaseDriver');        try {
