<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\FileFetcher;
use App\Attribute\AttributeManager;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IntegrationDataManager;

final readonly class RekognitionAnalyzer
{
    public function __construct(
        private AwsRekognitionClient $client,
        private IntegrationDataManager $dataManager,
        private FileFetcher $fileFetcher,
        private ApiBudgetLimiter $apiBudgetLimiter,
        private BatchAttributeManager $batchAttributeManager,
        private AttributeManager $attributeManager,
    ) {
    }

    public function analyze(?Asset $asset, File $file, string $category, array $config): array
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
            $result = json_decode($data->getValue(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

            $method = $methods[$category];
            $result = call_user_func([$this->client, $method], $path, $config);
            $this->dataManager->storeData($wsIntegration, $file, $category, \GuzzleHttp\json_encode($result));
        }

        if (!empty($result) && $asset instanceof Asset) {
            if (AwsRekognitionIntegration::LABELS === $category && !empty($config['labels']['attributes'] ?? [])) {
                $this->saveTextsToAttributes($asset, array_map(fn (array $text): array => [
                    'value' => $text['Name'],
                    'confidence' => $text['Confidence'],
                ], $result['Labels']), $config['labels']['attributes']);
            } elseif (AwsRekognitionIntegration::TEXTS === $category && !empty($config['texts']['attributes'] ?? [])) {
                $this->saveTextsToAttributes($asset, array_map(fn (array $text): array => [
                    'value' => $text['DetectedText'],
                    'confidence' => $text['Confidence'],
                ], array_filter($result['TextDetections'], fn (array $text): bool => 'LINE' === $text['Type'])), $config['texts']['attributes']);
            }
        }

        return $result;
    }

    protected function saveTextsToAttributes(Asset $asset, array $texts, array $attributes): void
    {
        foreach ($attributes as $attrConfig) {
            $attrDef = $this->attributeManager
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $attrConfig['name'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $attrConfig['name'], $asset->getWorkspaceId()));

            $threshold = $attrConfig['threshold'] ?? null;
            if (!$attrDef->isMultiple()) {
                throw new \InvalidArgumentException(sprintf('Attribute "%s" must be multi-valued', $attrDef->getId()));
            }

            $input = new AssetAttributeBatchUpdateInput();
            $i = new AttributeActionInput();
            $i->definitionId = $attrDef->getId();
            $i->action = BatchAttributeManager::ACTION_DELETE;
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->originVendor = AwsRekognitionIntegration::getName();
            $input->actions[] = $i;

            foreach ($texts as $text) {
                if (null === $threshold || $threshold < $text['confidence']) {
                    $i = new AttributeActionInput();
                    $i->action = BatchAttributeManager::ACTION_ADD;
                    $i->originVendor = AwsRekognitionIntegration::getName();
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
}
