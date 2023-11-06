<?php

declare(strict_types=1);

namespace App\Http\Cache;

use App\Listener\TerminateStackListener;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProxyCachePurger
{
    private ?array $purgeStack = null;

    public function __construct(
        private readonly Client $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TerminateStackListener $terminateStackListener,
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
                            $this->client->get('/purge'.$uri, [
                                'headers' => [
                                    'Accept' => $contentType,
                                    'Origin' => $origin,
                                ],
                            ]);
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
