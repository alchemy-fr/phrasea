<?php

declare(strict_types=1);

namespace App\Integration\Core\Watermark;

use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class WatermarkIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const VERSION = '1.0';

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('attributeName')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->booleanNode('regenerateOnUpdate')
                ->defaultTrue()
            ->end()
            ->arrayNode('applyToRenditions')
                ->isRequired()
                ->requiresAtLeastOneElement()
                    ->scalarPrototype()
                    ->end()
            ->end()
            ->scalarNode('fontSize')
                ->defaultValue('14')
            ->end()
            ->scalarNode('color')
                ->defaultValue('#000000')
            ->end()
            ->arrayNode('position')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('top')
                        ->defaultValue('50%')
                    ->end()
                    ->scalarNode('left')
                        ->defaultValue('50%')
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            WatermarkAction::class,
        );
    }

    public static function getName(): string
    {
        return 'core.watermark';
    }

    public static function getTitle(): string
    {
        return 'Watermark';
    }
}
