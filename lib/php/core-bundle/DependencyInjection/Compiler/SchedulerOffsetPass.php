<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\DependencyInjection\Compiler;

use Alchemy\CoreBundle\Scheduler\OffsetScheduleProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Shifts every schedule of the application by SCHEDULER_OFFSET seconds (0 by default),
 * so that several stacks sharing resources do not run their periodic tasks at the same time.
 */
class SchedulerOffsetPass implements CompilerPassInterface
{
    public const string ENV_VAR = 'SCHEDULER_OFFSET';

    public function process(ContainerBuilder $container): void
    {
        if (!interface_exists(ScheduleProviderInterface::class)) {
            return;
        }

        $envDefault = 'env('.self::ENV_VAR.')';
        if (!$container->hasParameter($envDefault)) {
            $container->setParameter($envDefault, '0');
        }

        foreach ($container->findTaggedServiceIds('scheduler.schedule_provider') as $serviceId => $tags) {
            $decoratorId = $serviceId.'.offset';
            $container->register($decoratorId, OffsetScheduleProvider::class)
                ->setArguments([
                    new Reference($decoratorId.'.inner'),
                    '%env(int:'.self::ENV_VAR.')%',
                ])
                ->setDecoratedService($serviceId);
        }
    }
}
