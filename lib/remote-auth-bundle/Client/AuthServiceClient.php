<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Client;

use Alchemy\RemoteAuthBundle\Security\InvalidResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class AuthServiceClient
{
    private Client $client;

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

        return \GuzzleHttp\json_decode($content, true);
    }

    public function getUsers(string $accessToken, int $limit = 200, int $offset = 0): array
    {
        return $this->get('/users', $accessToken, $limit, $offset);
    }

    public function getGroups(string $accessToken, int $limit = 200, int $offset = 0): array
    {
        return $this->get('/groups', $accessToken, $limit, $offset);
    }

    private function get(string $path, string $accessToken, int $limit = 200, int $offset = 0): array
    {
        try {
            $response = $this->client->request('GET', $path, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset,
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

        return \GuzzleHttp\json_decode($content, true);
    }

    public function post(string $uri, array $options = [])
    {
        return $this->client->post($uri, $options);
    }
}
