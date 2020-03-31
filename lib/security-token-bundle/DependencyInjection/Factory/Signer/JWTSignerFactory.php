<?php

declare(strict_types=1);

namespace Alchemy\SecurityTokenBundle\DependencyInjection\Factory\Signer;

use Alchemy\SecurityTokenBundle\DependencyInjection\Factory\SignerFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class JWTSignerFactory implements SignerFactoryInterface
{
    public function getKey(): string
    {
        return 'jwt';
    }

    public function create(ContainerBuilder $container, $id, array $config): void
    {
        $definition = $container
            ->setDefinition($id, new ChildDefinition('alchemy_security_token.signer.'.$this->getKey()))
            ->replaceArgument(0, new Reference($config['client']))
            ->replaceArgument(1, $config['prefix'])
        ;
    }

    public function addConfiguration(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('default_ttl')->end()
                ->scalarNode('signing_key')->isRequired()->end()
            ->end()
        ;
    }
}
