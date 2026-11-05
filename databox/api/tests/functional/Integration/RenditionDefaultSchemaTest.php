<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Integration\IntegrationManager;
use App\Integration\IntegrationRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Rendition names are workspace-specific: the form must not suggest one that may not exist.
 */
class RenditionDefaultSchemaTest extends KernelTestCase
{
    public function testFaceRecognitionRenditionIsOptionalWithoutDefault(): void
    {
        $rendition = $this->getRenditionSchema(FaceRecognitionIntegration::getName());

        $this->assertArrayNotHasKey('default', $rendition);
        $this->assertArrayNotHasKey('required', $rendition);
    }

    public function testSimilarityRenditionIsRequiredWithoutDefault(): void
    {
        $rendition = $this->getRenditionSchema(SimilarityIntegration::getName());

        $this->assertArrayNotHasKey('default', $rendition);
        $this->assertTrue($rendition['required'] ?? false);
    }

    private function getRenditionSchema(string $integrationName): array
    {
        self::bootKernel();
        $container = static::getContainer();
        $integration = $container->get(IntegrationRegistry::class)->getStrictIntegration($integrationName);
        $schema = $container->get(IntegrationManager::class)->getIntegrationSchema($integration);

        $this->assertArrayHasKey('rendition', $schema['properties']);
        $this->assertSame('rendition', $schema['properties']['rendition']['widget'] ?? null);

        return $schema['properties']['rendition'];
    }
}
