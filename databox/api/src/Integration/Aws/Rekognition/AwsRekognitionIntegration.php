<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Alchemy\Workflow\Model\Workflow;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Action\FileUserActionsTrait;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Integration\Aws\Rekognition\Message\RekognitionAnalyze;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\UserActionsIntegrationInterface;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class AwsRekognitionIntegration extends AbstractAwsIntegration implements WorkflowIntegrationInterface, UserActionsIntegrationInterface
{
    use FileUserActionsTrait;

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
        private readonly MessageBusInterface $bus,
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

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
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

    public function handleUserAction(string $action, Request $request, IntegrationConfig $config): ?Response
    {
        $file = $this->getFile($request);
        switch ($action) {
            case self::ACTION_ANALYZE:
                $this->bus->dispatch(new RekognitionAnalyze(
                    $file->getId(),
                    $config->getIntegrationId(),
                    $request->request->get('category')
                ));
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
    }

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationConfig $config): array
    {
        $output = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $output[$category] = [
                'enabled' => $config[$category]['enabled'],
            ];
        }

        return $output;
    }

    public static function getName(): string
    {
        return 'aws.rekognition';
    }

    public static function getTitle(): string
    {
        return 'AWS Rekognition';
    }

    public function getSupportedContexts(): array
    {
        return [IntegrationContext::AssetView];
    }
}
