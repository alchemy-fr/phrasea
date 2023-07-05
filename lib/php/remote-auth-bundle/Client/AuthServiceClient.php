<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Utils;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class AuthServiceClient
{
    public function __construct(
        private Client $client,
        private KeycloakUrlGenerator $urlGenerator,
    )
    {
    }

    public function getTokenInfo(string $accessToken): array
    {
        return $this->wrapRequest(function () use ($accessToken) {
            return $this->client->request('GET', $this->urlGenerator->getUserinfoUrl(), [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        });
    }

    public function logout(string $accessToken, string $clientId, string $refreshToken): array
    {
        return $this->wrapRequest(function () use ($accessToken, $clientId, $refreshToken) {
            return $this->client->request('POST', $this->urlGenerator->getLogoutUrl(), [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                RequestOptions::FORM_PARAMS => [
                    'client_id' => $clientId,
                    'refresh_token' => $refreshToken,
                ]
            ]);
        });
    }

    public function getUsers(string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->get($this->urlGenerator->getUsersApiUrl(), $accessToken, $limit, $offset);
    }

    public function getGroups(string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->get($this->urlGenerator->getGroupsApiUrl(), $accessToken, $limit, $offset);
    }

    private function get(string $path, string $accessToken, int $limit = null, int $offset = null): array
    {
        return $this->wrapRequest(function () use ($path, $accessToken, $limit, $offset) {
            return $this->client->request('GET', $path, [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                RequestOptions::QUERY => [
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        });
    }

    private function wrapRequest(callable $handler): array
    {
        try {
            $response = $handler();
        } catch (ClientException $e) {
            if (401 === $e->getResponse()?->getStatusCode()) {
                throw new UnauthorizedHttpException('access_token', $e->getResponse()->getBody()->getContents(), $e);
            }

            throw $e;
        }

        $content = $response->getBody()->getContents();

        dump($content);

        return Utils::jsonDecode($content, true);
    }

    public function getClientCredentialAccessToken(string $clientId, string $clientSecret): array
    {
        $response = $this->client->post($this->urlGenerator->getTokenUrl(), [
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);

        return Utils::jsonDecode($response->getBody()->getContents(), true);
    }
}
