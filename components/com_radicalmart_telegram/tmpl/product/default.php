<?php
/**
 * @package     com_radicalmart_telegram
 * @subpackage  Site
 * Product view - route to standalone TG WebApp template
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

// For Telegram WebApp we need standalone HTML without Joomla wrappers
// Check if we are in a WebApp context (has chat param or tgWebApp=1)
$app = Factory::getApplication();
$isTgWebApp = $app->input->getInt('tgWebApp', 0)
    || $app->input->getInt('chat', 0)
    || $app->input->getString('tgWebAppData', '');

if ($isTgWebApp) {
    // Output standalone template and stop
    include __DIR__ . '/default_tgwebapp.php';
    $app->close();
} else {
    // Normal Joomla rendering - include template as content
    // This path can be used for preview/debug in browser
    include __DIR__ . '/default_tgwebapp.php';
    $app->close();
}
