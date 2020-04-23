<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\RemoteAuthBundle\Security\Client\RemoteClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RemoteAuthUserRepository implements UserRepositoryInterface
{
    private RemoteClient $remoteClient;
    private CacheInterface $cache;
    private string $clientId;
    private string $clientSecret;

    public function __construct(
        RemoteClient $remoteClient,
        CacheInterface $accessTokenCache,
        string $clientId,
        string $clientSecret
    )
    {
        $this->remoteClient = $remoteClient;
        $this->cache = $accessTokenCache;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getUsers(): array
    {
        return $this->remoteClient->getUsers($this->getAccessToken());
    }

    private function getAccessToken(): string
    {
        return $this->cache->get('remote_auth_access_token',  function (ItemInterface $item) {
            $response = $this->remoteClient->post('oauth/v2/token', [
                'json' => [
                    'scope' => 'user:list',
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
}
