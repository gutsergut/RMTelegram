<?php
/**
 * @package     RadicalMart Telegram Notifications Plugin
 * @subpackage  plg_radicalmart_telegram_notifications
 * @version     0.1.0
 * @author      RadicalMart Telegram
 * @copyright   Copyright (C) 2025
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\RadicalMart\TelegramNotifications\Extension\TelegramNotifications;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container)
            {
                $plugin = new TelegramNotifications(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('radicalmart', 'telegram_notifications')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
