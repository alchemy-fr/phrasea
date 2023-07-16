<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;
use Symfony\Component\HttpFoundation\Response;

class AssetGetTest extends AbstractAssetTest
{
    public function testAssetGetOK(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('secret_token');

        $contents = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
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
        $response = $this->requestGet(OAuthClientTestMock::USER_TOKEN, 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAssetGetWithAdminBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet(OAuthClientTestMock::ADMIN_TOKEN, 'Bearer');
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
        $response = $this->requestGet(OAuthClientTestMock::ADMIN_TOKEN, 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function requestGet(?string $accessToken, $authType = 'AssetToken'): Response
    {
        $token = null;
        if (null !== $accessToken) {
            $token = [$authType, $accessToken];
        }

        return $this->request($token, 'GET', sprintf('/assets/%s', $this->assetId));
    }
}
