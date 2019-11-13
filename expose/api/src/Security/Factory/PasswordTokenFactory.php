<?php

declare(strict_types=1);

namespace App\Security\Factory;

use App\Security\Authentication\PasswordTokenProvider;
use App\Security\Firewall\PasswordTokenListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PasswordTokenFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.password.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition(PasswordTokenProvider::class))
        ;

        $listenerId = 'security.authentication.listener.password.'.$id;
        $container->setDefinition($listenerId, new ChildDefinition(PasswordTokenListener::class));

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'password';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }
}
