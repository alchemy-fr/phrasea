<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactoryBundle\DependencyInjection\Compiler;

use Alchemy\WorkflowBundle\Executor\Adapter\Action\ServiceActionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RenditionFactoryActionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
//        if (!$containerBuilder->has(ServiceActionRegistry::class)) {
//            return;
//        }
//
//        $definition = $containerBuilder->findDefinition(ServiceActionRegistry::class);
//
//        $taggedServices = $containerBuilder->findTaggedServiceIds('alchemy_workflow.action');
//
//        $services = [];
//        foreach ($taggedServices as $id => $tags) {
//            $services[$id] = new Reference($id);
//        }
//
//        $definition->setArgument('$services', ServiceLocatorTagPass::register($containerBuilder, $services));
    }
}
