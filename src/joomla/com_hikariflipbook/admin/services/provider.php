<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Hikari\Component\Hikariflipbook\Administrator\Extension\HikariflipbookComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Hikari\\Component\\Hikariflipbook'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Hikari\\Component\\Hikariflipbook'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                // The interfaces, not the providers that registered them: asking the
                // container for a provider class is what leaves a component unable
                // to boot at all.
                $component = new HikariflipbookComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
