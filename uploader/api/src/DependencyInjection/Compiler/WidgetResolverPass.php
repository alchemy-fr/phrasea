<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Form\LiFormWidgetResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class WidgetResolverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(LiFormWidgetResolver::class)) {
            return;
        }

        $definition = $container->getDefinition(LiFormWidgetResolver::class);

        foreach ($container->findTaggedServiceIds('widget_resolver') as $id => $tags) {
            $definition->addMethodCall('addResolver', [new Reference($id)]);
        }
    }
}
