<?php

declare(strict_types=1);

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

        $accessToken = $this->integrationTokenManager->getAccessToken(
            $integrationToken,
            $this->createTokenRenewer($baseUrl, $clientId),
        );

        return $client->withOptions([
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);
    }

    /**
     * @return \Closure(string $refreshToken): array
     */
    public function createTokenRenewer(string $baseUrl, string $clientId): \Closure
    {
        $client = $this->client->withOptions([
            'base_uri' => $baseUrl,
        ]);

        return fn (string $refreshToken): array => $client->request('POST', '/oauth/v2/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'refresh_token' => $refreshToken,
            ],
        ])->toArray();
    }
}
