<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyWebhookBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'http_client' => [
                'default_options' => [
                    'max_redirects' => 0,
                ],
            ],
        ]);
    }
}
