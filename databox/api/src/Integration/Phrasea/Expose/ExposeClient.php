<?php

namespace App\Integration\Phrasea\Expose;

use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
    ) {
    }

    private function create(IntegrationConfig $config): HttpClientInterface
    {
        return $this->clientFactory->create(
            $config['baseUrl'],
            $config['clientId'],
            $config['clientSecret'],
        );
    }

    public function getPublications(IntegrationConfig $config): array
    {
        return $this->create($config)
            ->request('GET', '/publications')
            ->toArray();
    }
}
