<?php

declare(strict_types=1);

namespace Alchemy\ESBundle;

use Alchemy\ESBundle\DependencyInjection\Compiler\SearchIndexPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyESBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new SearchIndexPass());
    }
}
