<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Serializer\EntitySerializer;
use App\Serializer\Normalizer\EntityNormalizerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EntityNormalizerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(EntitySerializer::class)) {
            return;
        }

        $definition = $container->getDefinition(EntitySerializer::class);

        foreach ($container->findTaggedServiceIds('app.entity_normalizer') as $id => $tag) {
            /* @var EntityNormalizerInterface|string $id */
            $definition->addMethodCall('addNormalizer', [new Reference($id)]);
        }
    }
}
