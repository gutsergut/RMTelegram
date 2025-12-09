<?php
/**
 * @package     com_radicalmart_telegram (admin)
 * Field type: CardViewFields
 * Provides list (single/multi) of RadicalMart product field aliases + special tokens.

















































}    }        return $options;        }            $options = array_merge($parent, $options);        if (is_array($parent)) {        $parent = parent::getOptions();        $options[] = (object)['value' => 'discount', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_DISCOUNT')];        $options[] = (object)['value' => 'in_stock', 'text' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_IN_STOCK')];        // Special tokens        }            $options[] = (object)['value' => 'weight', 'text' => 'weight'];        } catch (\Throwable $e) {            }                $options[] = (object)['value' => $alias, 'text' => $text];                $text = $title !== '' ? $title . ' [' . $alias . ']' : $alias;                $title = trim((string)$r->title);                if ($alias === '') continue;                $alias = trim((string)$r->alias);            foreach ($rows as $r) {            $rows = $db->setQuery($q)->loadObjectList() ?: [];                ->order($db->quoteName('ordering') . ' ASC');                ->where($db->quoteName('area') . ' = ' . $db->quote('products'))                ->where($db->quoteName('state') . ' = 1')                ->from($db->quoteName('#__radicalmart_fields'))                ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('alias')])            $q = $db->getQuery(true)            $db = Factory::getContainer()->get('DatabaseDriver');        try {        $options = [];    {    protected function getOptions(): array    protected $type = 'CardViewFields';{class CardViewFieldsField extends ListFielduse Joomla\CMS\Factory;use Joomla\CMS\Language\Text;use Joomla\CMS\Form\Field\ListField;\defined('_JEXEC') or die;namespace Joomla\Component\RadicalMartTelegram\Administrator\Field; */
