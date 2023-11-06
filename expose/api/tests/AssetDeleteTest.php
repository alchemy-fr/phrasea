<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\SubDefinition;

class AssetDeleteTest extends AbstractExposeTestCase
{
    public function testDeleteAssetOK(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication, [
            'persist_file' => true,
        ]);

        $asset = $this->assertAssetExist($assetId, true);
        $path = $asset->getPath();
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotAssetExist($assetId);
        $this->assertAssetFileDoesNotExist($path);
    }

    public function testDeleteAssetWithAnotherUserWillReturn403(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication, [
            'ownerId' => '42',
            'persist_file' => true,
        ]);

        $asset = $this->assertAssetExist($assetId, true);
        $path = $asset->getPath();
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'DELETE',
            '/assets/'.$assetId
        );
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertAssetExist($assetId);
        $this->assertAssetFileExists($path);
    }

    public function testDeleteAssetWithSubDefinitionsOK(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
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
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'DELETE',
            '/assets/'.$assetId
        );
        if (500 === $response->getStatusCode()) {
            dump($response->getContent());
        }
        $this->assertEquals(204, $response->getStatusCode());
        $this->clearEmBeforeApiCall();
        $this->assertNotAssetExist($assetId);
        $this->assertNotSubDefinitionExist($subDef1Id);
        $this->assertNotSubDefinitionExist($subDef2Id);
    }

    public function testDeleteNonExistingAssetWillReturn404(): void
    {
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'DELETE',
            '/assets/invalid-asset'
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteAssetByAssetId(): void
    {
        $publication = $this->createPublication();
        $deletedIds = [];
        $notDeletedIds = [];

        $deletedIds[] = $this->createAsset($publication, [
            'asset_id' => 'foo',
        ]);
        $deletedIds[] = $this->createAsset($publication, [
            'asset_id' => 'foo',
        ]);
        $notDeletedIds[] = $this->createAsset($publication, [
            'asset_id' => 'bar',
        ]);

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
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
