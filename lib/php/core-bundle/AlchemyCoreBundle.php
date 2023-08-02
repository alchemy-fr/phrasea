<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle;

use Alchemy\CoreBundle\DependencyInjection\Compiler\ConsoleFilterHandlePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyCoreBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ConsoleFilterHandlePass());
    }
}
