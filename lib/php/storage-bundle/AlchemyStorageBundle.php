<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle;

use Alchemy\StorageBundle\DependencyInjection\Compiler\PreferredMimeTypesPass;
use Alchemy\StorageBundle\Util\PreferredMimeTypes;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyStorageBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new PreferredMimeTypesPass());
    }

    public function boot(): void
    {
        PreferredMimeTypes::setDefault($this->container->get(PreferredMimeTypes::class));
    }
}
