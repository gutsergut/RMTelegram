<?php
/*
 * Service provider for plg_task_radicalmart_telegram_fetch
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\RadicalmartTelegramFetch\Extension\RadicalMartTelegramFetch;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $plugin  = (object) ['type' => 'plugin', 'name' => 'radicalmart_telegram_fetch', 'group' => 'task'];
                $subject = $container->get(DispatcherInterface::class);

                $instance = new RadicalMartTelegramFetch($subject, (array) $plugin);
                $instance->setApplication(Factory::getApplication());

                return $instance;
            }
        );
    }
};
