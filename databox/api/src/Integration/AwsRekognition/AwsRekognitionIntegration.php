<?php

declare(strict_types=1);

namespace App\Integration\AwsRekognition;

use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\AssetOperationIntegrationInterface;
use App\Integration\IntegrationDataManager;
use App\Util\FileUtil;
use InvalidArgumentException;
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

    private AwsRekognitionClient $client;
    private IntegrationDataManager $dataManager;
    private FileFetcher $fileFetcher;

    public function __construct(
        AwsRekognitionClient $client,
        IntegrationDataManager $dataManager,
        FileFetcher $fileFetcher
    ) {
        $this->client = $client;
        $this->dataManager = $dataManager;
        $this->fileFetcher = $fileFetcher;
    }

    public function getConfiguration(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        $rootNode = $treeBuilder->getRootNode();

        $addNode = function (string $name): NodeDefinition {
            $treeBuilder = new TreeBuilder($name);

            $treeBuilder->getRootNode()
                ->canBeEnabled()
                ->children()
                    ->booleanNode('processIncoming')
                    ->info('Analyze all incoming assets automatically')
                ->end();

            return $treeBuilder->getRootNode();
        };

        $rootNode
            ->children()
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
                ->append($addNode('labels'))
                ->append($addNode('texts'))
                ->append($addNode('faces'))
            ->end()
        ;

        return $treeBuilder;
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        if (!$options['analyzeIncoming']) {
            return;
        }

        if ($asset->getFile()) {
            $this->analyze($asset->getFile(), $options);
        }
    }

    public function handleFileAction(string $action, Request $request, File $file, array $options): Response
    {
        switch ($action) {
            case self::ACTION_ANALYZE:
                $payload = $this->analyze($file, $options, $request->request->get('category'));

                return new JsonResponse($payload);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function analyze(File $file, array $options, ?string $category = null): array
    {
        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $options['workspaceIntegration'];

        $categories = [
            'labels' => 'getImageLabels',
            'texts' => 'getImageTexts',
            'faces' => 'getImageFaces',
        ];

        if ($category) {
            $categories = [$category => $categories[$category]];
        }

        $shouldAnalyze = false;
        foreach ($categories as $key => $method) {
            if ($options[$key]) {
                $shouldAnalyze = true;
                break;
            }
        }

        if (!$shouldAnalyze) {
            return [];
        }

        $path = $this->fileFetcher->getFile($file);
        $result = [];
        foreach ($categories as $key => $method) {
            if (null !== $data = $this->dataManager->getData($wsIntegration, $file, $key)) {
                $result[$key] = \GuzzleHttp\json_decode($data->getValue(), true);
            } else {
                $result[$key] = call_user_func([$this->client, $method], $path, $options);
                $this->dataManager->storeData($wsIntegration, $file, $key, \GuzzleHttp\json_encode($result[$key]));
            }
        }

        return $result;
    }

    public function resolveClientOptions(WorkspaceIntegration $workspaceIntegration, array $options): array
    {
        return [
            'labels' => $options['labels'],
            'texts' => $options['texts'],
            'faces' => $options['faces'],
        ];
    }

    public function supportsAsset(Asset $asset, array $options): bool
    {
        return $asset->getFile() && $this->supportFile($asset->getFile());
    }

    private function supportFile(File $file): bool
    {
        return FileUtil::isImageType($file->getType());
    }

    public function supportsFileActions(File $file, array $options): bool
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
