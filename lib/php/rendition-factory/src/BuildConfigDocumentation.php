<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Documents the structure of a rendition build definition (see Config\YamlLoader).
 */
final readonly class BuildConfigDocumentation
{
    public function getTreeBuilder(): TreeBuilder
    {
        $families = implode(', ', array_map(fn (FamilyEnum $family): string => $family->value, FamilyEnum::cases()));

        $treeBuilder = new TreeBuilder('root');
        // @formatter:off
        $treeBuilder->getRootNode()
            ->info('The build definition is keyed by source file family. One entry per family to support, among: '.$families)
            ->useAttributeAsKey('family')
            ->arrayPrototype()
                ->children()
                    ->arrayNode('transformations')
                        ->isRequired()
                        ->info('The modules to run sequentially to build the rendition.')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('module')
                                    ->isRequired()
                                    ->info('Name of the module to run (see the modules reference).')
                                ->end()
                                ->scalarNode('description')
                                    ->info('Description of the module action.')
                                ->end()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('Whether to enable this module.')
                                ->end()
                                ->variableNode('options')
                                    ->info('Module specific options (see the modules reference).')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
