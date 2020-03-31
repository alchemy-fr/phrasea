<?php

namespace Alchemy\SecurityTokenBundle\DependencyInjection;

use Alchemy\SecurityTokenBundle\DependencyInjection\Factory\SignerFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @var SignerFactoryInterface[]
     */
    protected array $signerFactories;

    public function __construct(array $signerFactories)
    {
        $this->signerFactories = $signerFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('alchemy_security_token');
        $node = $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('default_ttl')->defaultValue(3600)->end()
            ->end()
        ;

        $this->addSignerSection($node);

        return $treeBuilder;
    }

    private function addSignerSection(ArrayNodeDefinition $node)
    {
        $adapterNodeBuilder = $node
            ->fixXmlConfig('signer')
            ->children()
            ->arrayNode('signers')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->performNoDeepMerging()
            ->children()
        ;

        foreach ($this->signerFactories as $name => $factory) {
            $factoryNode = $adapterNodeBuilder->arrayNode($name)->canBeUnset();

            $factory->addConfiguration($factoryNode);
        }
    }
}
