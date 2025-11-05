<?php
/**
 * Site service provider for com_radicalmart_telegram
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\RadicalMartTelegram'));
    }
};

