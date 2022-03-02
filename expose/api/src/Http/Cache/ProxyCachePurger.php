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

    public function __construct(Client $client, UrlGeneratorInterface $urlGenerator, TerminateStackListener $terminateStackListener)
    {
        $this->client = $client;
        $this->urlGenerator = $urlGenerator;
        $this->terminateStackListener = $terminateStackListener;
    }

    public function purgeUri(string $uri): void
    {
        $this->terminateStackListener->addCallback(function () use ($uri): void {
            $this->client->get('/purge'.$uri);
        });
    }

    public function purgeRoute(string $routeName, array $parameter = []): void
    {
        $this->purgeUri($this->urlGenerator->generate($routeName, $parameter));
    }
}
