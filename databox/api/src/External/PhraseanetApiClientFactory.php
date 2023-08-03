<?php

declare(strict_types=1);

namespace App\External;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhraseanetApiClientFactory
{
    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    public function create(string $baseUri, string $oauthToken): HttpClientInterface
    {
        if (empty($oauthToken)) {
            throw new \InvalidArgumentException('Phraseanet token is empty');
        }

        return $this->client->withOptions([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'OAuth '.$oauthToken,
            ]
        ]);
    }
}
