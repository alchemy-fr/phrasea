<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Factory;

use Alchemy\RemoteAuthBundle\Security\RemoteAccessAuthenticator;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RemoteAuthFactory implements AuthenticatorFactoryInterface
{
    public function createAuthenticator(ContainerBuilder $container, $firewallName, $config, $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.remote_auth.'.$firewallName;
        $firewallEventDispatcherId = 'security.event_dispatcher.'.$firewallName;

        $container
            ->setDefinition($authenticatorId, new ChildDefinition(RemoteAccessAuthenticator::class))
        ;

        // authenticator manager listener
        $container
            ->setDefinition('security.firewall.authenticator.'.$firewallName, new ChildDefinition('security.firewall.authenticator'))
            ->replaceArgument(0, new Reference($authenticatorId))
        ;

        // user checker listener
        $container
            ->setDefinition('security.listener.user_checker.'.$firewallName, new ChildDefinition('security.listener.user_checker'))
            ->replaceArgument(0, new Reference('security.user_checker.'.$firewallName))
            ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId])
        ;

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getKey(): string
    {
        return 'remote_auth';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
    }
}
