<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Client;

use Alchemy\RemoteAuthBundle\Security\InvalidResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class AuthServiceClient
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getTokenInfo(string $accessToken): array
    {
        try {
            $response = $this->client->request('GET', 'userinfo', [
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

    public function getUsers(string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->get('/admin/realms/master/users', $accessToken, $limit, $offset);
    }

    public function getGroups(string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->get('/admin/realms/master/groups', $accessToken, $limit, $offset);
    }

    private function get(string $path, string $accessToken, int $limit = null, int $offset = null): array
    {
        dump($accessToken);
        try {
            $response = $this->client->request('GET', $path, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
//                'query' => [
//                    'limit' => $limit,
//                    'offset' => $offset,
//                ],
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

    public function request(string $method, string $uri, array $options = [])
    {
        return $this->client->request($method, $uri, $options);
    }
}
