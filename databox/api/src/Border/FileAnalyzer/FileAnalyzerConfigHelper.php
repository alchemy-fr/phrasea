<?php

namespace App\Border\FileAnalyzer;

use App\Border\FileAnalyzer\Dto\LogLevelEnum;
use App\Integration\Core\FileAnalyzer\FileAnalyzerAssetActionEnum;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

abstract class FileAnalyzerConfigHelper
{
    final public const string MAX_SEVERITY = 'max_severity';

    public static function createBaseTree(string $name): TreeBuilder
    {
        $actions = FileAnalyzerAssetActionEnum::getValues();

        $treeBuilder = new TreeBuilder('root');
        $children = $treeBuilder->getRootNode()->children();
        // @formatter:off
        $children
            ->scalarNode('name')
                ->cannotBeEmpty()
                ->isRequired()
                ->defaultValue($name)
            ->end()
            ->enumNode(self::MAX_SEVERITY)
                ->defaultValue(LogLevelEnum::Critical->name)
                ->values(LogLevelEnum::getNames())
            ->end()
            ->arrayNode('actions_on_reject')
                ->info('One of: '.implode(', ', $actions))
                ->enumPrototype()
                    ->values($actions)
                ->end()
            ->end()
            ->enumNode(self::MAX_SEVERITY)
                ->defaultValue(LogLevelEnum::Critical->name)
                ->values(LogLevelEnum::getNames())
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
