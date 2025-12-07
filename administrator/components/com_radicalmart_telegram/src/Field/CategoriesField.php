<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Administrator
 * @version     0.2.0
 * @author      RadicalMart Telegram
 * @copyright   2025
 * @license     GNU/GPL
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

class CategoriesField extends ListField
{
    protected $type = 'Categories';

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('level'), $db->quoteName('state')])
                ->from($db->quoteName('#__radicalmart_categories'))
                ->where($db->quoteName('alias') . ' != ' . $db->quote('root'))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('lft') . ' ASC');

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $prefix = '';
                if ($category->level > 1) {
                    $prefix = str_repeat('- ', $category->level - 1);
                }
                $options[] = HTMLHelper::_('select.option', $category->id, $prefix . $category->title);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error: ' . $e->getMessage(), 'warning');
        }

        return $options;
    }
}
