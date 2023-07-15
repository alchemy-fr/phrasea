<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OAuthClient
{
    public function __construct(
        private HttpClientInterface $keycloakClient,
        private KeycloakUrlGenerator $urlGenerator,
        private string $clientId,
        private string $clientSecret,
    )
    {
    }

    public function getTokenInfo(string $accessToken): array
    {
        return $this->wrapRequest(function () use ($accessToken) {
            return $this->keycloakClient->request('GET', $this->urlGenerator->getUserinfoUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        });
    }

    public function getTokenFromAuthorizationCode(string $code, string $redirectUri): array
    {
        $data = $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
            ],
        ])->toArray();

        return [$data['access_token'], $data['refresh_token']];
    }


    public function logout(string $accessToken, string $refreshToken): array
    {
        return $this->wrapRequest(function () use ($accessToken, $refreshToken) {
            return $this->keycloakClient->request('POST', $this->urlGenerator->getLogoutUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'body' => [
                    'client_id' => $this->clientId,
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
            return $this->keycloakClient->request('GET', $path, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => [
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

            return $response->toArray();
        } catch (ClientException $e) {
            if (401 === $e->getResponse()?->getStatusCode()) {
                throw new UnauthorizedHttpException('access_token', $e->getResponse()->getContent(false), $e);
            }

            throw $e;
        }
    }

    public function getClientCredentialAccessToken(): array
    {
        return $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ])->toArray();
    }

    public function getJwtPublicKey(): string
    {
        $data = $this->keycloakClient->request('GET', $this->urlGenerator->getRealmInfo())->toArray();

        return $data['public_key'];
    }
}
