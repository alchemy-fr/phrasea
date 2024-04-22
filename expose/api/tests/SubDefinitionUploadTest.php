<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SubDefinitionUploadTest extends AbstractExposeTestCase
{
    public function testUploadSubDefOK(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/sub-definitions', [
                'asset_id' => $assetId,
                'name' => 'thumb',
            ], [
                'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
            ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (201 !== $response->getStatusCode()) {
            dump($response->getContent());
        }
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertMatchesUuid($json['id']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);

        $this->clearEmBeforeApiCall();

        // Test the sub definition is added to the asset
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'GET',
            '/assets/'.$assetId.'/sub-definitions');

        if (200 !== $response->getStatusCode()) {
            dump($response->getContent());
        }
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $json);
        $this->assertEquals('thumb', $json[0]['name']);
    }
}
