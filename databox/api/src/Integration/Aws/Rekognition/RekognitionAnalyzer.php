<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IntegrationDataManager;

final class RekognitionAnalyzer
{
    public function __construct(
        private readonly AwsRekognitionClient $client,
        private readonly IntegrationDataManager $dataManager,
        private readonly FileFetcher $fileFetcher,
        private readonly ApiBudgetLimiter $apiBudgetLimiter,
    )
    {
    }

    public function analyze(File $file, string $category, array $config): array
    {
        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $config['workspaceIntegration'];

        $methods = [
            'labels' => 'getImageLabels',
            'texts' => 'getImageTexts',
            'faces' => 'getImageFaces',
        ];

        $path = $this->fileFetcher->getFile($file);
        if (null !== $data = $this->dataManager->getData($wsIntegration, $file, $category)) {
            $result = \GuzzleHttp\json_decode($data->getValue(), true);
        } else {
            $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

            $method = $methods[$category];
            $result = call_user_func([$this->client, $method], $path, $config);
            $this->dataManager->storeData($wsIntegration, $file, $category, \GuzzleHttp\json_encode($result));
        }

        return $result;
    }
}
