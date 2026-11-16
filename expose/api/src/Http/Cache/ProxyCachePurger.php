<?php

declare(strict_types=1);

namespace App\Http\Cache;

use Alchemy\MessengerBundle\Listener\TerminateStackListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purges the proxy cache of nginx (expose/api/docker/nginx/tpl/default.conf), whose key is made of the
 * Origin, the Accept header, the method and the path: every variant the cache may keep is purged.
 */
class ProxyCachePurger
{
    /**
     * The Accept headers nginx caches (map $ps_foreign_accept), any other one bypasses the cache.
     */
    public const array ACCEPT_HEADERS = [
        'application/ld+json, application/json',
        'application/json',
        'application/ld+json',
        'text/html',
        '*/*',
    ];

    private ?array $purgeStack = null;

    public function __construct(
        #[Autowire(service: 'cache_purger.client')]
        private readonly HttpClientInterface $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TerminateStackListener $terminateStackListener,
        /**
         * The only origin nginx caches apart from the requests without origin: any other one bypasses the cache.
         */
        #[Autowire(env: 'origin:EXPOSE_CLIENT_URL')]
        private readonly string $clientOrigin,
    ) {
    }

    public function purgeUri(string $uri): void
    {
        if (null === $this->purgeStack) {
            $this->purgeStack = [];
            $this->terminateStackListener->addCallback(function (): void {
                $stack = array_unique($this->purgeStack);
                $this->purgeStack = null;

                foreach ($stack as $path) {
                    $this->purgeVariants($path);
                }
            });
        }

        // The query string is not part of the cache key: the requests with one are never cached.
        $this->purgeStack[] = explode('?', $uri, 2)[0];
    }

    public function purgeRoute(string $routeName, array $parameter = []): void
    {
        $this->purgeUri($this->urlGenerator->generate($routeName, $parameter));
    }

    private function purgeVariants(string $path): void
    {
        $origins = [null];
        if ('' !== $this->clientOrigin) {
            $origins[] = $this->clientOrigin;
        }

        $responses = [];
        foreach (self::ACCEPT_HEADERS as $accept) {
            foreach ($origins as $origin) {
                $headers = ['Accept' => $accept];
                if (null !== $origin) {
                    $headers['Origin'] = $origin;
                }

                $responses[] = $this->client->request('GET', '/purge'.$path, [
                    'headers' => $headers,
                ]);
            }
        }

        foreach ($responses as $response) {
            // A 404 means the cache holds no such variant; getHeaders() throws on any other error.
            if (404 !== $response->getStatusCode()) {
                $response->getHeaders();
            }
        }
    }
}
