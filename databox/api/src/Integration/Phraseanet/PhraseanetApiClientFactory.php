<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use App\Integration\IntegrationPrivateOrigins;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhraseanetApiClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly IntegrationPrivateOrigins $privateOrigins,
    ) {
    }

    public function create(string $baseUri, string $oauthToken): HttpClientInterface
    {
        if (empty($oauthToken)) {
            throw new \InvalidArgumentException('Phraseanet token is empty');
        }

        return $this->privateOrigins->getClient($this->client, $baseUri)->withOptions([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'OAuth '.$oauthToken,
            ],
        ]);
    }
}
