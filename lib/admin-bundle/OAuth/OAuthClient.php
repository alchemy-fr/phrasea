<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\OAuth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class OAuthClient
{
    /**
     * @var string
     */
    private $clientId;
    /**
     * @var string
     */
    private $clientSecret;
    /**
     * @var Client
     */
    private $client;

    public function __construct(string $clientId, string $clientSecret, Client $client)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->client = $client;
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
