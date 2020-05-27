<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\DependencyInjection\Compiler;

use Alchemy\CoreBundle\Logger\Handler\ConsoleFilterHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ConsoleFilterHandlePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $serviceId = 'monolog.handler.console_filter';
        if (!$container->hasDefinition($serviceId)) {
            return;
        }

        $definition = $container->getDefinition($serviceId);
        $cacheId = $serviceId . '.wrapper';

        $container
            ->setDefinition($cacheId, new Definition(ConsoleFilterHandler::class))
            ->setArguments($definition->getArguments())
            ->replaceArgument(0, new Reference($cacheId . '.inner'))
            ->setDecoratedService($serviceId);
    }
}
