<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\User\GroupMapper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class IdentityProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(GroupMapper::class);
        $providers = $container->getParameter('app.identity_providers');

        $maps = [];
        foreach ($providers as $provider) {
            $maps[$provider['name']] = $provider['group_map'] ?? [];
        }

        $definition->setArgument('$groupMaps', $maps);
    }
}
