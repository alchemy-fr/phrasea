<?php

declare(strict_types=1);

namespace App\Http\Cache;

use Alchemy\MessengerBundle\Listener\TerminateStackListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProxyCachePurger
{
    private ?array $purgeStack = null;

    public function __construct(
        #[Autowire(service: 'cache_purger.client')]
        private readonly HttpClientInterface $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TerminateStackListener $terminateStackListener,
        #[Autowire(env: 'EXPOSE_CLIENT_URL')]
        private readonly string $clientBaseUrl,
    ) {
    }

    public function purgeUri(string $uri): void
    {
        if (null === $this->purgeStack) {
            $this->purgeStack = [];
            $this->terminateStackListener->addCallback(function () use ($uri): void {
                $stack = array_unique($this->purgeStack);
                $this->purgeStack = null;

                foreach ($stack as $uri) {
                    foreach ([
                        'application/json',
                        'application/ld+json',
                        'text/html',
                    ] as $contentType) {
                        foreach ([
                            $this->clientBaseUrl,
                            null,
                        ] as $origin) {
                            try {
                                $this->client->request('GET', '/purge'.$uri, [
                                    'headers' => [
                                        'Accept' => $contentType,
                                        'Origin' => $origin,
                                    ],
                                ]);
                            } catch (ClientExceptionInterface $e) {
                                if (404 === $e->getResponse()->getStatusCode()) {
                                    // ignore 404 errors, as the cache might not exist yet
                                } else {
                                    throw $e;
                                }
                            }
                        }
                    }
                }
            });
        }

        $this->purgeStack[] = $uri;
    }

    public function purgeRoute(string $routeName, array $parameter = []): void
    {
        $this->purgeUri($this->urlGenerator->generate($routeName, $parameter));
    }
}
