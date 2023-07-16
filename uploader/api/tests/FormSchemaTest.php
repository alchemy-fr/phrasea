<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;

class FormSchemaTest extends AbstractUploaderTestCase
{
    public function testFormSchemaEditOK(): void
    {
        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'GET', '/form-schemas');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('[]', $response->getContent());

        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'POST', '/form-schemas', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('data', $json);

        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'GET', '/form-schemas');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $json);
        $this->assertEquals([
            'foo' => 'bar',
        ], $json[0]['data']);
    }

    public function testFormSchemaPostWithANonAdminUser(): void
    {
        $response = $this->request(OAuthClientTestMock::USER_TOKEN, 'POST', '/form-schemas', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testFormSchemaPostWithAnonymousUser(): void
    {
        $response = $this->request(null, 'POST', '/form-schemas', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testFormSchemaGetWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/form-schemas');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
