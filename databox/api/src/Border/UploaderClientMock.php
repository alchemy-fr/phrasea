<?php

namespace App\Border;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
#[AsAlias(id: UploaderClient::class)]
readonly class UploaderClientMock extends UploaderClient
{
    public function getCommit(string $baseUrl, string $id, string $token): array
    {
        return [
            'id' => $id,
            'userId' => 'test_user',
            'assets' => [],
        ];
    }
}
