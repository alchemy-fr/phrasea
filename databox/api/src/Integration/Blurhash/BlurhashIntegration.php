<?php

declare(strict_types=1);

namespace App\Integration\Blurhash;

use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class BlurhashIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const VERSION = '1.0';

    public static function getName(): string
    {
        return 'blurhash';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('rendition')
                ->info('Not providing rendition name will use the source file')
            ->end()
            ->scalarNode('attribute')
                ->defaultValue('blurhash')
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            BlurhashAction::class,
        );
    }

    public static function getTitle(): string
    {
        return 'Blurhash';
    }
}
