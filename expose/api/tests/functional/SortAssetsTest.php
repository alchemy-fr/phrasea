<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;

class SortAssetsTest extends AbstractExposeTestCase
{
    public function testSortAssetsAsAdmin(): void
    {
        $publication = $this->createPublication();
        $asset1 = $this->createAsset($publication);
        $asset2 = $this->createAsset($publication);
        $asset3 = $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/publications/'.$publication->getId().'/sort-assets',
            ['order' => [$asset3, $asset1, $asset2]]
        );
        $this->assertTrue($response->isSuccessful(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($publication->getId(), $json['id']);

        $this->clearEmBeforeApiCall();
        $this->assertSame(1, $this->getPosition($asset1));
        $this->assertSame(2, $this->getPosition($asset2));
        $this->assertSame(0, $this->getPosition($asset3));
    }

    public function testSortAssetsWithoutOrderReturns400(): void
    {
        $publication = $this->createPublication();
        $this->createAsset($publication);

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/publications/'.$publication->getId().'/sort-assets',
            []
        );
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSortAssetsAsNonOwnerReturns403(): void
    {
        $publication = $this->createPublication(['ownerId' => '42']);
        $asset = $this->createAsset($publication);

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'POST',
            '/publications/'.$publication->getId().'/sort-assets',
            ['order' => [$asset]]
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSortAssetsOfUnknownPublicationReturns404(): void
    {
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/publications/00000000-0000-0000-0000-000000000000/sort-assets',
            ['order' => ['foo']]
        );
        $this->assertEquals(404, $response->getStatusCode(), $response->getContent());
    }

    private function getPosition(string $assetId): int
    {
        $asset = self::getEntityManager()->find(Asset::class, $assetId);
        $this->assertInstanceOf(Asset::class, $asset);

        return $asset->getPosition();
    }
}
