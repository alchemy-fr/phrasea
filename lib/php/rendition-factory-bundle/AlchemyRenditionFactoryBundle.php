<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactoryBundle;

use Alchemy\RenditionFactoryBundle\DependencyInjection\Compiler\RenditionFactoryActionCompilerPass;
use Alchemy\WorkflowBundle\DependencyInjection\Compiler\WorkflowActionCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyRenditionFactoryBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RenditionFactoryActionCompilerPass());
    }
}
