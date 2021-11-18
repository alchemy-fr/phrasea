<?php

declare(strict_types=1);

namespace App\Border;

use GuzzleHttp\Client;

class UploaderClient
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getCommit(string $baseUrl, string $id, string $token): array
    {
        return $this->doRequest(sprintf('%s/commits/%s', $baseUrl, $id), $token);
    }

    public function getAsset(string $baseUrl, string $id, string $token): array
    {
        return $this->doRequest(sprintf('%s/assets/%s', $baseUrl, $id), $token);
    }

    public function doRequest(string $path, string $token): array
    {
        $response = $this->client
            ->get($path, [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ]
            ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }
}
