<?php

namespace App\Integration\Phrasea\Expose;

use App\Integration\Phrasea\PhraseaClientFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
    )
    {
    }

    private function create(array $config): HttpClientInterface
    {
        return $this->clientFactory->create(
            $config['baseUrl'],
            $config['clientId'],
            $config['clientSecret'],
        );
    }

    public function getPublications(array $config): array
    {
        $res = $this->create($config)
            ->request('GET', '/publications')
            ->toArray();
    }
}
