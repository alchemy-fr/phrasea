<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class OAuthClient
{
    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly Client $client, private readonly string $authBaseUrl)
    {
    }

    public function getAuthorizeUrl(string $redirectUri, string $state = null): string
    {
        return sprintf(
            '%s/auth?client_id=%s&response_type=code&redirect_uri=%s',
            preg_replace('#/$#', '', $this->authBaseUrl),
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

    // TODO move to RemoteAuthBundle
    public function getAccessTokenFromAuthorizationCode(string $code, string $redirectUri): array
    {
        try {
            $response = $this->client->post('token', [
                RequestOptions::FORM_PARAMS => [
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

        return [$json['access_token'], $json['refresh_token']];
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
