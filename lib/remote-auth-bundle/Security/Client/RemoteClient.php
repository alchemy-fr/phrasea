<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Client;

use Alchemy\RemoteAuthBundle\Security\InvalidResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class RemoteClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getTokenInfo(string $accessToken): array
    {
        try {
            $response = $this->client->request('GET', '/token-info', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse() && 401 === $e->getResponse()->getStatusCode()) {
                throw new InvalidResponseException($e->getResponse()->getBody()->getContents());
            }

            throw $e;
        }

        if (401 === $response->getStatusCode()) {
            throw new InvalidResponseException($response->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();
        $data = \GuzzleHttp\json_decode($content, true);

        return $data;
    }

    public function post(string $uri, array $options = [])
    {
        return $this->client->post($uri, $options);
    }
}
