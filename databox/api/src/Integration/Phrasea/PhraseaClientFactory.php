<?php

namespace App\Integration\Phrasea;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PhraseaClientFactory
{
    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $tokenCache,
    ) {
    }

    public function create(string $baseUrl, string $clientId, string $clientSecret): HttpClientInterface
    {
        $client = $this->client->withOptions([
            'base_uri' => $baseUrl,
        ]);

        $token = $this->tokenCache->get(sprintf('t:%s:%s', $baseUrl, $clientId), function (ItemInterface $item) use ($client, $clientId, $clientSecret): array {
            $response = $client->request('POST', '/oauth/v2/token', [
                'form' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ])->toArray();

            $item->expiresAfter($response['expires_in'] - 2);

            return $response['access_token'];
        });

        return $client->withOptions([
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
    }
}
