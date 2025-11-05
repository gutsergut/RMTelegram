<?php
/*
 * @package     com_radicalmart_telegram
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;

class RadicalMartTelegramComponent extends MVCComponent implements RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;
}
