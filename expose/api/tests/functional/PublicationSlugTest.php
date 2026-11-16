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
        $adminToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $response = $this->request($adminToken, 'GET', '/publications/slug-availability/foo');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $this->createPublication(['slug' => 'foo']);

        $response = $this->request($adminToken, 'GET', '/publications/slug-availability/foo');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSlugAvailabilityRequiresTheCreateRight(): void
    {
        $this->createPublication(['slug' => 'existing', 'enabled' => false]);

        foreach (['existing', 'unknown'] as $slug) {
            $response = $this->request(null, 'GET', '/publications/slug-availability/'.$slug);
            $this->assertEquals(401, $response->getStatusCode(), $slug);

            $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications/slug-availability/'.$slug);
            $this->assertEquals(403, $response->getStatusCode(), $slug);

            $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/slug-availability/'.$slug);
            $this->assertEquals(200, $response->getStatusCode(), $slug);
            $this->assertSame('unknown' === $slug, json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
        }
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

    public function testGetAssetBySlugsOfExpiredPublication(): void
    {
        $publication = $this->createPublication([
            'slug' => 'pub',
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'endDate' => new \DateTimeImmutable('-1 hour'),
        ]);
        $assetId = $this->createAssetWithSlug($publication, 'my-asset');
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::OTHER_USER_UID), 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($assetId, $json['id']);
    }

    public function testGetAssetBySlugsOfPublicationDisabledByItsProfile(): void
    {
        $profileId = $this->createProfile(['enabled' => false]);
        $publication = $this->createPublication([
            'slug' => 'pub',
            'enabled' => null,
            'profile_id' => $profileId,
        ]);
        $this->createAssetWithSlug($publication, 'my-asset');
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/pub/assets/my-asset');
        $this->assertEquals(404, $response->getStatusCode());
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
