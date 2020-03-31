<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\OAuth\CustomTokenOAuth2;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OverrideOAuthServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->setParameter('fos_oauth_server.server.class', CustomTokenOAuth2::class);
    }
}
