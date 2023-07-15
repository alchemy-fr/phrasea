<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Auth;

use Alchemy\AuthBundle\Client\OAuthClient as AuthClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ServiceAccountClient
{
    private const ACCESS_TOKEN_CACHE_KEY = 'admin_access_token';

    public function __construct(
        protected readonly AuthClient $serviceClient,
        private readonly CacheInterface $cache,
    )
    {
    }

    private function getAccessToken(): string
    {
        return $this->cache->get(self::ACCESS_TOKEN_CACHE_KEY, function (ItemInterface $item): string {
            $data = $this->serviceClient->getClientCredentialAccessToken();

            $item->expiresAfter($data['expires_in']);

            return $data['access_token'];
        });
    }

    private function invalidateAccessToken(): void
    {
        $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);
    }

    public function executeWithAccessToken(callable $callback, int $retryCount = 0): mixed
    {
        try {
            return $callback($this->getAccessToken());
        } catch (ClientException $e) {
            if ($retryCount < 1 && in_array($e->getResponse()->getStatusCode(), [401, 403], true)) {
                $this->invalidateAccessToken();

                return $this->executeWithAccessToken($callback, $retryCount + 1);
            }

            throw $e;
        }
    }
}
