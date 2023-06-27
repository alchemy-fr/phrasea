<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Client;

use Alchemy\RemoteAuthBundle\Security\InvalidResponseException;
use GuzzleHttp\Exception\ClientException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AdminClient
{
    private const ACCESS_TOKEN_CACHE_KEY = 'admin_remote_auth_access_token';

    public function __construct(protected AuthServiceClient $serviceClient, private readonly CacheInterface $cache, private readonly string $clientId, private readonly string $clientSecret)
    {
    }

    private function getAccessToken(): string
    {
        return $this->cache->get(self::ACCESS_TOKEN_CACHE_KEY, function (ItemInterface $item) {
            $response = $this->serviceClient->post('oauth/v2/token', [
                'json' => [
                    'scope' => 'user:list group:list',
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);
            $data = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

            $item->expiresAfter($data['expires_in']);

            return $data['access_token'];
        });
    }

    public function invalidateAccessToken(): void
    {
        $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);
    }

    public function executeWithAccessToken(callable $callback, int $retryCount = 0)
    {
        try {
            return $callback($this->getAccessToken());
        } catch (ClientException $e) {
            if ($retryCount < 1 && in_array($e->getResponse()->getStatusCode(), [401, 403], true)) {
                $this->invalidateAccessToken();

                return $this->executeWithAccessToken($callback, $retryCount + 1);
            }

            throw $e;
        } catch (InvalidResponseException $e) {
            if ($retryCount < 1) {
                $this->invalidateAccessToken();

                return $this->executeWithAccessToken($callback, $retryCount + 1);
            }

            throw $e;
        }
    }
}
