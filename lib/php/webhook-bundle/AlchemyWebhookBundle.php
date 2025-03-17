<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyWebhookBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {       
        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();
        
    }
}
