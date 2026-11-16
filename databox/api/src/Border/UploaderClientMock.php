<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
#[AsAlias(id: UploaderClient::class)]
class UploaderClientMock extends UploaderClient
{
    private array $acknowledgedAssets = [];

    /**
     * The uploading user sent with the commits: the owner of the test workspace by default.
     */
    public string $userId = KeycloakClientTestMock::ADMIN_UID;

    /**
     * Merged into the uploaded asset, as returned by the Uploader.
     */
    public array $assetData = [];

    #[\Override]
    public function getCommit(string $baseUrl, string $id, string $token): array
    {
        return [
            'id' => $id,
            'userId' => $this->userId,
            'assets' => [
                '42',
            ],
        ];
    }

    #[\Override]
    public function getAsset(string $baseUrl, string $id, string $token): array
    {
        return array_merge([
            'originalName' => 'test_file.png',
            'mimeType' => 'image/png',
            'size' => 42,
            'url' => 'http://localhost/path/to/test_file.png',
            'data' => [
            ],
            'formData' => [
            ],
            'fromUploadLink' => false,
        ], $this->assetData);
    }

    #[\Override]
    public function ackAsset(string $baseUrl, string $id, string $token): void
    {
        $this->acknowledgedAssets[] = $id;
    }

    public function getAcknowledgedAssets(): array
    {
        return $this->acknowledgedAssets;
    }
}
