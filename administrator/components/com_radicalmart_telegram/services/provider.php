<?php
/**
 * Service provider for com_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\RadicalMartTelegram\Administrator\Extension\RadicalMartTelegramComponent;
use Joomla\Component\RadicalMartTelegram\Administrator\Console\ApiShipFetchCommand;
use Joomla\Component\RadicalMartTelegram\Administrator\Console\HousekeepCommand;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\RadicalMartTelegram'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Joomla\\Component\\RadicalMartTelegram'));
        $container->registerServiceProvider(new RouterFactory('\\Joomla\\Component\\RadicalMartTelegram'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new RadicalMartTelegramComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));
                return $component;
            }
        );

        // Register console command (Joomla 5 auto-discovers tagged console commands)
        if (class_exists(ApiShipFetchCommand::class)) {
            $container->set(
                ApiShipFetchCommand::class,
                function () {
                    return new ApiShipFetchCommand();
                },
                true
            );
            $container->tag('console.command', [ApiShipFetchCommand::class]);
        }

        if (class_exists(HousekeepCommand::class)) {
            $container->set(
                HousekeepCommand::class,
                function () {
                    return new HousekeepCommand();
                },
                true
            );
            $container->tag('console.command', [HousekeepCommand::class]);
        }
    }
};
