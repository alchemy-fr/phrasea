<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\DependencyInjection\Compiler;

use Alchemy\MessengerBundle\Listener\AddErrorDetailsStampListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ReplaceAddErrorStampListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $id = 'messenger.failure.add_error_details_stamp_listener';

        if (!$container->hasDefinition($id)) {
            return;
        }

        $service = $container->getDefinition($id);
        $service->setClass(AddErrorDetailsStampListener::class);
    }
}
