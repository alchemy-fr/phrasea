<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AttributeTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $fieldRegistry = $container->getDefinition(AttributeTypeRegistry::class);
        $taggedServices = $container->findTaggedServiceIds('app.attribute_type');

        /** @var string|AttributeTypeInterface $id */
        foreach ($taggedServices as $id => $tags) {
            $refl = new \ReflectionClass($id);
            if ($refl->isAbstract()) {
                continue;
            }
            $fieldRegistry->addMethodCall('addType', [new Reference($id)]);
        }
    }
}
