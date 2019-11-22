<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ReportClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function pushAction(string $action, array $payload): void
    {
        try {
            $response = $this->client->post('/action', [
                'json' => [
                    'action' => $action,
                    'payload' => $payload,
                ],
            ]);
        } catch (ClientException $e) {
            throw $e;
        }
    }
}
