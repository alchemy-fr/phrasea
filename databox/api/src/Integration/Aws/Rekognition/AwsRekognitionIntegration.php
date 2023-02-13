<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\FileFetcher;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\ApiBudgetLimiter;
use App\Integration\AssetOperationIntegrationInterface;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationDataManager;
use App\Util\FileUtil;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AwsRekognitionIntegration extends AbstractAwsIntegration implements AssetOperationIntegrationInterface, FileActionsIntegrationInterface
{
    private const ACTION_ANALYZE = 'analyze';

    private const CATEGORIES = [
        'labels',
        'texts',
        'faces',
    ];

    private AwsRekognitionClient $client;
    private ApiBudgetLimiter $apiBudgetLimiter;
    private IntegrationDataManager $dataManager;
    private FileFetcher $fileFetcher;
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(
        AwsRekognitionClient $client,
        IntegrationDataManager $dataManager,
        FileFetcher $fileFetcher,
        ApiBudgetLimiter $apiBudgetLimiter,
        BatchAttributeManager $batchAttributeManager
    ) {
        $this->client = $client;
        $this->dataManager = $dataManager;
        $this->fileFetcher = $fileFetcher;
        $this->apiBudgetLimiter = $apiBudgetLimiter;
        $this->batchAttributeManager = $batchAttributeManager;
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
                    ->booleanNode('processIncoming')
                        ->defaultFalse()
                        ->info('Analyze all incoming assets automatically')
                    ->end();

            return $treeBuilder->getRootNode();
        };

        $this->addCredentialConfigNode($builder);
        $this->addRegionConfigNode($builder);

        foreach (self::CATEGORIES as $category) {
            $n = $addNode($category);
            if (in_array($category, ['labels', 'texts'], true)) {
                $n
                    ->children()
                        ->arrayNode('attributes')
                        ->info('Save results in attributes (multi-valued string)')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('name')->isRequired()->cannotBeEmpty()->info('Attribute slug')->end()
                                ->floatNode('threshold')->example(.5)->info('Minimum confidence to be saved into attribute')->end()
                            ->end()
                        ->end()
                    ->end()
                ;
            }
            $builder->append($n);
        }

        $builder->append($this->createBudgetLimitConfigNode(true));
    }

    public function handleAsset(Asset $asset, array $config): void
    {
        if (!$asset->getSource()) {
            return;
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            if ($config[$category]['enabled'] && $config[$category]['processIncoming']) {
                $categories[] = $category;
            }
        }

        $result = $this->analyze($asset->getSource(), $config, $categories);

        if (!empty($result['labels']) && !empty($config['labels']['attributes'] ?? [])) {
            $this->saveTextsToAttributes($asset, array_map(function (array $text): array {
                return [
                    'value' => $text['Name'],
                    'confidence' => $text['Confidence'],
                ];
            }, $result['labels']['Labels']), $config['labels']['attributes']);
        }
        if (!empty($result['texts']) && !empty($config['texts']['attributes'] ?? [])) {
            $this->saveTextsToAttributes($asset, array_map(function (array $text): array {
                return [
                    'value' => $text['DetectedText'],
                    'confidence' => $text['Confidence'],
                ];
            }, array_filter($result['texts']['TextDetections'], function (array $text): bool {
                return 'LINE' === $text['Type'];
            })), $config['texts']['attributes']);
        }
    }

    private function saveTextsToAttributes(Asset $asset, array $texts, array $attributes): void
    {
        foreach ($attributes as $attrConfig) {
            $attrDef = $this->batchAttributeManager->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $attrConfig['name']);
            $threshold = $attrConfig['threshold'] ?? null;
            if (!$attrDef->isMultiple()) {
                throw new InvalidArgumentException(sprintf('Attribute "%s" must be multi-valued', $attrDef->getId()));
            }

            $input = new AssetAttributeBatchUpdateInput();
            $i = new AttributeActionInput();
            $i->definitionId = $attrDef->getId();
            $i->action = BatchAttributeManager::ACTION_DELETE;
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->originVendor = self::getName();
            $input->actions[] = $i;

            foreach ($texts as $text) {
                if (null === $threshold || $threshold < $text['confidence']) {
                    $i = new AttributeActionInput();
                    $i->action = BatchAttributeManager::ACTION_ADD;
                    $i->originVendor = self::getName();
                    $i->origin = Attribute::ORIGIN_MACHINE;
                    $i->definitionId = $attrDef->getId();
                    $i->confidence = $text['confidence'];
                    $i->value = $text['value'];
                    $input->actions[] = $i;
                }
            }

            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );
        }
    }

    public function handleFileAction(string $action, Request $request, File $file, array $config): Response
    {
        switch ($action) {
            case self::ACTION_ANALYZE:
                $payload = $this->analyze($file, $config, [$request->request->get('category')]);

                return new JsonResponse($payload);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function analyze(File $file, array $config, array $categories): array
    {
        if (empty($categories)) {
            return [];
        }

        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $config['workspaceIntegration'];

        $methods = [
            'labels' => 'getImageLabels',
            'texts' => 'getImageTexts',
            'faces' => 'getImageFaces',
        ];

        $path = $this->fileFetcher->getFile($file);
        $result = [];
        $missing = [];
        foreach ($categories as $category) {
            if (null !== $data = $this->dataManager->getData($wsIntegration, $file, $category)) {
                $result[$category] = \GuzzleHttp\json_decode($data->getValue(), true);
            } else {
                $missing[] = $category;
            }
        }

        $this->apiBudgetLimiter->acceptIntegrationApiCall($config, count($missing));

        foreach ($missing as $category) {
            $method = $methods[$category];
            $result[$category] = call_user_func([$this->client, $method], $path, $config);
            $this->dataManager->storeData($wsIntegration, $file, $category, \GuzzleHttp\json_encode($result[$category]));
        }

        return $result;
    }

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, array $config): array
    {
        $output = [];
        foreach (self::CATEGORIES as $category) {
            $output[$category] = [
                'enabled' => $config[$category]['enabled'],
            ];
        }

        return $output;
    }

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return $asset->getSource() && $this->supportFile($asset->getSource());
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
