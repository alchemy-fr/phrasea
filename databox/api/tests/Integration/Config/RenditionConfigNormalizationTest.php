<?php

declare(strict_types=1);

namespace App\Tests\Integration\Config;

use App\Entity\Core\RenditionDefinition;
use App\Integration\Aws\Rekognition\AwsRekognitionIntegration;
use App\Integration\Blurhash\BlurhashIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Integration\Core\Watermark\WatermarkIntegration;
use App\Integration\Happyscribe\HappyscribeIntegration;
use App\Integration\IntegrationRegistry;
use App\Service\Storage\RenditionManager;
use App\Tests\AbstractDataboxTestCase;

class RenditionConfigNormalizationTest extends AbstractDataboxTestCase
{
    /**
     * @dataProvider provideIntegrations
     */
    public function testRenditionNamesAreStoredAsIdsAndExposedAsNames(string $integrationName, array $config, array $expectedNormalized): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        /** @var RenditionManager $renditionManager */
        $renditionManager = self::getService(RenditionManager::class);
        $preview = $renditionManager->getRenditionDefinitionByName($workspace->getId(), 'Preview');
        $thumbnail = $renditionManager->getRenditionDefinitionByName($workspace->getId(), 'Thumbnail');
        $ids = ['{preview}' => $preview->getId(), '{thumbnail}' => $thumbnail->getId()];

        $expectedNormalized = $this->replaceIds($expectedNormalized, $ids);

        $integration = self::getService(IntegrationRegistry::class)->getStrictIntegration($integrationName);

        $normalized = $integration->normalizeConfiguration($config, $workspace);
        $this->assertSame($expectedNormalized, $normalized);

        // Normalizing again is stable
        $this->assertSame($expectedNormalized, $integration->normalizeConfiguration($normalized, $workspace));

        $this->assertSame($config, $integration->denormalizeConfiguration($normalized, $workspace));
    }

    public function testUnknownRenditionIsRejected(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $integration = self::getService(IntegrationRegistry::class)->getStrictIntegration(SimilarityIntegration::getName());

        $this->expectException(\InvalidArgumentException::class);
        $integration->normalizeConfiguration(['rendition' => 'does-not-exist'], $workspace);
    }

    public function testRenditionLookupsAcceptDefinitionIds(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        /** @var RenditionManager $renditionManager */
        $renditionManager = self::getService(RenditionManager::class);
        $preview = $renditionManager->getRenditionDefinitionByName($workspace->getId(), 'Preview');

        $byId = $renditionManager->getRenditionDefinitionByName($workspace->getId(), $preview->getId());
        $this->assertInstanceOf(RenditionDefinition::class, $byId);
        $this->assertSame($preview->getId(), $byId->getId());

        $asset = $this->createAsset(['workspace' => $workspace]);
        $this->assertNull($renditionManager->getAssetRenditionByName($asset->getId(), $preview->getId()), 'No rendition built yet, but the ID form is understood');
    }

    public static function provideIntegrations(): iterable
    {
        yield 'rendition (core)' => [
            RenditionIntegration::getName(),
            ['renditions' => ['Preview', 'Thumbnail']],
            ['renditions' => ['{preview}', '{thumbnail}']],
        ];
        yield 'similarity' => [
            SimilarityIntegration::getName(),
            ['rendition' => 'Preview'],
            ['rendition' => '{preview}'],
        ];
        yield 'face recognition' => [
            FaceRecognitionIntegration::getName(),
            ['rendition' => 'Preview', 'processIncoming' => true],
            ['rendition' => '{preview}', 'processIncoming' => true],
        ];
        yield 'blurhash' => [
            BlurhashIntegration::getName(),
            ['rendition' => 'Thumbnail'],
            ['rendition' => '{thumbnail}'],
        ];
        yield 'blurhash without rendition' => [
            BlurhashIntegration::getName(),
            ['attribute' => 'blurhash'],
            ['attribute' => 'blurhash'],
        ];
        yield 'happyscribe' => [
            HappyscribeIntegration::getName(),
            ['apiKey' => 'x', 'rendition' => 'Preview'],
            ['apiKey' => 'x', 'rendition' => '{preview}'],
        ];
        yield 'watermark' => [
            WatermarkIntegration::getName(),
            ['attributeName' => 'watermark', 'applyToRenditions' => ['Thumbnail', 'Preview']],
            ['attributeName' => 'watermark', 'applyToRenditions' => ['{thumbnail}', '{preview}']],
        ];
        yield 'aws rekognition' => [
            AwsRekognitionIntegration::getName(),
            ['labels' => ['enabled' => true, 'rendition' => 'Preview'], 'faces' => ['enabled' => true], 'texts' => ['rendition' => 'Thumbnail']],
            ['labels' => ['enabled' => true, 'rendition' => '{preview}'], 'faces' => ['enabled' => true], 'texts' => ['rendition' => '{thumbnail}']],
        ];
    }

    private function replaceIds(array $config, array $ids): array
    {
        array_walk_recursive($config, function (&$value) use ($ids): void {
            if (is_string($value) && isset($ids[$value])) {
                $value = $ids[$value];
            }
        });

        return $config;
    }
}
