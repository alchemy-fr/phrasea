<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AwsRekognitionIntegration extends AbstractAwsIntegration implements WorkflowIntegrationInterface, FileActionsIntegrationInterface
{
    private const ACTION_ANALYZE = 'analyze';

    final public const LABELS = 'labels';
    final public const TEXTS = 'texts';
    final public const FACES = 'faces';

    private const CATEGORIES = [
        self::LABELS => RekognitionLabelsAction::class,
        self::TEXTS => RekognitionTextsAction::class,
        self::FACES => RekognitionFacesAction::class,
    ];

    public function __construct(
        private readonly RekognitionAnalyzer $rekognitionAnalyzer,
    ) {
    }

    protected function getSupportedRegions(): array
    {
        return [
            'ap-northeast-1',
            'ap-northeast-2',
            'ap-south-1',
            'ap-southeast-1',
            'ap-southeast-2',
            'eu-central-1',
            'eu-west-1',
            'eu-west-2',
            'us-east-1',
            'us-east-2',
            'us-west-2',
        ];
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $addNode = function (string $name): NodeDefinition {
            $treeBuilder = new TreeBuilder($name);

            $treeBuilder->getRootNode()
                ->canBeEnabled()
                ->children()
                    ->scalarNode('rendition')
                        ->defaultNull()
                        ->info('Send rendition instead of source to AWS')
                    ->end()
                    ->booleanNode('processIncoming')
                        ->defaultFalse()
                        ->info('Analyze all incoming assets automatically')
                    ->end();

            return $treeBuilder->getRootNode();
        };

        $this->addCredentialConfigNode($builder);
        $this->addRegionConfigNode($builder);

        foreach (array_keys(self::CATEGORIES) as $category) {
            $n = $addNode($category);
            if (in_array($category, [self::LABELS, self::TEXTS], true)) {
                $n
                    ->children()
                        ->arrayNode('attributes')
                        ->info('Save results in attributes (multi-valued string)')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('name')->isRequired()->cannotBeEmpty()->info('Attribute slug')->end()
                                ->floatNode('threshold')->example('.5')->info('Minimum confidence to be saved into attribute')->end()
                            ->end()
                        ->end()
                    ->end()
                ;
            }
            $builder->append($n);
        }

        $builder->append($this->createBudgetLimitConfigNode(true));
    }

    public function getWorkflowJobDefinitions(array $config, Workflow $workflow): iterable
    {
        foreach (self::CATEGORIES as $category => $action) {
            if ($config[$category]['enabled'] && $config[$category]['processIncoming']) {
                yield WorkflowHelper::createIntegrationJob(
                    $config,
                    $action,
                    $category,
                    ucfirst($category),
                );
            }
        }
    }

    public function handleFileAction(string $action, Request $request, File $file, array $config): Response
    {
        switch ($action) {
            case self::ACTION_ANALYZE:
                $category = $request->request->get('category');
                $payload = $this->rekognitionAnalyzer->analyze(null, $file, $category, $config);

                return new JsonResponse([
                    $category => $payload,
                ]);
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, array $config): array
    {
        $output = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $output[$category] = [
                'enabled' => $config[$category]['enabled'],
            ];
        }

        return $output;
    }

    private function supportFile(File $file): bool
    {
        return FileUtil::isImageType($file->getType());
    }

    public function supportsFileActions(File $file, array $config): bool
    {
        return $this->supportFile($file);
    }

    public static function getName(): string
    {
        return 'aws.rekognition';
    }

    public static function getTitle(): string
    {
        return 'AWS Rekognition';
    }
}
