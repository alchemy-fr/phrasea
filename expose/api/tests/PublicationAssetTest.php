<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class PublicationAssetTest extends AbstractTestCase
{
    public function testAddAssetToPublicationOK(): void
    {
        $publicationId = $this->createPublication();
        $assetId = $this->createAsset([
            'description' => 'asset desc',
        ]);
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publication-assets', [
            'asset' => '/assets/'.$assetId,
            'publication' => '/publications/'.$publicationId,
            'description' => 'Overridden description',
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('publication', $json);
        $this->assertArrayHasKey('asset', $json);
        $this->assertEquals($publicationId, $json['publication']['id']);
        $this->assertEquals($assetId, $json['asset']['id']);
        $this->assertEquals('Overridden description', $json['asset']['description']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayNotHasKey('description', $json);
        $id = $json['id'];

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publication-assets/'.$id);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals($id, $json['id']);
        $this->assertArrayHasKey('publication', $json);
        $this->assertArrayHasKey('asset', $json);
        $this->assertEquals($publicationId, $json['publication']['id']);
        $this->assertEquals($assetId, $json['asset']['id']);
        $this->assertEquals('Overridden description', $json['asset']['description']);
    }

    public function testAddAssetToPublicationAsARandomUser(): void
    {
        $publicationId = $this->createPublication();
        $assetId = $this->createAsset([
            'description' => 'asset desc',
        ]);
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'POST', '/publication-assets', [
            'asset' => '/assets/'.$assetId,
            'publication' => '/publications/'.$publicationId,
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddAssetToPublicationAnonymously(): void
    {
        $publicationId = $this->createPublication();
        $assetId = $this->createAsset([
            'description' => 'asset desc',
        ]);
        $response = $this->request(null, 'POST', '/publication-assets', [
            'asset' => '/assets/'.$assetId,
            'publication' => '/publications/'.$publicationId,
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
