<?php

namespace App\Integration\Phrasea\Expose;

use App\Entity\Integration\IntegrationToken;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
    ) {
    }

    private function create(IntegrationConfig $config, IntegrationToken $integrationToken): HttpClientInterface
    {
        return $this->clientFactory->create(
            $config['baseUrl'],
            $config['clientId'],
            $integrationToken,
        );
    }

    public function createPublications(IntegrationConfig $config, IntegrationToken $integrationToken, array $data): array
    {
        return $this->create($config, $integrationToken)
            ->request('POST', '/publications', [
                'json' => $data,
            ])
            ->toArray();
    }

    public function deletePublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/publications/'.$id)
        ;
    }
}
