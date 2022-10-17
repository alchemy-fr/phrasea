<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle;

use Alchemy\RemoteAuthBundle\Security\Factory\RemoteAuthFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyRemoteAuthBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new RemoteAuthFactory());
    }
}
