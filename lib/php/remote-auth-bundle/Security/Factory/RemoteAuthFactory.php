<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Factory;

use Alchemy\RemoteAuthBundle\Security\Firewall\RemoteAuthListener;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoteAuthFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.remote_auth.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition(RemoteAuthProvider::class))
        ;

        $listenerId = 'security.authentication.listener.remote_auth.'.$id;
        $container
            ->setDefinition($listenerId, new ChildDefinition(RemoteAuthListener::class));

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'remote_auth';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }
}
