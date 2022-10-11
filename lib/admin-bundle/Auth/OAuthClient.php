<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class OAuthClient
{
    private string $clientId;
    private string $clientSecret;
    private Client $client;
    private string $authBaseUrl;

    public function __construct(
        string $clientId,
        string $clientSecret,
        Client $client,
        string $authBaseUrl
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->client = $client;
        $this->authBaseUrl = $authBaseUrl;
    }

    public function getAuthorizeUrl(string $redirectUri, ?string $state = null): string
    {
        return sprintf(
            '%s/oauth/v2/auth?client_id=%s&response_type=code&redirect_uri=%s',
            $this->authBaseUrl,
            urlencode($this->clientId),
            urlencode($redirectUri)
        ).(!empty($state) ? '&state='.urlencode($state) : '');
    }

    public function getLogoutUrl(): string
    {
        return sprintf(
            '%s/security/logout',
            $this->authBaseUrl
        );
    }

    public function getAccessTokenFromAuthorizationCode(string $code, string $redirectUri): string
    {
        try {
            $response = $this->client->post('oauth/v2/token', [
                'json' => [
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $json = $this->getJson($response);
            if (401 === $response->getStatusCode()) {
                $this->validatePayload($json);
            }

            $this->validatePayload($json, BadRequestHttpException::class);

            throw $e;
        }

        $json = $this->getJson($response);
        $this->validatePayload($json);

        return $json['access_token'];
    }

    private function getJson(ResponseInterface $response)
    {
        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }

    private function validatePayload(array $data, string $exceptionClass = CustomUserMessageAuthenticationException::class): void
    {
        if (isset($data['error'])) {
            throw new $exceptionClass(sprintf('%s: %s', $data['error'], $data['error_description']));
        }
    }
}
