<?php

declare(strict_types=1);

namespace App\Http\Cache;

use App\Listener\TerminateStackListener;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProxyCachePurger
{
    private Client $client;
    private UrlGeneratorInterface $urlGenerator;
    private TerminateStackListener $terminateStackListener;

    private ?array $purgeStack = null;

    public function __construct(Client $client, UrlGeneratorInterface $urlGenerator, TerminateStackListener $terminateStackListener)
    {
        $this->client = $client;
        $this->urlGenerator = $urlGenerator;
        $this->terminateStackListener = $terminateStackListener;
    }

    public function purgeUri(string $uri): void
    {
        if (null === $this->purgeStack) {
            $this->purgeStack = [];
            $this->terminateStackListener->addCallback(function () use ($uri): void {
                $stack = array_unique($this->purgeStack);
                $this->purgeStack = null;

                foreach ($stack as $uri) {
                    $this->client->get('/purge'.$uri);
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
