<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\DependencyInjection\Compiler;

use Alchemy\CoreBundle\Health\Checker\PredisConnectionChecker;
use Alchemy\CoreBundle\Health\HealthChecker;
use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use Predis\Client;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class HealthCheckerPass implements CompilerPassInterface
{
    final public const TAG = 'alchemy_core.health_checker';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(HealthChecker::class)) {
            return;
        }

        $definition = $container->getDefinition(HealthChecker::class);

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tag) {
            /* @var HealthCheckerInterface|string $id */
            $definition->addMethodCall('addChecker', [new Reference($id)]);
        }

        $redisMap = [
            Client::class => PredisConnectionChecker::class,
        ];

        foreach ($container->findTaggedServiceIds('snc_redis.client') as $id => $tag) {
            $clientDefinition = $container->getDefinition($id);

            $checkerClass = $redisMap[$clientDefinition->getClass()] ?? null;
            if (null !== $checkerClass) {
                $checkerDefinition = new Definition($checkerClass, [
                    new Reference($id),
                ]);
                $definition->addMethodCall('addChecker', [$checkerDefinition]);
            }
        }
    }
}
