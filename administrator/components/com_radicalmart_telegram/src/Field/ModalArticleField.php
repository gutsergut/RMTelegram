<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Administrator
 * @author      Sergey Tolkachyov
 * @copyright   Copyright (C) 2025 Sergey Tolkachyov. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       5.0.1
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\Component\Content\Administrator\Field\Modal\ArticleField;

/**
 * Modal Article Field wrapper
 *
 * @since  5.0.1
 */
class ModalArticleField extends ArticleField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  5.0.1
	 */
	protected $type = 'ModalArticle';
}
