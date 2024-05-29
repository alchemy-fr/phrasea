<?php

namespace App\Integration\Phrasea;

use App\Entity\Integration\IntegrationToken;
use App\Integration\Auth\IntegrationTokenManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PhraseaClientFactory
{
    public function __construct(
        private HttpClientInterface $client,
        private IntegrationTokenManager $integrationTokenManager,
    ) {
    }

    public function create(string $baseUrl, string $clientId, IntegrationToken $integrationToken): HttpClientInterface
    {
        $client = $this->client->withOptions([
            'base_uri' => $baseUrl,
        ]);

        $accessToken = $this->integrationTokenManager->getAccessToken($integrationToken, function (string $refreshToken) use ($client, $clientId): array {
            return $client->request('POST', '/oauth/v2/token', [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'refresh_token' => $refreshToken,
                ],
            ])->toArray();
        });

        return $client->withOptions([
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);
    }
}
