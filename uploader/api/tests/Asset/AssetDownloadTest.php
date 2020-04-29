<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class AssetDownloadTest extends AbstractAssetTest
{
    public function testAssetDownloadOK(): void
    {
        $this->commitAsset();
        [$response, $contents] = $this->requestDownload('secret_token');
        ob_start();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));

        $this->assertEquals(file_get_contents(self::SAMPLE_FILE), $contents);
    }

    public function testAssetDownloadWithoutToken(): void
    {
        [$response] = $this->requestDownload(null);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetDownloadWithInvalidBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('xxx', 'Bearer');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetDownloadWithValidBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload(AuthServiceClientTestMock::USER_TOKEN, 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAssetDownloadWithAdminBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload(AuthServiceClientTestMock::ADMIN_TOKEN, 'Bearer');
        ob_start();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAssetDownloadWithInvalidAssetToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('invalid_asset_token');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnCommittedAssetDownload(): void
    {
        [$response] = $this->requestDownload(AuthServiceClientTestMock::ADMIN_TOKEN, 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function requestDownload(?string $accessToken, $authType = 'AssetToken'): array
    {
        $token = null;
        if (null !== $accessToken) {
            $token = [$authType, $accessToken];
        }

        ob_start();
        $response = $this->request($token, 'GET', sprintf('/assets/%s/download', $this->assetId));
        $contents = ob_get_contents();
        ob_end_clean();

        return [
            $response,
            $contents,
        ];
    }
}
