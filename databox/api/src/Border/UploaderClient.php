<?php

declare(strict_types=1);

namespace App\Border;

use GuzzleHttp\Client;

class UploaderClient
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getCommit(string $baseUrl, string $id, string $token): array
    {
        return $this->doRequest(sprintf('%s/commits/%s', $baseUrl, $id), $token);
    }

    public function getAsset(string $baseUrl, string $id, string $token): array
    {
        return $this->doRequest(sprintf('%s/assets/%s', $baseUrl, $id), $token);
    }

    public function ackAsset(string $baseUrl, string $id, string $token): void
    {
        $this->client
            ->post(sprintf('%s/assets/%s/ack', $baseUrl, $id), [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
            ]);
    }

    public function doRequest(string $path, string $token): array
    {
        $response = $this->client
            ->get($path, [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
            ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }
}
