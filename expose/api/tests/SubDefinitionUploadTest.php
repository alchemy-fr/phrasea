<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SubDefinitionUploadTest extends AbstractExposeTestCase
{
    public function testUploadSubDefOK(): void
    {
        $publicationId = $this->createPublication();
        $assetId = $this->createAsset(['publication_id' => $publicationId]);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/sub-definitions', [
            'asset_id' => $assetId,
            'name' => 'thumb',
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);

        $this->clearEmBeforeApiCall();

        // Test the sub definition is added to the asset
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/assets/'.$assetId.'/sub-definitions');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(1, count($json));
        $this->assertEquals('thumb', $json[0]['name']);
    }
}
