<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle;

use Alchemy\MessengerBundle\DependencyInjection\Compiler\ReplaceAddErrorStampListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyMessengerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ReplaceAddErrorStampListenerPass());
    }
}
