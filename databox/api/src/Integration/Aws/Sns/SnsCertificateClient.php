<?php

declare(strict_types=1);

namespace App\Integration\Aws\Sns;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads the certificate an SNS message is signed with, for Aws\Sns\MessageValidator,
 * which only asks for a "SigningCertURL" on an sns.<region>.amazonaws.com host.
 */
class SnsCertificateClient
{
    private const int TTL = 86400;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheInterface $cache,
    ) {
    }

    public function __invoke(string $url): string
    {
        return $this->cache->get('sns_cert_'.md5($url), function (ItemInterface $item) use ($url): string {
            $item->expiresAfter(self::TTL);

            return $this->client->request('GET', $url, [
                'timeout' => 10,
                'max_redirects' => 0,
            ])->getContent();
        });
    }
}
