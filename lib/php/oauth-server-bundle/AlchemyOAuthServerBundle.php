<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle;

use Alchemy\OAuthServerBundle\DependencyInjection\AlchemyOAuthServerExtension;
use Alchemy\OAuthServerBundle\DependencyInjection\Compiler\OverrideOAuthServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlchemyOAuthServerBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new AlchemyOAuthServerExtension();
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new OverrideOAuthServiceCompilerPass());
    }
}
