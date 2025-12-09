<?php
/**
 * Custom admin form field: RMFieldsSelectClean
 * Provides a selectable (single or multi) list of RadicalMart product field aliases
 * plus special tokens for card rendering. Safe replacement for corrupted RMFieldsSelect.
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class RMFieldsSelectClean extends ListField
{
    /** @var string */
    protected $type = 'RMFieldsSelectClean';

    /**























































}    }        return array_merge($parentOptions, $options);        $parentOptions = parent::getOptions();        // Merge with parent (allows XML <option> definitions if needed)        }            ];                'text'  => $label . ' (__' . $token . ')',                'value' => '__' . $token,            $options[] = (object) [        foreach ($specialTokens as $token => $label) {        ];            'discount' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_DISCOUNT'),            'in_stock' => Text::_('COM_RADICALMART_TELEGRAM_CARDVIEW_FIELDS_TOKEN_IN_STOCK'),        $specialTokens = [        // Special tokens for dynamic card rendering        }            }                }                    ];                        'text'  => $text,                        'value' => $row['alias'],                    $options[] = (object) [                    }                        $text = $row['name'] . ' [' . $row['alias'] . ']';                    if (!empty($row['name'])) {                    $text = $row['alias'];                if (!empty($row['alias'])) {            foreach ($rows as $row) {        if ($rows) {        // Build options from field aliases        }            $rows = [];        } catch (\Throwable $e) {            $rows = $db->loadAssocList();            $db->setQuery($query);                ->order($db->quoteName('ordering') . ' ASC');                ->where($db->quoteName('context') . ' = ' . $db->quote('com_radicalmart.product'))                ->from($db->quoteName('#__fields'))                ->select($db->quoteName(['id', 'name', 'alias']))            $query = $db->getQuery(true)        try {        // Fallback if schema differs: only include fields with a non-empty alias.        // RadicalMart product custom fields table (core Joomla fields system) - use field alias        $db = Factory::getContainer()->get(DatabaseInterface::class);        $options = [];    {    protected function getOptions(): array     */     * Get the list of options
