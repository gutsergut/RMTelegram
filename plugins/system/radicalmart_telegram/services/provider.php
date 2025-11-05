<?php
/*
 * Service provider for plg_system_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\CMS\Factory;
use Joomla\Plugin\System\Radicalmart_telegram\Extension\RadicalMartTelegram;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class,
            function (Container $container) {
                $plugin  = (object) ['type' => 'plugin', 'name' => 'radicalmart_telegram'];
                $subject = $container->get(DispatcherInterface::class);

                $instance = new RadicalMartTelegram($subject, (array) $plugin);
                $instance->setApplication(Factory::getApplication());

                return $instance;
            }
        );
    }
};

