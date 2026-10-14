<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Publication;

class PublicationSlugTest extends AbstractExposeTestCase
{
    public function testSlugAvailability(): void
    {
        $response = $this->request(null, 'GET', '/publications/slug-availability/foo');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $this->createPublication(['slug' => 'foo']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/slug-availability/foo');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testGetAssetBySlugs(): void
    {
        $publication = $this->createPublication(['slug' => 'pub']);
        $assetId = $this->createAssetWithSlug($publication, 'my-asset');
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($assetId, $json['id']);
        $this->assertEquals('my-asset', $json['slug']);

        $response = $this->request(null, 'GET', '/publications/pub/assets/unknown');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(null, 'GET', '/publications/unknown/assets/my-asset');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetAssetBySlugsOfDisabledPublication(): void
    {
        $publication = $this->createPublication(['slug' => 'pub', 'enabled' => false]);
        $assetId = $this->createAssetWithSlug($publication, 'my-asset');
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($assetId, $json['id']);
    }

    private function createAssetWithSlug(Publication $publication, string $slug): string
    {
        $em = self::getEntityManager();
        $assetId = $this->createAsset($publication);
        $asset = $em->find(Asset::class, $assetId);
        $asset->setSlug($slug);
        $em->flush();

        return $assetId;
    }
}
