<?php

namespace App\Border;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
#[AsAlias(id: UploaderClient::class)]
class UploaderClientMock extends UploaderClient
{
    private array $acknowledgedAssets = [];

    public function getCommit(string $baseUrl, string $id, string $token): array
    {
        return [
            'id' => $id,
            'userId' => 'test_user',
            'assets' => [
                '42',
            ],
        ];
    }

    public function getAsset(string $baseUrl, string $id, string $token): array
    {
        return [
            'originalName' => 'test_file.txt',
            'mimeType' => 'text/plain',
            'size' => 42,
            'url' => 'http://localhost/path/to/test_file.txt',
            'data' => [
            ],
            'formData' => [
            ],
        ];
    }

    public function ackAsset(string $baseUrl, string $id, string $token): void
    {
        $this->acknowledgedAssets[] = $id;
    }

    public function getAcknowledgedAssets(): array
    {
        return $this->acknowledgedAssets;
    }
}
