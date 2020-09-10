<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Entity\Asset;

class AssetDeleteTest extends AbstractExposeTestCase
{
    public function testDeleteAssetOK(): void
    {
        $id = $this->createPublication();
        $assetId = $this->createAsset([
            'publication_id' => $id,
        ]);

        $this->assertAssetExist($assetId);
        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotAssetExist($assetId);
    }

    public function testDeleteNonExistingAssetWillReturn404(): void
    {
        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/assets/invalid-asset'
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteAssetByAssetId(): void
    {
        $id = $this->createPublication();
        $deletedIds = [];
        $notDeletedIds = [];

        $deletedIds[] = $this->createAsset([
            'publication_id' => $id,
            'asset_id' => 'foo',
        ]);
        $deletedIds[] = $this->createAsset([
            'publication_id' => $id,
            'asset_id' => 'foo',
        ]);
        $notDeletedIds[] = $this->createAsset([
            'publication_id' => $id,
            'asset_id' => 'bar',
        ]);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/assets/delete-by-asset-id/foo'
        );
        $this->assertEquals(204, $response->getStatusCode());

        foreach ($deletedIds as $deletedId) {
            $this->assertNotAssetExist($deletedId);
        }
        foreach ($notDeletedIds as $notDeletedId) {
            $this->assertAssetExist($notDeletedId);
        }
    }

    private function assertAssetExist(string $id): void
    {
        $asset = self::getEntityManager()->find(Asset::class, $id);
        $this->assertInstanceOf(Asset::class, $asset);
    }

    private function assertNotAssetExist(string $id): void
    {
        $asset = self::getEntityManager()->find(Asset::class, $id);
        $this->assertNull($asset);
    }
}
