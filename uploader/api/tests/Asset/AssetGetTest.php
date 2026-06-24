<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AssetGetTest extends AbstractAssetTest
{
    public function testAssetGetOK(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('secret_token');

        $this->assertEquals(200, $response->getStatusCode());
        $contents = $response->toArray();
        $this->assertEquals('application/ld+json; charset=utf-8', $response->getHeaders()['content-type'][0]);
        $this->assertEquals('foo.jpg', $contents['originalName']);
        $this->assertEquals(['foo' => 'bar'], $contents['formData']);
        $this->assertEquals(846, $contents['size']);
        $this->assertEquals('image/jpeg', $contents['mimeType']);
        $this->assertArrayNotHasKey('token', $contents);
        $this->assertArrayNotHasKey('path', $contents);
        $this->assertArrayHasKey('createdAt', $contents);
    }

    public function testAssetGetWithoutToken(): void
    {
        $response = $this->requestGet(null);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetGetWithInvalidBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('xxx', 'Bearer');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetGetWithValidBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAssetGetWithAdminBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'Bearer');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAssetGetWithInvalidAssetToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('invalid_asset_token');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnCommittedAssetGet(): void
    {
        $response = $this->requestGet(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function requestGet(?string $accessToken, $authType = 'AssetToken'): ResponseInterface
    {
        $client = static::createClient();

        return $client->request('GET', sprintf('/assets/%s', $this->assetId), [
            'headers' => $accessToken ? [
                'Authorization' => sprintf('%s %s', $authType, $accessToken),
            ] : [],
        ]);
    }
}
