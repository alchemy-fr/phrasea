<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Auth;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Client\KeycloakUrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class OAuthClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly AuthServiceClient $client,
        private readonly KeycloakUrlGenerator $urlGenerator,
    )
    {
    }

    public function getAuthorizeUrl(string $redirectUri, string $state = null): string
    {
        return $this->urlGenerator->getAuthorizeUrl($this->clientId, $redirectUri, $state);
    }

    public function getAccessTokenFromAuthorizationCode(string $code, string $redirectUri): array
    {
        return $this->client->getTokenFromAuthorizationCode($code, $redirectUri, $this->clientId, $this->clientSecret);
    }

    private function getJson(ResponseInterface $response)
    {
        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }

    private function validatePayload(array $data, string $exceptionClass = CustomUserMessageAuthenticationException::class): void
    {
        if (isset($data['error'])) {
            throw new $exceptionClass(sprintf('%s: %s', $data['error'], $data['error_description'] ?? ''));
        }
    }
}
