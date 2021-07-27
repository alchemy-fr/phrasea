<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Entity\SubDefinition;

class AssetDeleteTest extends AbstractExposeTestCase
{
    public function testDeleteAssetOK(): void
    {
        $id = $this->createPublication();
        $assetId = $this->createAsset([
            'publication_id' => $id,
            'persist_file' => true,
        ]);

        $asset = $this->assertAssetExist($assetId, true);
        $path = $asset->getPath();
        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotAssetExist($assetId);
        $this->assertAssetFileDoesNotExist($path);
    }

    public function testDeleteAssetWithOwnerOK(): void
    {
        $id = $this->createPublication();
        $assetId = $this->createAsset([
            'publication_id' => $id,
            'ownerId' => AuthServiceClientTestMock::USER_UID,
            'persist_file' => true,
        ]);

        $asset = $this->assertAssetExist($assetId, true);
        $path = $asset->getPath();
        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotAssetExist($assetId);
        $this->assertAssetFileDoesNotExist($path);
    }

    public function testDeleteAssetWithAnotherUserWillReturn403(): void
    {
        $id = $this->createPublication();
        $assetId = $this->createAsset([
            'publication_id' => $id,
            'ownerId' => '42',
            'persist_file' => true,
        ]);

        $asset = $this->assertAssetExist($assetId, true);
        $path = $asset->getPath();
        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertAssetExist($assetId);
        $this->assertAssetFileExists($path);
    }

    public function testDeleteAssetWithSubDefinitionsOK(): void
    {
        $id = $this->createPublication();
        $assetId = $this->createAsset([
            'publication_id' => $id,
        ]);
        $subDef1Id = $this->createSubDefinition($assetId, [
            'name' => 'thumb',
        ]);
        $subDef2Id = $this->createSubDefinition($assetId, [
            'name' => 'preview',
        ]);
        $this->clearEmBeforeApiCall();

        $this->assertAssetExist($assetId);
        $this->assertSubDefinitionExist($subDef1Id);
        $this->assertSubDefinitionExist($subDef2Id);
        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/assets/'.$assetId
        );
        if (500 === $response->getStatusCode()) {
            var_dump($response->getContent());
        }
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotAssetExist($assetId);
        $this->assertNotSubDefinitionExist($subDef1Id);
        $this->assertNotSubDefinitionExist($subDef2Id);
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

    private function assertSubDefinitionExist(string $id): void
    {
        $obj = self::getEntityManager()->find(SubDefinition::class, $id);
        $this->assertInstanceOf(SubDefinition::class, $obj);
    }

    private function assertNotSubDefinitionExist(string $id): void
    {
        $obj = self::getEntityManager()->find(SubDefinition::class, $id);
        $this->assertTrue(null === $obj, 'Sub def not removed');
    }
}
