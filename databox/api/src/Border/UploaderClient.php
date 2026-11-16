<?php

declare(strict_types=1);

namespace App\Border;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UploaderClient
{
    private const int MAX_DURATION = 60;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly UploaderOrigins $uploaderOrigins,
    ) {
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
        $url = sprintf('%s/assets/%s/ack', $baseUrl, $id);
        $this->getClient($url)
            ->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
                'json' => [],
                'max_duration' => self::MAX_DURATION,
            ]);
    }

    public function doRequest(string $path, string $token): array
    {
        $response = $this->getClient($path)
            ->request('GET', $path, [
                'headers' => [
                    'Authorization' => 'AssetToken '.$token,
                ],
                'max_duration' => self::MAX_DURATION,
            ]);

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Only the Uploader of this instance may be reached on the private network.
     */
    private function getClient(string $url): HttpClientInterface
    {
        return $this->uploaderOrigins->isUploaderApi($url) ? $this->client : new NoPrivateNetworkHttpClient($this->client);
    }
}
