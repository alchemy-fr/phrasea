<?php

declare(strict_types=1);

namespace App\Integration\AwsRekognition;

use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AbstractIntegration;
use App\Integration\AssetActionIntegrationInterface;
use App\Integration\AssetOperationIntegrationInterface;
use App\Integration\IntegrationDataManager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AwsRekognitionIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface, AssetActionIntegrationInterface
{
    private const ACTION_ANALYZE = 'analyze';

    private const DATA_LABEL = 'image_labels';
    private const DATA_TEXT = 'image_text';
    private const DATA_FACES = 'image_faces';

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

    public function __construct(
        AwsRekognitionClient $client,
        IntegrationDataManager $dataManager
    ) {
        $this->client = $client;
        $this->dataManager = $dataManager;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('accessKeyId');
        $resolver->setRequired('accessKeySecret');
        $resolver->setDefaults([
            'analyzeIncoming' => false,
            'region' => 'eu-central-1',
        ]);
        $resolver->setAllowedValues('region', self::SUPPORTED_REGIONS);
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        if (!$options['analyzeIncoming']) {
            return;
        }

        $this->analyze($asset, $options);
    }

    public function handleAssetAction(string $action, Request $request, Asset $asset, array $options): Response
    {
        switch ($action) {
            case self::ACTION_ANALYZE:
                $payload = $this->analyze($asset, $options);

                return new JsonResponse($payload);
            default:
                throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }
    }

    private function analyze(Asset $asset, array $options): array
    {
        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $options['workspaceIntegration'];

        if (null !== $data = $this->dataManager->getData($wsIntegration, $asset, self::DATA_LABEL)) {
            return \GuzzleHttp\json_decode($data->getValue(), true);
        }

        $payload = $this->client->getImageLabels($asset->getFile(), $options);

        $this->dataManager->storeData($wsIntegration, $asset, self::DATA_LABEL, \GuzzleHttp\json_encode($payload));

        return $payload;
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
