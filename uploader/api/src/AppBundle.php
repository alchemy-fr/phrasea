<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\Compiler\EntityNormalizerPass;
use App\DependencyInjection\Compiler\WidgetResolverPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new WidgetResolverPass());
        $container->addCompilerPass(new EntityNormalizerPass());
    }
}
