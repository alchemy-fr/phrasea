<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle;

use Alchemy\CoreBundle\DependencyInjection\Compiler\HealthCheckerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyCoreBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new HealthCheckerPass());
    }
}
