<?php

declare(strict_types=1);

namespace App\Integration\Core\FileAnalyzer;

use Alchemy\Workflow\Model\Workflow;
use App\Border\FileAnalyzerRegistry;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class FileAnalyzerIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function __construct(
        private readonly FileAnalyzerRegistry $fileAnalyzerRegistry,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('analyzers')
                ->prototype('variable')
                ->end()
            ->end()
            ->arrayNode('actions_on_error')
            ->end();
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        $isIngest = $workflow->getOn()->hasEventName(AssetIngestWorkflowEvent::EVENT);
        if (!$isIngest) {
            return [];
        }

        $job = WorkflowHelper::createIntegrationJob(
            $config,
            FileAnalyzerAction::class,
        );
        $job->setOutputs([
            'analysis' => '${{ steps._.outputs.analysis }}',
        ]);

        return [$job];
    }

    public static function getName(): string
    {
        return 'core.file_analyzer';
    }

    public static function getDisplayName(): string
    {
        return 'File Analyzer';
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        foreach ($config['analyzers'] ?? [] as $analyzerConfig) {
            $name = $analyzerConfig['name'] ?? null;
            if (empty($name)) {
                throw new \InvalidArgumentException('Analyzer configuration error: "name" is not set.');
            }

            $analyzer = $this->fileAnalyzerRegistry->getAnalyzer($analyzerConfig['name']);

            try {
                $analyzerConfig = $this->fileAnalyzerRegistry->processConfiguration(
                    $analyzer,
                    $analyzerConfig
                );
                $analyzer->validateConfiguration($analyzerConfig);
            } catch (InvalidConfigurationException|\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('Analyzer "%s": %s', $analyzerConfig['name'] ?? 'unknown', $e->getMessage()));
            }
        }
    }
}
