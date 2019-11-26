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

    public function pushLog(array $log): void
    {
        try {
            $response = $this->client->post('/log', [
                'json' => $log,
            ]);
        } catch (ClientException $e) {
            throw $e;
        }
    }
}
