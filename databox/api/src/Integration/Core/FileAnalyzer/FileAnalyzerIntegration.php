<?php

declare(strict_types=1);

namespace App\Integration\Core\FileAnalyzer;

use Alchemy\Workflow\Model\Workflow;
use App\Border\FileAnalyzer\FileAnalyzerConfigHelper;
use App\Border\FileAnalyzerRegistry;
use App\Documentation\ConfigurationReferenceDumper;
use App\Integration\AbstractIntegration;
use App\Integration\Config\ConfigSchemaCustomizerInterface;
use App\Integration\Config\ConfigSchemaDumper;
use App\Integration\ExtraReferenceIntegrationInterface;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class FileAnalyzerIntegration extends AbstractIntegration implements WorkflowIntegrationInterface, ExtraReferenceIntegrationInterface, ConfigSchemaCustomizerInterface
{
    public const string NAME = 'core.file_analyzer';

    public function __construct(
        private readonly FileAnalyzerRegistry $fileAnalyzerRegistry,
        private readonly ConfigurationReferenceDumper $referenceDumper,
        private readonly ConfigSchemaDumper $schemaDumper,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $actions = FileAnalyzerAssetActionEnum::getValues();

        // @formatter:off
        $builder
            ->arrayNode('analyzers')
                ->prototype('variable')
                ->end()
            ->end()
            ->arrayNode('actions_on_reject')
                ->info('One of: '.implode(', ', $actions))
                ->enumPrototype()
                    ->values($actions)
                ->end()
            ->end()
            ->booleanNode('skipNonSourceFiles')
                ->defaultFalse()
                ->info('Skip the analysis of files that are not the source file of the asset (e.g. renditions).')
            ->end();
        // @formatter:on
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
            'analyzed' => '${{ steps._.outputs.analyzed }}',
        ]);

        return [$job];
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public static function getDisplayName(): string
    {
        return 'File Analyzer';
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Processing,
            IntegrationCategory::Ingest,
        ];
    }

    public function getExtraReferenceSections(): array
    {
        $sections = [];
        foreach ($this->fileAnalyzerRegistry->getAnalyzers() as $name => $analyzer) {
            $documentation = $analyzer->getDocumentation();

            $sections[] = [
                'name' => $name,
                'description' => trim($documentation->getHeader()) ?: null,
                'reference' => $this->referenceDumper->dumpDocumentation($documentation),
            ];
        }

        return $sections;
    }

    /**
     * Each analyzer has its own options: the analyzers list is described by one variant per analyzer.
     */
    public function customizeConfigSchema(array $schema): array
    {
        $variants = [];
        foreach ($this->fileAnalyzerRegistry->getAnalyzers() as $name => $analyzer) {
            $treeBuilder = FileAnalyzerConfigHelper::createBaseTree($name);
            $analyzer->buildConfiguration($treeBuilder->getRootNode()->children());

            $variants[$name] = $this->schemaDumper->dump($treeBuilder->buildTree());
        }

        $schema['properties']['analyzers']['items'] = [
            'type' => 'object',
            'discriminator' => 'name',
            'variants' => $variants,
        ];

        return $schema;
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
