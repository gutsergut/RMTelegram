<?php
/**
 * @package     RadicalMart Telegram Expiring Points Task
 * @subpackage  plg_task_radicalmart_telegram_expiring
 * @version     0.1.0
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\RadicalmartTelegramExpiring\Extension\RadicalMartTelegramExpiring;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container)
            {
                $plugin = new RadicalMartTelegramExpiring(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'radicalmart_telegram_expiring')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
