<?php

declare(strict_types=1);

namespace App\Security\Factory;

use App\Security\Authentication\AssetTokenProvider;
use App\Security\Firewall\AssetTokenListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AssetTokenFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.asset.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition(AssetTokenProvider::class))
        ;

        $listenerId = 'security.authentication.listener.asset.'.$id;
        $container->setDefinition($listenerId, new ChildDefinition(AssetTokenListener::class));

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'asset';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }
}
