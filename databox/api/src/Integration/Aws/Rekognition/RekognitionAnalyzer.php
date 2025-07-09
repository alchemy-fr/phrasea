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
use App\Entity\Traits\AssetAnnotationsInterface;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationDataManager;
use App\Repository\Core\AttributeDefinitionRepository;

final readonly class RekognitionAnalyzer
{
    public function __construct(
        private AwsRekognitionClient $client,
        private IntegrationDataManager $dataManager,
        private FileFetcher $fileFetcher,
        private ApiBudgetLimiter $apiBudgetLimiter,
        private BatchAttributeManager $batchAttributeManager,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public function analyze(?Asset $asset, File $file, string $category, IntegrationConfig $config): array
    {
        $wsIntegration = $config->getWorkspaceIntegration();

        $methods = [
            'labels' => 'getImageLabels',
            'texts' => 'getImageTexts',
            'faces' => 'getImageFaces',
        ];

        $path = $this->fileFetcher->getFile($file);
        if (null !== $data = $this->dataManager->getData($wsIntegration, null, $file, $category)) {
            $result = json_decode($data->getValue(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

            $method = $methods[$category];
            $result = call_user_func([$this->client, $method], $path, $config);
            $this->dataManager->storeData($wsIntegration, null, $file, $category, json_encode($result, JSON_THROW_ON_ERROR));
        }

        if (!empty($result) && $asset instanceof Asset) {
            if (AwsRekognitionIntegration::LABELS === $category && !empty($config['labels']['attributes'] ?? [])) {
                $this->saveTextsToAttributes($category, $asset, array_map(fn (array $text): array => [
                    'value' => $text['Name'],
                    'confidence' => $text['Confidence'] / 100,
                    'annotations' => array_map(fn (array $instance): array => [
                        'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                        'x' => $instance['BoundingBox']['Left'] ?? null,
                        'y' => $instance['BoundingBox']['Top'] ?? null,
                        'w' => $instance['BoundingBox']['Width'] ?? null,
                        'h' => $instance['BoundingBox']['Height'] ?? null,
                    ], $text['Instances'] ?? []),
                ], $result['Labels']), $config['labels']['attributes']);
            } elseif (AwsRekognitionIntegration::TEXTS === $category && !empty($config['texts']['attributes'] ?? [])) {
                $this->saveTextsToAttributes($category, $asset, array_map(function (array $text): array {
                    $box = $text['Geometry']['BoundingBox'] ?? [];

                    return [
                        'value' => $text['DetectedText'],
                        'confidence' => $text['Confidence'] / 100,
                        'annotations' => [[
                            'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                            'x' => $box['Left'] ?? null,
                            'y' => $box['Top'] ?? null,
                            'w' => $box['Width'] ?? null,
                            'h' => $box['Height'] ?? null,
                        ]],
                    ];
                }, array_filter($result['TextDetections'], fn (array $text): bool => 'LINE' === $text['Type'])), $config['texts']['attributes']);
            }
        }

        return $result;
    }

    protected function saveTextsToAttributes(string $category, Asset $asset, array $texts, array $attributes): void
    {
        foreach ($attributes as $attrConfig) {
            $attrDef = $this->attributeDefinitionRepository
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
            $i->originVendorContext = $category;
            $input->actions[] = $i;

            foreach ($texts as $text) {
                if (null === $threshold || $text['confidence'] >= $threshold) {
                    $i = new AttributeActionInput();
                    $i->action = BatchAttributeManager::ACTION_ADD;
                    $i->originVendor = AwsRekognitionIntegration::getName();
                    $i->originVendorContext = $category;
                    $i->origin = Attribute::ORIGIN_MACHINE;
                    $i->definitionId = $attrDef->getId();
                    $i->confidence = $text['confidence'];
                    $i->value = $text['value'];
                    $i->annotations = $text['annotations'] ?? [];
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
