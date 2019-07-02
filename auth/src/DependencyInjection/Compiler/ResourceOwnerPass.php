<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\OAuth\OAuthProviderFactory;
use App\OAuth\ResourceOwner\ResourceOwnerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResourceOwnerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(OAuthProviderFactory::class)) {
            return;
        }

        $definition = $container->getDefinition(OAuthProviderFactory::class);

        foreach ($container->findTaggedServiceIds('app.resource_owner') as $id => $tag) {
            /** @var ResourceOwnerInterface|string $id */
            $definition->addMethodCall('addResourceOwner', [$id::getTypeName(), $id]);
        }
    }
}
