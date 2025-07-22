<?php

declare(strict_types=1);

namespace App\Border;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class UploaderClient
{
    public function __construct(private readonly HttpClientInterface $client)
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
            ->request('POST', sprintf('%s/assets/%s/ack', $baseUrl, $id), [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
                'json' => [],
            ]);
    }

    public function doRequest(string $path, string $token): array
    {
        $response = $this->client
            ->request('GET', $path, [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
            ]);

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
