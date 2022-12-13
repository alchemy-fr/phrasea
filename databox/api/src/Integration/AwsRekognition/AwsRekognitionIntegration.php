<?php

declare(strict_types=1);

namespace App\Integration\AwsRekognition;

use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\ApiBudgetLimiter;
use App\Integration\AssetOperationIntegrationInterface;
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

class AwsRekognitionIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface, FileActionsIntegrationInterface
{
    private const ACTION_ANALYZE = 'analyze';

    private const SUPPORTED_REGIONS = [
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

    private const CATEGORIES = [
        'labels',
        'texts',
        'faces',
    ];

    private AwsRekognitionClient $client;
    private ApiBudgetLimiter $apiBudgetLimiter;
    private IntegrationDataManager $dataManager;
    private FileFetcher $fileFetcher;

    public function __construct(
        AwsRekognitionClient $client,
        IntegrationDataManager $dataManager,
        FileFetcher $fileFetcher,
        ApiBudgetLimiter $apiBudgetLimiter
    ) {
        $this->client = $client;
        $this->dataManager = $dataManager;
        $this->fileFetcher = $fileFetcher;
        $this->apiBudgetLimiter = $apiBudgetLimiter;
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

        $builder
            ->scalarNode('accessKeyId')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The AWS IAM Access Key ID')
            ->end()
            ->scalarNode('accessKeySecret')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The AWS IAM Access Key Secret')
            ->end()
            ->scalarNode('region')
                ->cannotBeEmpty()
                ->defaultValue('eu-central-1')
                ->example('us-east-2')
                ->validate()
                    ->ifNotInArray(self::SUPPORTED_REGIONS)
                    ->thenInvalid(sprintf('Invalid region "%%s". Supported ones are: "%s"', implode('", "', self::SUPPORTED_REGIONS)))
                ->end()
                ->info(sprintf('Supported regions are: "%s"', implode('", "', self::SUPPORTED_REGIONS)))
            ->end()
        ;

        /** @var NodeDefinition[] $nodes  */
        $nodes = [];
        foreach (self::CATEGORIES as $category) {
            $n = $addNode($category);
            if ($category === 'labels') {
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
        if (!$asset->getFile()) {
            return;
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            if ($config[$category]['analyzeIncoming']) {
                $categories[] = $category;
            }
        }

        $this->analyze($asset->getFile(), $config, $categories);
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
        return $asset->getFile() && $this->supportFile($asset->getFile());
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
