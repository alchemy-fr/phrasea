<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookApiClientFactory
{
    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    public function create(): HttpClientInterface
    {
        return $this->client->withOptions([
            'max_redirects' => 0,            
        ]);
    }
}
