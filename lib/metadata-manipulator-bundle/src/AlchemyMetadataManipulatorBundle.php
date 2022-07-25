<?php

declare(strict_types=1);

namespace Alchemy\MetadataManipulatorBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyMetadataManipulatorBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new BuildPhpExiftoolClassesCompilerPass());
    }
}
