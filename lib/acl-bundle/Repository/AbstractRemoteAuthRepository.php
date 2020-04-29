<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

abstract class AbstractRemoteAuthRepository
{
    protected AuthServiceClient $serviceClient;
    private CacheInterface $cache;
    private string $clientId;
    private string $clientSecret;

    public function __construct(
        AuthServiceClient $serviceClient,
        CacheInterface $accessTokenCache,
        string $clientId,
        string $clientSecret
    ) {
        $this->serviceClient = $serviceClient;
        $this->cache = $accessTokenCache;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    protected function getAccessToken(): string
    {
        return $this->cache->get('remote_auth_access_token2', function (ItemInterface $item) {
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
}
