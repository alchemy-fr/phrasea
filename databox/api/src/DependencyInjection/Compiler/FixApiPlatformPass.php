<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FixApiPlatformPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $defId = 'api_platform.security.listener.request.deny_access';
        if ($container->hasDefinition($defId)) {
            $container->removeDefinition($defId);
        }
    }
}
