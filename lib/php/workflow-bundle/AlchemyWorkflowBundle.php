<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle;

use Alchemy\WorkflowBundle\DependencyInjection\Compiler\WorkflowActionCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyWorkflowBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WorkflowActionCompilerPass());
    }
}
