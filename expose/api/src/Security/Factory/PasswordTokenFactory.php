<?php

declare(strict_types=1);

namespace App\Security\Factory;

use App\Security\Authentication\PasswordTokenProvider;
use App\Security\Authenticator\PasswordTokenAuthenticator;
use App\Security\Firewall\PasswordTokenListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PasswordTokenFactory implements AuthenticatorFactoryInterface
{
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.password.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition(PasswordTokenAuthenticator::class))
        ;

        return $authenticatorId;
    }

    public function getKey(): string
    {
        return 'password';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
    }

    public function getPriority(): int
    {
        return 0;
    }
}
